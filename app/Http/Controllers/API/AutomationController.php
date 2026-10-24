<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\AutomationHomeSurvey;
use App\Models\AutomationIndustrialSurvey;
use App\Models\AutomatonComment;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\FailureCategory;
use App\Models\Product;
use App\Models\Sms;
use App\Models\SourceSms;
use App\Models\User;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutomationController extends Controller
{
    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }

    public function index($status)
    {
        $query = Automation::query()
            ->withCount(['orders', 'contractItems', 'payments', 'cheques']);

        if (auth()->user()->role == User::KARSHENAS_FANI && $status !== Automation::SUCCESS && $status !== Automation::FAILURE)
            $list = $query->where('user_id', auth()->id())->where('status', $status)->orderbyDesc('created_at')->get();
        else
            $list = $query->where('status', $status)->orderbyDesc('created_at')->get();

        return $this->apiController->respondData($list);
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

        $survey = $request->input('survey') ? json_decode($request->input('survey'), true) : null;

        $automation = DB::transaction(function () use ($request, $customer, $survey, $expert) {
            $automation = Automation::create([
                'status' => Automation::REQUEST,
                'survey_type' => $request->input('surveyType'),
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


    public function addOrder(array $orders, Automation $create)
    {
        $finalPrice = 0;
        $create->orders()->delete();
        foreach ($orders as $order) {
            $product = Product::findOrFail($order['orderProductId']);

            $create->orders()->create([
                'product_id' => $product->id,
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

        $invoice_number = (Automation::query()
                ->whereNotNull('invoice_number')
                ->whereRaw('CAST(invoice_number AS UNSIGNED) >= 100')
                ->whereRaw('CAST(invoice_number AS UNSIGNED) < 10000')
                ->max(DB::raw('CAST(invoice_number AS UNSIGNED)')) ?? 99) + 1;

        $automation = DB::transaction(function () use ($customer, $orders, $description, $invoice_number) {
            $automation = Automation::create([
                'status' => Automation::PRE_INVOICE,
                'user_id' => auth()->id(),
                'customer_id' => $customer->id,
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

        $invoice_number = (Automation::query()
                ->whereNotNull('invoice_number')
                ->whereRaw('CAST(invoice_number AS UNSIGNED) >= 100')
                ->whereRaw('CAST(invoice_number AS UNSIGNED) < 10000')
                ->max(DB::raw('CAST(invoice_number AS UNSIGNED)')) ?? 99) + 1;

        DB::transaction(function () use ($automation, $orders, $nextStatus, $invoice_number, $description) {
            $finalPrice = $this->addOrder($orders, $automation);

            $automation->update([
                'status' => $nextStatus,
                'final_price' => $finalPrice,
                'invoice_number' => $invoice_number,
                'description' => $description,
            ]);

            if ($automation->customer->status === Customer::LEAD)
                $automation->customer->update(['status' => Customer::OPPORTUNITY]);
        });

        return $this->apiController->respondCreated();
    }

    public function success(Request $request)
    {
        $automation = Automation::findOrFail($request->input('id'));
        $automation->update(['status' => Automation::SUCCESS]);

        if ($automation->customer->status === Customer::LEAD || $automation->customer->status === Customer::OPPORTUNITY)
            $automation->customer->update(['status' => Customer::CUSTOMER]);

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
        $smsTotalCount = 0;
        $mobilesArray = [];

        $sms = SourceSms::where('title', 'تشکر از خرید')->first();
        if (!$sms)
            return $this->apiController->respondError("قالب sms برای 'نظرسنجی' ثبت نشده است.");

        $customer = $automation->customer;
        $mobile = $customer->contact_number;
        $name = $customer->name;
        $content = '';
        if ($customer->type == 0) {
            $content = 'آقای ';
        } elseif ($customer->type == 1) {
            $content = 'خانم ';
        }
        $content .= $name;
        $content .= "\n";
        $content .= "\n";
        $content .= $sms->description;

        $urlSMS = "https://RayganSMS.com/SendMessageWithUrl.ashx?Username=" . urlencode(env('TREZ_USERNAME')) . "&Password=" . urlencode(env('TREZ_PASSWORD')) . "&PhoneNumber=" . urlencode(env('TREZ_NUMBER')) . "&MessageBody=" . urlencode($content) . "&RecNumber=" . urlencode($mobile) . "&Smsclass=1";
        if (file_get_contents($urlSMS)) {
            $mobilesArray[] = $mobile;
            $smsTotalCount++;
        }

        $create = Sms::create([
            'receivers' => $mobilesArray,
            'count' => $smsTotalCount,
            'body' => $content,
            'sent_at' => Carbon::now(),
            'user_id' => auth()->id()
        ]);
        if ($create) {
            return $this->apiController->respondCreated('با موفقیت پیامک ارسال شد');
        } else {
            return $this->apiController->respondInternalError();
        }
    }

    public function SmsSurveys(Automation $automation)
    {
        $smsTotalCount = 0;
        $mobilesArray = [];

        $sms = SourceSms::where('title', 'فرم بازدید')->first();
        if (!$sms)
            return $this->apiController->respondError("قالب sms برای 'فرم بازدید' ثبت نشده است.");

        $customer = $automation->customer;
        $mobile = $customer->contact_number;
        $name = $customer->name;
        $content = '';
        if ($customer->type == 0) {
            $content = 'آقای ';
        } elseif ($customer->type == 1) {
            $content = 'خانم ';
        }
        $content .= $name;
        $content .= "\n";
        $content .= "\n";
        $content .= $sms->description;
        $content .= "\n";
        $content .= 'تاریخ بازدید : ';
        $content .= $automation->jalali_visit_date;

        $urlSMS = "https://RayganSMS.com/SendMessageWithUrl.ashx?Username=" . urlencode(env('TREZ_USERNAME')) . "&Password=" . urlencode(env('TREZ_PASSWORD')) . "&PhoneNumber=" . urlencode(env('TREZ_NUMBER')) . "&MessageBody=" . urlencode($content) . "&RecNumber=" . urlencode($mobile) . "&Smsclass=1";
        if (file_get_contents($urlSMS)) {
            $mobilesArray[] = $mobile;
            $smsTotalCount++;
        }

        $create = Sms::create([
            'receivers' => $mobilesArray,
            'count' => $smsTotalCount,
            'body' => $content,
            'sent_at' => Carbon::now(),
            'user_id' => auth()->id()
        ]);
        if ($create) {
            return $this->apiController->respondCreated('با موفقیت پیامک ارسال شد');
        } else {
            return $this->apiController->respondInternalError();
        }
    }


    public function destroy(Request $request)
    {
        $failure = null;
        if ($request->input('failure') !== null)
            $failure = FailureCategory::find($request->input('failure'))->id;

        $data = Automation::findorFail($request->input('id'));
        $data->update([
            'status' => Automation::FAILURE,
            'failure_id' => $failure,
        ]);
        return $this->apiController->respondDeleted('با موفقیت حذف شد', $data);
    }

    public function show(Automation $automation)
    {
        $automation->load(['homeSurvey.gasCoolers', 'industrialSurvey.mainConsumers', 'orders.product']);
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

        $survey = $request->input('survey') ? json_decode($request->input('survey'), true) : null;

        DB::transaction(function () use ($request, $automation, $customer, $survey, $expert) {
            $automation->update([
                'date' => Verta::parse($request->input('date'))->datetime(),
                'customer_id' => $customer->id,
                'expert_id' => $expert->id,
                'description' => $request->input('description'),
                'survey_type' => $request->input('surveyType'),
            ]);

            $this->saveSurvey($automation, $request->input('surveyType'), $survey, $request);
        });

        return $this->apiController->respondUpdated();
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
        if ($data) {
            if ($data->delete()) {
                return $this->apiController->respondDeleted();
            } else {
                return $this->apiController->respondInternalError();
            }
        } else {
            return $this->apiController->respondNotFound();
        }

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
