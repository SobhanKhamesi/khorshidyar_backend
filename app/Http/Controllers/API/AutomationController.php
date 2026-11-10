<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\AutomationCustomerSurvey;
use App\Models\AutomationHomeSurvey;
use App\Models\AutomationIndustrialSurvey;
use App\Models\AutomatonComment;
use App\Models\Customer;
use App\Models\FailureCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\ProjectProfitService;
use App\Services\SmsService;
use App\Support\Access;
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

        // ادمین و مدیر فروش همه؛ سایرین فقط اتوماسیون‌هایی که ثبت کرده یا کارشناس فنی آن بوده‌اند (در همه‌ی مراحل)
        Access::scopeAutomations($query);

        // در لیست فروش‌های موفق، وضعیت ارجاع به سرویس نصب هم نمایش داده می‌شود
        if ($status === Automation::SUCCESS)
            $query->with(['installationService' => fn($q) => $q->withoutEagerLoads()]);

        $list = $query->get();

        // سود ناخالص و سود نهایی فقط برای مدیر
        if ($this->canSeeProfit())
            $list->each(function (Automation $automation) {
                $automation->makeVisible(Automation::PROFIT_FIELDS);
                $automation->installationService?->makeVisible('final_profit');
            });

        return $this->apiController->respondData($list);
    }


    public function store(Request $request)
    {
        $this->validate($request, [
            'date' => 'required',
            'customer' => 'required',
            'expert' => 'required',
            'survey' => 'nullable|string',
            'panel_photo' => 'nullable|image',
            'required_photos.*' => 'nullable|image',
        ], [
            'date.required' => 'تاریخ را وارد نمایید',
            'customer.required' => 'مشتری را انتخاب نمایید',
            'expert.required' => 'کارشناس فنی را انتخاب نمایید',
        ]);

        Access::ensureSales();
        $customer = Customer::findOrFail($request->input('customer'));
        Access::ensureCustomer($customer);
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
        $this->validate($request, array_merge([
            'id' => 'required',
            'contract_items' => 'nullable|array',
        ], self::FINAL_PRICE_RULE, self::PAYMENT_RULES), self::FINAL_PRICE_MESSAGES);

        $automation = $this->findAutomation($request->input('id'), true);
        $nextStatus = $automation->status + 1;

        DB::transaction(function () use ($request, $automation, $nextStatus) {
            $automation->contractItems()->delete();
            foreach ($request->input('contract_items', []) as $item) {
                $automation->contractItems()->create([
                    'title' => $item['title'],
                    'amount' => $item['amount'] ?? 0,
                ]);
            }

            $this->savePaymentsAndCheques($automation, $request->input('payments', []), $request->input('cheques', []));

            // مبلغ کل قرارداد به‌صورت دستی وارد می‌شود
            $automation->update(['status' => $nextStatus, 'final_price' => $request->input('final_price')]);
        });

        return $this->apiController->respondCreated();
    }

    // مبلغ نهایی (پیش فاکتور / قرارداد) به‌صورت دستی وارد می‌شود و از جمع اقلام محاسبه نمی‌شود
    private const FINAL_PRICE_RULE = ['final_price' => 'required|numeric|min:0'];
    private const FINAL_PRICE_MESSAGES = [
        'final_price.required' => 'مبلغ نهایی را وارد نمایید',
        'final_price.numeric' => 'مبلغ نهایی باید عدد باشد',
    ];
    private const PAYMENT_RULES = [
        'payments' => 'nullable|array',
        'payments.*.amount' => 'required|numeric|min:0',
        'cheques' => 'nullable|array',
        'cheques.*.amount' => 'required|numeric|min:0',
    ];

    private function savePaymentsAndCheques(Automation $automation, ?array $payments, ?array $cheques): void
    {
        $automation->payments()->delete();
        foreach ($payments ?? [] as $payment) {
            $automation->payments()->create([
                'method' => $payment['method'] ?? null,
                'amount' => $payment['amount'],
                'date' => !empty($payment['date']) ? Verta::parse($payment['date'])->datetime() : null,
            ]);
        }

        $automation->cheques()->delete();
        foreach ($cheques ?? [] as $cheque) {
            $automation->cheques()->create([
                'number' => $cheque['number'] ?? null,
                'bank_name' => $cheque['bank_name'] ?? null,
                'amount' => $cheque['amount'],
                'due_date' => !empty($cheque['due_date']) ? Verta::parse($cheque['due_date'])->datetime() : null,
            ]);
        }
    }

    /**
     * اصلاحیه فاکتور و روش پرداخت؛ در هر مرحله‌ای از اتوماسیون (حتی فروش موفق یا ناموفق) قابل انجام است.
     * وضعیت اتوماسیون تغییر نمی‌کند و یک کامنت خودکار برای سابقه ثبت می‌شود.
     * فقط بخش‌های ارسال شده تغییر می‌کنند: اقلام (orders)، مبلغ نهایی (final_price) و پرداختی‌ها/چک‌ها (payments/cheques)؛
     * تا دیالوگ نمایش پیش فاکتور و دیالوگ پرداختی‌ها بتوانند هر کدام فقط بخش خود را ویرایش کنند.
     */
    public function amend(Request $request)
    {
        $this->validate($request, array_merge([
            'id' => 'required',
            'orders' => 'nullable|array',
            'orders.*.orderProductId' => 'required|exists:products,id',
            'orders.*.orderCount' => 'required|numeric|min:1',
            'orders.*.orderPrice' => 'required|numeric|min:0',
            'orders.*.orderPurchasePrice' => 'nullable|numeric|min:0',
            'final_price' => 'required_with:orders|nullable|numeric|min:0',
            'invoice_description' => 'nullable|string',
        ], self::PAYMENT_RULES), self::FINAL_PRICE_MESSAGES + [
            'final_price.required_with' => 'مبلغ نهایی را وارد نمایید',
        ]);

        $automation = $this->findAutomation($request->input('id'));
        // اصلاحیه‌ی فروش موفق فقط برای مدیر و مدیر فروش
        if ((int)$automation->status === Automation::SUCCESS)
            Access::ensure(Access::canManageSalesStages(), 'اصلاحیه‌ی فروش موفق فقط برای مدیر مجاز است');
        $oldPrice = $automation->final_price;
        $invoiceChanged = $request->has('orders') || $request->filled('final_price');
        $paymentsChanged = $request->has('payments') || $request->has('cheques');
        // توضیحات پیش فاکتور (زیر اقلام در پرینت)؛ فقط وقتی ارسال شود و با مقدار فعلی فرق کند
        $descriptionChanged = $request->has('invoice_description')
            && (string)$request->input('invoice_description') !== (string)$automation->invoice_description;

        if (!$invoiceChanged && !$paymentsChanged && !$descriptionChanged) {
            if ($request->has('invoice_description'))
                return $this->apiController->respondUpdated('تغییری ثبت نشد');
            return $this->apiController->respondError('تغییری برای ثبت ارسال نشده است');
        }

        DB::transaction(function () use ($request, $automation, $oldPrice, $invoiceChanged, $paymentsChanged, $descriptionChanged) {
            if ($request->has('orders'))
                $this->addOrder($request->input('orders') ?? [], $automation);

            if ($paymentsChanged)
                $this->savePaymentsAndCheques($automation, $request->input('payments', []), $request->input('cheques', []));

            if ($request->filled('final_price'))
                $automation->update(['final_price' => $request->input('final_price')]);

            if ($descriptionChanged)
                $automation->update(['invoice_description' => $request->input('invoice_description')]);

            // قرارداد تایید شده‌ای که هنوز امضا نشده، با تغییر فاکتور نیاز به بازبینی و تایید مجدد دارد
            if ($invoiceChanged || $paymentsChanged)
                $automation->contract?->invalidateApproval();

            $parts = [];
            if ($invoiceChanged)
                $parts[] = 'مبلغ نهایی: ' . number_format((float)$oldPrice) . ' ← '
                    . number_format((float)$automation->final_price) . ' تومان';
            if ($paymentsChanged)
                $parts[] = 'پرداختی‌ها و چک‌ها ویرایش شد';
            if ($descriptionChanged)
                $parts[] = 'توضیحات پیش فاکتور ویرایش شد';

            AutomatonComment::create([
                'automation_id' => $automation->id,
                'user_id' => auth()->id(),
                'content' => 'اصلاحیه فاکتور / روش پرداخت ثبت شد. ' . implode(' - ', $parts)
                    . ($request->filled('reason') ? "\nعلت: " . $request->input('reason') : ''),
            ]);
        });

        // سود ناخالص اولیه ثابت می‌ماند؛ فقط سود نهایی سرویس نصب (که بر مبنای مبلغ نهایی فعلی است) به‌روز می‌شود
        ProjectProfitService::refreshById($automation->installationService?->id);

        return $this->apiController->respondUpdated('اصلاحیه با موفقیت ثبت شد');
    }

    // بازگرداندن فروش ناموفق به مرحله درخواست
    public function restore(Request $request)
    {
        $automation = $this->findAutomation($request->input('id'), true);
        if ($automation->status !== Automation::FAILURE)
            return $this->apiController->respondError('فقط فروش‌های ناموفق قابل بازگردانی هستند');

        $automation->update(['status' => Automation::REQUEST, 'failure_id' => null]);

        return $this->apiController->respondUpdated('درخواست به کارتابل درخواست‌ها بازگردانده شد');
    }


    // وجود محصولات قبلاً با قانون exists در validate بررسی شده است
    // قیمت خرید هر قلم در همین فاکتور ذخیره می‌شود (پیش‌فرض: قیمت خرید فعلی محصول) تا تغییر بعدی قیمت محصول روی آن اثر نگذارد
    public function addOrder(array $orders, Automation $create)
    {
        $finalPrice = 0;
        $products = Product::withoutEagerLoads()
            ->whereIn('id', array_column($orders, 'orderProductId'))
            ->get(['id', 'kind', 'service_nature', 'purchase_price'])
            ->keyBy('id');

        $create->orders()->delete();
        foreach ($orders as $order) {
            $product = $products->get($order['orderProductId']);
            $price = (int)round((float)$order['orderPrice']);
            $purchasePrice = isset($order['orderPurchasePrice']) && $order['orderPurchasePrice'] !== ''
                ? (int)round((float)$order['orderPurchasePrice'])
                : ($product?->purchase_price !== null ? (int)$product->purchase_price : null);

            // خدمت فقط یک مبلغ دارد:
            // خدمت هزینه (مثل حمل و نقل) ← همان مبلغ هم هزینه‌ی ما (قیمت خرید) است و هم از مشتری دریافت می‌شود (قیمت فروش)،
            // پس در جمع فاکتور، پرینت و قرارداد مشتری می‌آید و سود این ردیف صفر است
            // خدمت دریافتی ← فقط مبلغ دریافتی (قیمت فروش) و برای ما هزینه‌ای ندارد
            if ($product?->isService()) {
                if ($product->isCostService()) {
                    $purchasePrice = $purchasePrice ?: $price;
                    $price = (int)$purchasePrice;
                } else {
                    $purchasePrice = 0;
                }
            }

            $create->orders()->create([
                'product_id' => $order['orderProductId'],
                'price' => $price,
                'purchase_price' => $purchasePrice,
                'count' => $order['orderCount'],
            ]);

            $finalPrice += $order['orderCount'] * $price;
        }

        return $finalPrice;
    }

    // ثبت سریع پیش فاکتور: فقط با انتخاب مشتری، یک اتوماسیون جدید مستقیماً در مرحله «پیش فاکتور» ساخته می‌شود
    public function quickStore(Request $request)
    {
        $this->validate($request, array_merge([
            'customer' => 'required',
            'orders' => 'required|array|min:1',
            'orders.*.orderProductId' => 'required|exists:products,id',
            'orders.*.orderCount' => 'required|numeric|min:1',
            'orders.*.orderPrice' => 'required|numeric|min:0',
            'orders.*.orderPurchasePrice' => 'nullable|numeric|min:0',
        ], self::FINAL_PRICE_RULE), array_merge([
            'customer.required' => 'مشتری را انتخاب نمایید',
            'orders.required' => 'حداقل یک آیتم پیش فاکتور وارد کنید',
        ], self::FINAL_PRICE_MESSAGES));

        Access::ensureSales();
        $customer = Customer::findOrFail($request->input('customer'));
        Access::ensureCustomer($customer);
        $orders = $request->input('orders');
        // در ثبت سریع، توضیحات وارد شده متعلق به پیش فاکتور است
        $invoiceDescription = $request->input('invoice_description', $request->input('description'));
        $referrerPercent = $this->resolveReferrerPercent($request, $customer);

        $finalPrice = $request->input('final_price');

        $automation = DB::transaction(function () use ($customer, $orders, $invoiceDescription, $referrerPercent, $finalPrice) {
            $invoice_number = Automation::nextInvoiceNumber();

            $automation = Automation::create([
                'status' => Automation::PRE_INVOICE,
                'user_id' => auth()->id(),
                'customer_id' => $customer->id,
                'referrer_percent' => $referrerPercent,
                'invoice_description' => $invoiceDescription,
            ]);

            if ($customer->status === Customer::LEAD)
                $customer->update(['status' => Customer::OPPORTUNITY]);

            $this->addOrder($orders, $automation);

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
        $this->validate($request, array_merge([
            'id' => 'required',
            'orders' => 'required|array|min:1',
            'orders.*.orderProductId' => 'required|exists:products,id',
            'orders.*.orderCount' => 'required|numeric|min:1',
            'orders.*.orderPrice' => 'required|numeric|min:0',
            'orders.*.orderPurchasePrice' => 'nullable|numeric|min:0',
        ], self::FINAL_PRICE_RULE), array_merge([
            'orders.required' => 'حداقل یک آیتم پیش فاکتور وارد کنید',
        ], self::FINAL_PRICE_MESSAGES));

        // درخواست ← پیش فاکتور: صاحب اتوماسیون؛ پیش فاکتور ← مذاکره: فقط مدیر و مدیر فروش
        $automation = Automation::findOrFail($request->input('id'));
        $automation = $this->findAutomation($automation->id, (int)$automation->status !== Automation::REQUEST);
        $nextStatus = $automation->status + 1;
        $orders = $request->input('orders');
        // توضیحات پیش فاکتور جدا از توضیحات درخواست ذخیره می‌شود و توضیحات درخواست دست نمی‌خورد
        $invoiceDescription = $request->input('invoice_description');
        $referrerPercent = $this->resolveReferrerPercent($request, $automation->customer, $automation);

        $finalPrice = $request->input('final_price');

        DB::transaction(function () use ($automation, $orders, $nextStatus, $invoiceDescription, $referrerPercent, $finalPrice) {
            $invoice_number = Automation::nextInvoiceNumber();
            $this->addOrder($orders, $automation);

            $automation->update([
                'status' => $nextStatus,
                'final_price' => $finalPrice,
                'invoice_number' => $invoice_number,
                'invoice_description' => $invoiceDescription,
                'referrer_percent' => $referrerPercent,
            ]);

            if ($automation->customer->status === Customer::LEAD)
                $automation->customer->update(['status' => Customer::OPPORTUNITY]);
        });

        return $this->apiController->respondCreated();
    }

    public function success(Request $request)
    {
        $automation = $this->findAutomation($request->input('id'), true);

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
        Access::ensureAutomation($automation);
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
        $failure = $request->input('failure') !== null
            ? FailureCategory::findOrFail($request->input('failure'))
            : null;

        $data = $this->findAutomation($request->input('id'));
        $wasSuccess = (int)$data->status === Automation::SUCCESS;
        if ($wasSuccess)
            Access::ensure(Access::canManageSalesStages(), 'تبدیل فروش موفق به ناموفق فقط برای مدیر مجاز است');

        DB::transaction(function () use ($request, $data, $failure, $wasSuccess) {
            $data->update([
                'status' => Automation::FAILURE,
                'failure_id' => $failure?->id,
            ]);

            if (!$wasSuccess)
                return;

            // تبدیل فروش موفق به ناموفق: اگر مشتری فروش موفق دیگری ندارد، از «مشتری» به «فرصت» برمی‌گردد
            $customer = $data->customer;
            if ($customer && (int)$customer->status === Customer::CUSTOMER
                && !$customer->automations()->where('status', Automation::SUCCESS)->exists())
                $customer->update(['status' => Customer::OPPORTUNITY]);

            AutomatonComment::create([
                'automation_id' => $data->id,
                'user_id' => auth()->id(),
                'content' => 'فروش موفق به ناموفق تبدیل شد.'
                    . ($failure ? "\nدلیل شکست: " . $failure->title : '')
                    . ($request->filled('reason') ? "\nتوضیحات: " . $request->input('reason') : ''),
            ]);
        });

        return $this->apiController->respondDeleted('با موفقیت حذف شد', $data);
    }

    public function show(Automation $automation)
    {
        Access::ensureAutomation($automation);
        // orders.product از قبل در $with مدل بارگذاری شده است
        $automation->load(['homeSurvey.gasCoolers', 'industrialSurvey.mainConsumers', 'customerSurvey']);
        if ($this->canSeeProfit())
            $automation->makeVisible(Automation::PROFIT_FIELDS);

        return $this->apiController->respondData($automation);
    }

    /**
     * اتوماسیون قابل دسترس کاربر؛ $managerOnly برای مراحلی که فقط مدیر و مدیر فروش انجام می‌دهند
     * (پیش فاکتور ← مذاکره، تایید قرارداد، فروش موفق، بازگردانی فروش ناموفق)
     */
    private function findAutomation($id, bool $managerOnly = false): Automation
    {
        $automation = Automation::findOrFail($id);
        Access::ensureAutomation($automation);
        if ($managerOnly)
            Access::ensure(Access::canManageSalesStages(), 'این مرحله فقط توسط مدیر یا مدیر فروش انجام می‌شود');

        return $automation;
    }

    private function canSeeProfit(): bool
    {
        return Access::can('profit.view');
    }

    public function update(Request $request)
    {
        $this->validate($request, [
            'date' => 'required',
            'customer' => 'required',
            'expert' => 'required',
            'surveyType' => 'nullable|in:1,2,3',
            'survey' => 'nullable|string',
            'panel_photo' => 'nullable|image',
            'required_photos.*' => 'nullable|image',
        ], [
            'date.required' => 'تاریخ بازدید را وارد نمایید',
            'customer.required' => 'مشتری را انتخاب نمایید',
            'expert.required' => 'کارشناس فنی را انتخاب نمایید',
        ]);

        $automation = $this->findAutomation($request->input('id'));
        $customer = Customer::findOrFail($request->input('customer'));
        Access::ensureCustomer($customer);
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

        $automation = $this->findAutomation($request->input('id'));
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
        $leaveId = $this->findAutomation($request->input('id'))->id;
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

        $ticket = $this->findAutomation($request->input('id'));

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
        // حذف فقط برای مدیر
        Access::ensureCan('sales.delete');

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

        } elseif ($surveyType == Automation::CUSTOMER && $survey) {

            // فرم ثبت شده از مشتری؛ همان سوالات فرم اولیه که در سامانه قابل ویرایش است
            AutomationCustomerSurvey::updateOrCreate(
                ['automation_id' => $automation->id],
                $this->mapCustomerSurvey($survey)
            );
        }
    }

    private function mapCustomerSurvey(array $s): array
    {
        $data = [];
        foreach (AutomationCustomerSurvey::keys() as $key) {
            $value = $s[$key] ?? null;
            $data[$key] = $value === '' ? null : $value;
        }
        $data['surface_material'] = array_values((array)($data['surface_material'] ?? []));

        return $data;
    }


}
