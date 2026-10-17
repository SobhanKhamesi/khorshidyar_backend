<?php

namespace App\Services;

use App\Models\InstallerAppointment;
use App\Models\InstallerWorkingDay;
use Carbon\Carbon;

/**
 * منطق مشترک اعتبارسنجی نوبت‌های نصاب (روز کاری بودن + تداخل نداشتن با نوبت‌های دیگر).
 *
 * نکته مهم: زمان شروع و پایان هر نوبت را همیشه خود کاربر در فرم انتخاب می‌کند؛
 * این سرویس هیچ بازه‌ی از پیش تعیین‌شده‌ای (اسلات) برای انتخاب تولید نمی‌کند،
 * فقط بررسی می‌کند که بازه‌ی دلخواه کاربر معتبر است یا نه.
 */
class InstallerSlotService
{
    /**
     * تبدیل روز هفته‌ی میلادی (Carbon) به شماره‌ی روز هفته‌ی شمسی
     * که در جدول installer_working_days ذخیره شده است.
     * شنبه = 0 ، یکشنبه = 1 ، ... ، جمعه = 6
     *
     * Carbon::dayOfWeek => یکشنبه = 0 ، دوشنبه = 1 ، ... ، شنبه = 6
     */
    public function resolveWeekday(Carbon $date): int
    {
        return ($date->dayOfWeek + 1) % 7;
    }

    /**
     * روز کاری فعال نصاب برای تاریخ داده شده را برمی‌گرداند (در صورت وجود).
     *
     * $installerId همیشه باید به همان شکلی که در دیتابیس ذخیره شده (شناسه‌ی
     * کاربر - UUID) پاس داده شود. تبدیل آن به (int) باعث می‌شود شناسه به
     * اشتباه بریده/صفر شود و هیچ روز کاری‌ای پیدا نشود؛ این خودِ یکی از
     * دلایل اصلی باگ «تشخیص اشتباه روز کاری / تداخل نوبت» بود.
     */
    public function getActiveWorkingDay(string $installerId, Carbon $date): ?InstallerWorkingDay
    {
        return InstallerWorkingDay::where('user_id', $installerId)
            ->where('weekday', $this->resolveWeekday($date))
            ->active()
            ->first();
    }

    /**
     * بررسی می‌کند بازه‌ی زمانی دلخواه کاربر (start/end) برای نصاب داده شده
     * قابل رزرو هست یا نه:
     *  1) پایان بعد از شروع باشد
     *  2) در روز کاری فعال نصاب باشد
     *  3) داخل ساعت کاری آن روز باشد
     *  4) با هیچ نوبت فعال دیگری تداخل نداشته باشد
     *
     * در صورت نامعتبر بودن Exception با پیام مناسب پرتاب می‌شود.
     */
    public function assertSlotIsAvailable(
        string $installerId,
        Carbon $date,
        string $startTime,
        string $endTime,
        ?string $excludeAppointmentId = null
    ): void {
        if ($startTime >= $endTime) {
            throw new \Exception('ساعت پایان باید بعد از ساعت شروع باشد');
        }

        $workingDay = $this->getActiveWorkingDay($installerId, $date);

        if (!$workingDay) {
            throw new \Exception('این روز جزو روزهای کاری نصاب نیست');
        }

        $workStart = substr($workingDay->start_time, 0, 5);
        $workEnd = substr($workingDay->end_time, 0, 5);

        if ($startTime < $workStart || $endTime > $workEnd) {
            throw new \Exception('بازه انتخاب شده خارج از ساعت کاری نصاب است');
        }

        $conflictQuery = InstallerAppointment::forInstaller($installerId)
            ->active()
            ->whereDate('date', $date->format('Y-m-d'))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($excludeAppointmentId) {
            $conflictQuery->where('id', '!=', $excludeAppointmentId);
        }

        if ($conflictQuery->exists()) {
            throw new \Exception('نصاب در این بازه زمانی نوبت دیگری دارد');
        }
    }

    /**
     * بازه‌های زمانی رزرو شده (فعال) نصاب در یک روز مشخص را برمی‌گرداند.
     * این فقط جهت نمایش اطلاعاتی به کاربر است (کاربر همچنان خودش ساعت
     * شروع/پایان دلخواهش را وارد می‌کند) و هیچ بازه‌ی آماده‌ای برای
     * انتخاب تولید نمی‌کند.
     */
    public function getBusyRanges(string $installerId, Carbon $date, ?string $excludeAppointmentId = null): array
    {
        $query = InstallerAppointment::forInstaller($installerId)
            ->active()
            ->whereDate('date', $date->format('Y-m-d'))
            ->orderBy('start_time');

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->get(['start_time', 'end_time', 'type'])
            ->map(fn($a) => [
                'start' => substr($a->start_time, 0, 5),
                'end' => substr($a->end_time, 0, 5),
                // نوع نوبت (panel/wiring/commissioning/after_sale) هم برگردانده می‌شود
                // تا فرم ثبت بتواند رنگ‌بندی را دقیقاً مطابق تقویم کاری نمایش دهد
                'type' => $a->type_key,
                'type_label' => $a->type_label,
            ])
            ->values()
            ->toArray();
    }
}
