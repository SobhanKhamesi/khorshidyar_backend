<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\AutomationHomeSurvey;
use App\Models\AutomationIndustrialSurvey;
use App\Models\AutomatonComment;
use App\Models\Customer;
use App\Models\FailureCategory;
use App\Models\User;
use App\Services\SmsService;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutomationController extends Controller
{
    private ApiController $apiController;
    private ApiHelper $apiHelper;
    private SmsService $smsService;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper, SmsService $smsService)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
        $this->smsService = $smsService;
    }

    public function index($status)
    {
        // پارامتر مسیر رشته است؛ بدون تبدیل، مقایسه با ثابت‌های عددی همیشه نابرابر بود
        $status = (int)$status;

        $query = Automation::query()
            ->withCount(['orders', 'contractItems', 'payments', 'cheques'])
            ->where('status', $status)
            ->orderByDesc('created_at');

        if (auth()->user()->role == User::KARSHENAS_FANI && $status !== Automation::SUCCESS && $status !== Automation::FAILURE)
            $query->where('user_id', auth()->id());

        return $this->apiController->respondData($query->get());
    }


    public function store(Request $request)
    {
        $this->validate($request, [
            'date' => 'required',
            'customer' => 'required',
            'survey' => 'nullable|string',
            'panel_photo' => 'nullable|image',
            'required_photos.*' => 'nullable|image',
        ], [
            'date.required' => 'تاریخ را وارد نمایید',
            'customer.required' => 'مشتری را انتخاب نمایید',
        ]);

        $customer = Customer::findOrFail($request->input('customer'));
        $expert = User::findOrFail($request->input('expert'));
        $referrerPercent = $this->resolveReferrerPercent($request, $customer);

        $survey = $request->input('survey') ? json_decode($request->input('survey'), true) : null;

        $automation = DB::transaction(function () use ($request, $customer, $survey, $expert, $referrerPercent) {
            $automation = Automation::create([
                'status' => Automation::REQUEST,
                'survey_type' => $request->input('surveyType'),
                'referrer_percent' => $referrerPercent,
                'user_id' => auth()->id(),
                'customer_id' => $customer->id,
                'expert_id' => $expert->id,
                'visit_date' => Verta::parse($request->input('date'))->datetime(),
                'description' => $request->input('description'),
            ]);

            $this->saveSurvey($automation, $request->input('surveyType'), $survey, $request);

            return $automation;
        });

        if ($request->input('send_sms') && $request->input('send_sms') !== "false")
            return $this->SmsSurveys($automation);

        return $this->apiController->respondCreated();
    }

    public function confirmContract(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'contract_items' => 'nullable|array',
            'payments' => 'nullable|array',
            'cheques' => 'nullable|array',
        ]);

        $automation = Automation::findOrFail($request->input('id'));
        $nextStatus = $automation->status + 1;

        DB::transaction(function () use ($request, $automation, $nextStatus) {
            $automation->contractItems()->delete();
            foreach ($request->input('contract_items', []) as $item) {
                $automation->contractItems()->create([
                    'title' => $item['title'],
                    'amount' => $item['amount'] ?? 0,
                ]);
            }

            $automation->payments()->delete();
            foreach ($request->input('payments', []) as $payment) {
                $automation->payments()->create([
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'date' => isset($payment['date']) ? Verta::parse($payment['date'])->datetime() : null,
                ]);
            }

            $automation->cheques()->delete();
            foreach ($request->input('cheques', []) as $cheque) {
                $automation->cheques()->create([
                    'number' => $cheque['number'],
                    'bank_name' => $cheque['bank_name'] ?? null,
                    'amount' => $cheque['amount'],
                    'due_date' => isset($cheque['due_date']) ? Verta::parse($cheque['due_date'])->datetime() : null,
                ]);
            }

            $automation->update(['status' => $nextStatus]);
        });

        return $this->apiController->respondCreated();
    }


    // وجود محصولات قبلاً با قانون exists در validate بررسی شده است
    public function addOrder(array $orders, Automation $create)
    {
        $finalPrice = 0;
        $create->orders()->delete();
        foreach ($orders as $order) {
            $create->orders()->create([
                'product_id' => $order['orderProductId'],
                'price' => $order['orderPrice'],
                'count' => $order['orderCount'],
            ]);

            $finalPrice += $order['orderCount'] * $order['orderPrice'];
        }

        return $finalPrice;
    }

    // ثبت سریع پیش فاکتور: فقط با انتخاب مشتری، یک اتوماسیون جدید مستقیماً در مرحله «پیش فاکتور» ساخته می‌شود
    public function quickStore(Request $request)
    {
        $this->validate($request, [
            'customer' => 'required',
            'orders' => 'required|array|min:1',
            'orders.*.orderProductId' => 'required|exists:products,id',
            'orders.*.orderCount' => 'required|numeric|min:1',
            'orders.*.orderPrice' => 'required|numeric|min:0',
        ], [
            'customer.required' => 'مشتری را انتخاب نمایید',
            'orders.required' => 'حداقل یک آیتم پیش فاکتور وارد کنید',
        ]);

        $customer = Customer::findOrFail($request->input('customer'));
        $orders = $request->input('orders');
        $description = $request->input('description');
        $referrerPercent = $this->resolveReferrerPercent($request, $customer);

        $automation = DB::transaction(function () use ($customer, $orders, $description, $referrerPercent) {
            $invoice_number = Automation::nextInvoiceNumber();

            $automation = Automation::create([
                'status' => Automation::PRE_INVOICE,
                'user_id' => auth()->id(),
                'customer_id' => $customer->id,
                'referrer_percent' => $referrerPercent,
                'description' => $description,
            ]);

            if ($customer->status === Customer::LEAD)
                $customer->update(['status' => Customer::OPPORTUNITY]);

            $finalPrice = $this->addOrder($orders, $automation);

            $automation->update([
                'final_price' => $finalPrice,
                'invoice_number' => $invoice_number,
            ]);

            return $automation;
        });

        return $this->apiController->respondCreated('پیش فاکتور با موفقیت ثبت شد', $automation);
    }

    public function accept(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'orders' => 'required|array|min:1',
            'orders.*.orderProductId' => 'required|exists:products,id',
            'orders.*.orderCount' => 'required|numeric|min:1',
            'orders.*.orderPrice' => 'required|numeric|min:0',
        ], [
            'orders.required' => 'حداقل یک آیتم پیش فاکتور وارد کنید',
        ]);

        $automation = Automation::findOrFail($request->input('id'));
        $nextStatus = $automation->status + 1;
        $orders = $request->input('orders');
        $description = $request->input('description');
        $referrerPercent = $this->resolveReferrerPercent($request, $automation->customer, $automation);

        DB::transaction(function () use ($automation, $orders, $nextStatus, $description, $referrerPercent) {
            $invoice_number = Automation::nextInvoiceNumber();
            $finalPrice = $this->addOrder($orders, $automation);

            $automation->update([
                'status' => $nextStatus,
                'final_price' => $finalPrice,
                'invoice_number' => $invoice_number,
                'description' => $description,
                'referrer_percent' => $referrerPercent,
            ]);

            if ($automation->customer->status === Customer::LEAD)
                $automation->customer->update(['status' => Customer::OPPORTUNITY]);
        });

        return $this->apiController->respondCreated();
    }

    public function success(Request $request)
    {
        $automation = Automation::findOrFail($request->input('id'));

        DB::transaction(function () use ($automation) {
            $automation->update(['status' => Automation::SUCCESS]);

            if ($automation->customer->status === Customer::LEAD || $automation->customer->status === Customer::OPPORTUNITY)
                $automation->customer->update(['status' => Customer::CUSTOMER]);
        });

        if ($request->input("send_sms"))
            return $this->Sms($automation);
        else
            return $this->apiController->respondCreated();
    }

    public function cancel(Automation $automation)
    {
        $status = $automation->status;

        if ($status === Automation::SUCCESS || $status === Automation::FAILURE)
            return $this->apiController->respondError('این وضعیت قابل تغییر نمی‌باشد.');

        $automation->update([
            'status' => Automation::REQUEST,
        ]);
        return $this->apiController->respondCreated();
    }


    public function Sms(Automation $automation)
    {
        $sms = $this->smsService->sendTemplateToCustomer('تشکر از خرید', $automation->customer);
        if (!$sms)
            return $this->apiController->respondError("قالب sms برای 'تشکر از خرید' ثبت نشده است.");

        return $this->apiController->respondCreated('با موفقیت پیامک ارسال شد');
    }

    public function SmsSurveys(Automation $automation)
    {
        $suffix = "\n" . 'تاریخ بازدید : ' . $automation->jalali_visit_date;

        $sms = $this->smsService->sendTemplateToCustomer('فرم بازدید', $automation->customer, $suffix);
        if (!$sms)
            return $this->apiController->respondError("قالب sms برای 'فرم بازدید' ثبت نشده است.");

        return $this->apiController->respondCreated('با موفقیت پیامک ارسال شد');
    }


    public function destroy(Request $request)
    {
        $failure = null;
        if ($request->input('failure') !== null)
            $failure = FailureCategory::findOrFail($request->input('failure'))->id;

        $data = Automation::findorFail($request->input('id'));
        $data->update([
            'status' => Automation::FAILURE,
            'failure_id' => $failure,
        ]);
        return $this->apiController->respondDeleted('با موفقیت حذف شد', $data);
    }

    public function show(Automation $automation)
    {
        // orders.product از قبل در $with مدل بارگذاری شده است
        $automation->load(['homeSurvey.gasCoolers', 'industrialSurvey.mainConsumers']);
        return $this->apiController->respondData($automation);
    }

    public function update(Request $request)
    {
        $this->validate($request, [
            'date' => 'required',
            'customer' => 'required',
            'surveyType' => 'nullable|in:1,2',
            'survey' => 'nullable|string',
            'panel_photo' => 'nullable|image',
            'required_photos.*' => 'nullable|image',
        ]);

        $automation = Automation::findOrFail($request->input('id'));
        $customer = Customer::findOrFail($request->input('customer'));
        $expert = User::findOrFail($request->input('expert'));
        // اگر مشتری اتوماسیون عوض شده باشد، درصد قبلی متعلق به مشتری دیگر است و نباید حفظ شود
        $referrerPercent = $this->resolveReferrerPercent(
            $request, $customer, $automation->customer_id === $customer->id ? $automation : null
        );

        $survey = $request->input('survey') ? json_decode($request->input('survey'), true) : null;

        DB::transaction(function () use ($request, $automation, $customer, $survey, $expert, $referrerPercent) {
            $automation->update([
                // ستون date در جدول وجود ندارد؛ تاریخ بازدید در visit_date ذخیره می‌شود
                'visit_date' => Verta::parse($request->input('date'))->datetime(),
                'customer_id' => $customer->id,
                'expert_id' => $expert->id,
                'referrer_percent' => $referrerPercent,
                'description' => $request->input('description'),
                'survey_type' => $request->input('surveyType'),
            ]);

            $this->saveSurvey($automation, $request->input('surveyType'), $survey, $request);
        });

        return $this->apiController->respondUpdated();
    }

    /**
     * تغییر درصد معرف فقط برای همین پروژه؛ در هر مرحله‌ای از اتوماسیون قابل انجام است
     * و روی درصد تعریف شده برای مشتری یا پروژه‌های دیگر اثری ندارد.
     */
    public function referrerPercent(Request $request)
    {
        $this->validate($request, ['id' => 'required']);

        $automation = Automation::findOrFail($request->input('id'));
        if (!$automation->customer?->referrer_id)
            return $this->apiController->respondError('برای مشتری این پروژه معرفی ثبت نشده است');

        $percent = $this->apiHelper->referrerPercent($request);
        if ($percent === null)
            return $this->apiController->respondError('درصد معرف را وارد نمایید');

        $automation->update(['referrer_percent' => $percent]);

        return $this->apiController->respondUpdated('درصد معرف این پروژه ویرایش شد', [
            'referrer_percent' => $automation->referrer_percent,
            'referrer_income' => $automation->referrer_income,
        ]);
    }

    /**
     * درصد معرف یک پروژه:
     * - اگر مشتری معرف ندارد: null
     * - اگر درصد در درخواست ارسال شده: همان مقدار (فقط برای این پروژه)
     * - در غیر این صورت: درصد فعلی پروژه، و برای پروژه جدید درصد تعریف شده روی مشتری (در لحظه ایجاد ثابت می‌شود)
     */
    private function resolveReferrerPercent(Request $request, Customer $customer, ?Automation $automation = null): ?int
    {
        $percent = $this->apiHelper->referrerPercent($request);

        if (!$customer->referrer_id)
            return null;

        return $percent ?? $automation?->referrer_percent ?? $customer->referrer_percent;
    }

    public function comments(Request $request)
    {
        $leaveId = $request->input('id');
        $comments = AutomatonComment::where('automation_id', $leaveId)->orderBy('created_at', 'DESC')->get();
        return $this->apiController->respondData($comments);
    }

    public function newComment(Request $request)
    {

        $this->validate($request, [
            'content' => 'required'
        ], [
            'content.required' => 'کامنتی بنویسید'
        ]);

        $ticket = Automation::findOrFail($request->input('id'));

        AutomatonComment::create([
            'automation_id' => $ticket->id,
            'user_id' => auth()->id(),
            'content' => $request->input('content')
        ]);


        return $this->apiController->respondCreated();
    }

    public function deleteComment(Request $request)
    {

        $data = AutomatonComment::findOrFail($request->input('id'));

        if ($data->delete())
            return $this->apiController->respondDeleted();

        return $this->apiController->respondInternalError();
    }


    private function mapHomeSurvey(array $s): array
    {
        return [
            'goals' => $s['goals'] ?? [],
            'meter_single_selected' => $s['meter']['single']['selected'] ?? false,
            'meter_single_count' => $s['meter']['single']['count'] ?? null,
            'meter_single_amp' => $s['meter']['single']['amp'] ?? null,
            'meter_three_selected' => $s['meter']['three']['selected'] ?? false,
            'meter_three_count' => $s['meter']['three']['count'] ?? null,
            'meter_three_amp' => $s['meter']['three']['amp'] ?? null,
            'meter_demand_selected' => $s['meter']['demand']['selected'] ?? false,
            'meter_demand_count' => $s['meter']['demand']['count'] ?? null,
            'meter_demand_amp' => $s['meter']['demand']['amp'] ?? null,
            'bill_id' => $s['billId'] ?? null,
            'install_location_type' => $s['installLocation']['type'] ?? null,
            'install_location_distance' => $s['installLocation']['distance'] ?? null,
            'needs_extra_infrastructure' => $s['needsExtraInfrastructure'] ?? null,
            'extra_infrastructure_note' => ($s['needsExtraInfrastructure'] ?? false)
                ? ($s['extraInfrastructureNote'] ?? null)
                : null,
            'has_internal_panel' => $s['hasInternalPanel'] ?? null,
            'connection_point' => $s['connectionPoint'] ?? null,
            'cable_length' => $s['cableLength'] ?? null,
            'panel_location_note' => $s['panelLocationNote'] ?? null,
            'consumer_fridge_selected' => $s['consumers']['fridge']['selected'] ?? false,
            'consumer_fridge_count' => $s['consumers']['fridge']['count'] ?? null,
            'consumer_lighting_selected' => $s['consumers']['lighting']['selected'] ?? false,
            'consumer_lighting_watt' => $s['consumers']['lighting']['watt'] ?? null,
            'consumer_tv_selected' => $s['consumers']['tv']['selected'] ?? false,
            'consumer_rolling_door_selected' => $s['consumers']['rollingDoor']['selected'] ?? false,
            'consumer_rolling_door_count' => $s['consumers']['rollingDoor']['count'] ?? null,
            'consumer_water_pump_selected' => $s['consumers']['waterPump']['selected'] ?? false,
            'consumer_elevator_selected' => $s['consumers']['elevator']['selected'] ?? false,
            'consumer_elevator_type' => $s['consumers']['elevator']['type'] ?? null,
            'consumer_cctv_selected' => $s['consumers']['cctv']['selected'] ?? false,
            'consumer_cctv_count' => $s['consumers']['cctv']['count'] ?? null,
            'consumer_gas_cooler_selected' => $s['consumers']['gasCooler']['selected'] ?? false,
            'consumer_water_cooler_selected' => $s['consumers']['waterCooler']['selected'] ?? false,
            'consumer_water_cooler_type' => $s['consumers']['waterCooler']['type'] ?? null,
            'consumer_water_cooler_count' => $s['consumers']['waterCooler']['count'] ?? null,
            'outage_items' => $s['outageItems'] ?? [],
            'night_duration' => $s['nightDuration'] ?? null,
            'system_type' => $s['systemType'] ?? null,
            'main_decision_maker' => $s['mainDecisionMaker'] ?? null,
            'has_limitation' => $s['hasLimitation'] ?? null,
            'panel_photo' => $s['panel_photo'] ?? null,
            'description' => $s['description'] ?? null,
        ];
    }

    private function mapIndustrialSurvey(array $s): array
    {
        return [
            'goals' => $s['goals'] ?? [],
            'meter_type' => $s['meterType'] ?? null,
            'meter_amp' => $s['meterAmp'] ?? null,
            'bill_id' => $s['billId'] ?? null,
            'branch_capacity' => $s['branchCapacity'] ?? null,
            'has_dedicated_transformer' => $s['hasDedicatedTransformer'] ?? null,
            'outage_per_week' => $s['outagePerWeek'] ?? null,
            'outage_hours_each' => $s['outageHoursEach'] ?? null,
            'outage_time' => $s['outageTime'] ?? null,
            'outage_effects' => $s['outageEffects'] ?? [],
            'must_stay_on_equipment' => $s['mustStayOnEquipment'] ?? null,
            'damage_at_moment' => $s['damageAtMoment'] ?? null,
            'damage_per_hour' => $s['damagePerHour'] ?? null,
            'night_backup_duration' => $s['nightBackupDuration'] ?? null,
            'install_location' => $s['installLocation'] ?? null,
            'has_diesel_generator' => $s['hasDieselGenerator'] ?? null,
            'diesel_capacity' => $s['dieselCapacity'] ?? null,
            'diesel_fuel_consumption' => $s['dieselFuelConsumption'] ?? null,
            'diesel_working_hours' => $s['dieselWorkingHours'] ?? null,
            'system_preference' => $s['systemPreference'] ?? null,
            'payment_type' => $s['paymentType'] ?? null,
            'final_decision_maker' => $s['finalDecisionMaker'] ?? null,
            'required_photos' => $s['required_photos'] ?? [],
            'description' => $s['description'] ?? null,
        ];
    }

    private function saveSurvey(Automation $automation, $surveyType, ?array $survey, Request $request)
    {
        if ($surveyType == Automation::HOME && $survey) {

            // فایل panel_photo رو جدا از $request می‌گیریم، نه از survey
            if ($request->hasFile('panel_photo')) {
                $file = $request->file('panel_photo');
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $destinationPath = config('webroot.path') . '/assets/panel_photos';
                if (!is_dir($destinationPath))
                    mkdir($destinationPath, 0755, true);
                $file->move($destinationPath, $fileName);
                $survey['panel_photo'] = 'assets/panel_photos/' . $fileName;
            } else {
                // اگر در حالت ویرایش، عکس جدیدی آپلود نشده، مسیر قبلی رو دست نخورده نگه دار
                $existing = AutomationHomeSurvey::where('automation_id', $automation->id)->first();
                $survey['panel_photo'] = $existing->panel_photo ?? null;
            }

            $homeSurvey = AutomationHomeSurvey::updateOrCreate(
                ['automation_id' => $automation->id],
                $this->mapHomeSurvey($survey)
            );

            $homeSurvey->gasCoolers()->delete();
            foreach ($survey['consumers']['gasCooler']['items'] ?? [] as $item)
                $homeSurvey->gasCoolers()->create([
                    'size' => $item['size'],
                    'inverter' => $item['inverter'] ?? false,
                    'count' => $item['count'],
                ]);

        } elseif ($surveyType == Automation::INDUSTRIAL && $survey) {

            // آپلود عکس‌های متعدد صنعتی
            $photoPaths = [];
            if ($request->hasFile('required_photos')) {
                $destinationPath = config('webroot.path') . '/assets/required_photos';
                if (!is_dir($destinationPath))
                    mkdir($destinationPath, 0755, true);
                foreach ($request->file('required_photos') as $file) {
                    $fileName = $surveyType . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->move($destinationPath, $fileName);
                    $photoPaths[] = 'assets/required_photos/' . $fileName;
                }
                $survey['required_photos'] = $photoPaths;
            } else {
                $existing = AutomationIndustrialSurvey::where('automation_id', $automation->id)->first();
                $survey['required_photos'] = $existing->required_photos ?? [];
            }

            $industrialSurvey = AutomationIndustrialSurvey::updateOrCreate(
                ['automation_id' => $automation->id],
                $this->mapIndustrialSurvey($survey)
            );

            $industrialSurvey->mainConsumers()->delete();
            foreach ($survey['mainConsumers'] ?? [] as $item)
                $industrialSurvey->mainConsumers()->create([
                    'name' => $item['name'],
                    'power' => $item['power'] ?? null,
                    'type' => $item['type'] ?? null,
                    'starter_type' => $item['starterType'] ?? null,
                ]);
        }
    }


}
