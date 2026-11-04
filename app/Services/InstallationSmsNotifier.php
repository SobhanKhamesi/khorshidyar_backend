<?php

namespace App\Services;

use App\Models\InstallationService;
use App\Models\InstallerAppointment;
use App\Models\Sms;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Throwable;

/**
 * پیامک‌های ارجاع سرویس نصب:
 *  - به نصاب: «کارتابل سرویس نصب خود را چک کنید»
 *  - به مشتری: نام نصاب + تاریخ نصب + شماره تماس نصاب
 */
class InstallationSmsNotifier
{
    private SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * @param Collection<InstallerAppointment> $appointments نوبت‌هایی که تازه ارجاع یا تغییر کرده‌اند
     * @return array هشدارهای قابل نمایش به کاربر (مثلاً نبود شماره همراه نصاب)
     */
    public function notifyReferral(InstallationService $service, Collection $appointments): array
    {
        // اطلاعات ثبت شده و ارجاع انجام شده؛ خطای پیامک نباید پاسخ موفق را خراب کند
        try {
            return $this->sendReferral($service, $appointments);
        } catch (Throwable $e) {
            report($e);
            return ['ارسال پیامک‌های ارجاع با خطا مواجه شد'];
        }
    }

    private function sendReferral(InstallationService $service, Collection $appointments): array
    {
        $warnings = [];
        $appointments = EloquentCollection::make($appointments->all())->load('installer');

        foreach ($appointments->unique('user_id') as $appointment) {
            $installer = $appointment->installer;

            if (!$installer?->mobile) {
                $warnings[] = "شماره همراه «{$installer?->name}» ثبت نشده و پیامک ارجاع برای او ارسال نشد";
                continue;
            }

            $body = "{$installer->name} عزیز\n"
                . "یک سرویس نصب برای مشتری «{$service->customer?->name}» به شما ارجاع شد.\n"
                . "لطفاً کارتابل سرویس نصب خود را چک کنید.";

            $sent = $this->smsService->send($installer->mobile, $body);
            $this->log($body, $installer->mobile, $sent);

            if (!$sent)
                $warnings[] = "ارسال پیامک به نصاب «{$installer->name}» ناموفق بود";
        }

        $customer = $service->customer;
        if (!$customer)
            return $warnings;

        // برای مشتری همه‌ی نصاب‌های فعال این سرویس فرستاده می‌شود تا تصویر کامل داشته باشد
        $lines = $service->activeAppointments()->with('installer')->orderBy('date')->orderBy('start_time')->get()
            ->map(function (InstallerAppointment $appointment) {
                $date = (new Verta($appointment->date))->format('Y/m/d');
                $time = substr((string)$appointment->start_time, 0, 5);
                $mobile = $appointment->installer?->mobile ?: '-';

                return "نصاب: {$appointment->installer?->name}\n"
                    . "تاریخ نصب: {$date} ساعت {$time}\n"
                    . "شماره تماس نصاب: {$mobile}";
            })
            ->implode("\n\n");

        $content = $customer->greeting_name . "\n\n"
            . "سرویس نصب شما با مشخصات زیر برنامه‌ریزی شد:\n\n" . $lines;

        $sms = $this->smsService->sendAndLog($customer, $content);
        if (!$sms->count)
            $warnings[] = 'ارسال پیامک اطلاعات نصاب به مشتری ناموفق بود';

        return $warnings;
    }

    private function log(string $body, string $mobile, bool $sent): void
    {
        Sms::create([
            'send_to_all' => false,
            'receiver_type' => Sms::TYPE_SPECIFIC,
            'receivers' => $sent ? [$mobile] : [],
            'count' => $sent ? 1 : 0,
            'body' => $body,
            'user_id' => auth()->id(),
        ]);
    }
}
