<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\InstallerAppointment;
use App\Models\User;
use App\Services\InstallerSlotService;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstallerAppointmentController extends Controller
{
    private ApiController $apiController;
    private InstallerSlotService $slotService;

    public function __construct(ApiController $apiController, InstallerSlotService $slotService)
    {
        $this->apiController = $apiController;
        $this->slotService = $slotService;
    }

    public function forInstaller(Request $request, $installerId)
    {
        $this->validate($request, [
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $installer = User::findOrFail($installerId);

        $appointments = InstallerAppointment::forInstaller($installer->id)
            ->active()
            ->whereBetween('date', [$request->input('from'), $request->input('to')])
            ->orderBy('date')->orderBy('start_time')
            ->get()
            ->map(fn($a) => $this->mapAppointment($a));

        // بازدیدهای اتوماسیون که این کاربر به عنوان کارشناس فنی (expert_id) روی
        // آن‌ها ثبت شده، هم به عنوان یک ردیف تمام‌روز (بدون ساعت مشخص) به همراه
        // نوبت‌های نصب همین نصاب در تقویم نمایش داده می‌شوند. این آیتم‌ها فقط
        // جنبه‌ی نمایشی دارند و در منطق تداخل/دسترس‌پذیری InstallerSlotService
        // دخالتی ندارند.
        $visits = $this->getAutomationVisits($request, ['expert_id' => $installer->id])
            ->map(fn($a) => $this->mapVisit($a));

        return $this->apiController->respondData(
            $appointments->concat($visits)
                ->sortBy([['date', 'asc'], ['start_time', 'asc']])
                ->values()
        );
    }

    public function forAll(Request $request)
    {
        $this->validate($request, [
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        $appointments = InstallerAppointment::with(['installer', 'customer'])
            ->active()
            ->whereBetween('date', [$request->input('from'), $request->input('to')])
            ->orderBy('date')->orderBy('start_time')
            ->get()
            ->map(fn($a) => $this->mapAppointment($a, true));

        $visits = $this->getAutomationVisits($request)
            ->map(fn($a) => $this->mapVisit($a, true));

        return $this->apiController->respondData(
            $appointments->concat($visits)
                ->sortBy([['date', 'asc'], ['start_time', 'asc']])
                ->values()
        );
    }

    /**
     * بازدیدهای ثبت‌شده در اتوماسیون (visit_date) در بازه‌ی تاریخی درخواستی.
     * $extraWhere برای محدود کردن نتایج به یک کارشناس فنی مشخص استفاده می‌شود
     * (مثلاً هنگام نمایش تقویم یک نصاب/کارشناس خاص).
     */
    private function getAutomationVisits(Request $request, array $extraWhere = [])
    {
        return Automation::query()
            ->without(['orders', 'failure', 'comments', 'contractItems', 'payments', 'cheques'])
            ->with(['customer', 'expert'])
            ->where($extraWhere)
            ->whereNotNull('expert_id')
            ->whereNotNull('visit_date')
            ->whereBetween('visit_date', [
                $request->input('from') . ' 00:00:00',
                $request->input('to') . ' 23:59:59',
            ])
            ->orderBy('visit_date')
            ->get();
    }

    private function mapAppointment(InstallerAppointment $a, bool $withInstaller = false): array
    {
        $data = [
            'id' => $a->id,
            'date' => $a->date->format('Y-m-d'),
            'time' => substr($a->start_time, 0, 5) . ' - ' . substr($a->end_time, 0, 5),
            'start_time' => substr($a->start_time, 0, 5),
            'end_time' => substr($a->end_time, 0, 5),
            'all_day' => false,
            'role' => $a->type_key,
            'role_label' => $a->type_label,
            'automation_id' => $a->automation_id,
            'after_sale_service_id' => $a->after_sale_service_id,
            'installation_service_id' => $a->installation_service_id,
            'customer_name' => optional($a->customer)->name,
            'address' => optional($a->customer)->address,
        ];

        if ($withInstaller) {
            $data['installer_id'] = $a->user_id;
            $data['installer_name'] = optional($a->installer)->name;
        }

        return $data;
    }

    /**
     * تبدیل یک رکورد بازدید اتوماسیون (visit_date) به همان قالب نوبت‌های نصاب،
     * تا بتوان آن را در کنار نوبت‌های InstallerAppointment در تقویم نمایش داد.
     * این آیتم صرفاً نمایشی است: نه ساعت شروع/پایان مشخصی دارد (all_day=true)
     * و نه در محاسبات تداخل/در دسترس بودن InstallerSlotService لحاظ می‌شود.
     */
    private function mapVisit(Automation $a, bool $withInstaller = false): array
    {
        $data = [
            'id' => 'visit-' . $a->id,
            'date' => Carbon::parse($a->visit_date)->format('Y-m-d'),
            'time' => null,
            'start_time' => null,
            'end_time' => null,
            'all_day' => true,
            'role' => 'visit',
            'role_label' => 'بازدید',
            'automation_id' => $a->id,
            'after_sale_service_id' => null,
            'installation_service_id' => null,
            'customer_name' => optional($a->customer)->name,
            'address' => optional($a->customer)->address ?? $a->address,
        ];

        if ($withInstaller) {
            $data['installer_id'] = $a->expert_id;
            $data['installer_name'] = optional($a->expert)->name;
        }

        return $data;
    }

    /**
     * اطلاعات کمکی برای فرم رزرو: آیا این روز جزو روزهای کاری نصاب هست،
     * ساعت شروع/پایان کاری آن روز چیست، و چه بازه‌هایی از قبل رزرو شده‌اند.
     *
     * این endpoint هیچ بازه‌ی از پیش تعیین‌شده‌ای (اسلات) برای انتخاب کاربر
     * تولید نمی‌کند؛ فقط جهت نمایش اطلاعاتی است تا کاربر با آگاهی از ساعت
     * کاری و بازه‌های رزروشده، ساعت شروع/پایان دلخواه خودش را وارد کند.
     * اعتبارسنجی نهایی همیشه هنگام ثبت، توسط InstallerSlotService::assertSlotIsAvailable
     * انجام می‌شود.
     */
    public function availableSlots(Request $request, $installerId)
    {
        $this->validate($request, [
            'date' => 'required',
        ], [
            'date.required' => 'تاریخ را وارد نمایید',
        ]);

        $installer = User::findOrFail($installerId);

        // باگ قبلی: اینجا تاریخ به Carbon تبدیل نمی‌شد و مستقیم از شیء Verta
        // خوانده می‌شد. Verta با Carbon متد dayOfWeek متفاوتی دارد، پس محاسبه‌ی
        // روز هفته با همان فرمولی که در assertSlotIsAvailable استفاده می‌شود
        // (که روی Carbon کار می‌کند) هم‌خوان نبود و باعث می‌شد بعضی روزهای کاری
        // اشتباه «غیرکاری» تشخیص داده شوند.
        $date = Verta::parse($request->input('date'))->toCarbon();

        $workingDay = $this->slotService->getActiveWorkingDay($installer->id, $date);

        if (!$workingDay) {
            return $this->apiController->respondData([
                'is_working_day' => false,
                'work_start' => null,
                'work_end' => null,
                'busy' => [],
            ]);
        }

        $busyRanges = $this->slotService->getBusyRanges($installer->id, $date);

        return $this->apiController->respondData([
            'is_working_day' => true,
            'work_start' => substr($workingDay->start_time, 0, 5),
            'work_end' => substr($workingDay->end_time, 0, 5),
            'busy' => $busyRanges,
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'installer_id' => 'required|exists:users,id',
            'customer_id' => 'required|exists:customers,id',
            'automation_id' => 'nullable|exists:automation,id',
            'type' => 'required|in:1,2,3',
            'date' => 'required',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'description' => 'nullable|string',
        ], [
            'installer_id.required' => 'نصاب را انتخاب نمایید',
            'date.required' => 'تاریخ نصب را وارد نمایید',
            'start_time.required' => 'ساعت شروع را وارد نمایید',
            'end_time.after' => 'ساعت پایان باید بعد از ساعت شروع باشد',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $date = Verta::parse($request->input('date'))->toCarbon();

                // installer_id از نوع UUID است؛ نباید به (int) کست شود، وگرنه
                // شناسه اشتباه می‌شود و اعتبارسنجی روز کاری/تداخل نوبت غلط از آب
                // در می‌آید (این خودِ باگ اصلی گزارش‌شده بود).
                $installerId = $request->input('installer_id');

                $this->slotService->assertSlotIsAvailable(
                    $installerId,
                    $date,
                    $request->input('start_time'),
                    $request->input('end_time')
                );

                InstallerAppointment::create([
                    'user_id' => $installerId,
                    'customer_id' => $request->input('customer_id'),
                    'automation_id' => $request->input('automation_id'),
                    'type' => $request->input('type'),
                    'date' => $date->format('Y-m-d'),
                    'start_time' => $request->input('start_time'),
                    'end_time' => $request->input('end_time'),
                    'status' => InstallerAppointment::RESERVED,
                    'description' => $request->input('description'),
                ]);
            });
        } catch (\Exception $e) {
            return $this->apiController->respondError($e->getMessage());
        }

        return $this->apiController->respondCreated();
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'installer_id' => 'required|exists:users,id',
            'date' => 'required',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        $appointment = InstallerAppointment::findOrFail($id);

        try {
            DB::transaction(function () use ($request, $appointment) {
                $date = Verta::parse($request->input('date'))->toCarbon();
                $installerId = $request->input('installer_id');

                $this->slotService->assertSlotIsAvailable(
                    $installerId,
                    $date,
                    $request->input('start_time'),
                    $request->input('end_time'),
                    $appointment->id
                );

                $appointment->update([
                    'user_id' => $installerId,
                    'date' => $date->format('Y-m-d'),
                    'start_time' => $request->input('start_time'),
                    'end_time' => $request->input('end_time'),
                ]);
            });
        } catch (\Exception $e) {
            return $this->apiController->respondError($e->getMessage());
        }

        return $this->apiController->respondUpdated();
    }

    public function destroy($id)
    {
        $appointment = InstallerAppointment::findOrFail($id);
        $appointment->update(['status' => InstallerAppointment::CANCELED]);

        return $this->apiController->respondDeleted();
    }
}
