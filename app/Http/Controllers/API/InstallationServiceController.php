<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\Controller;
use App\Models\AfterSalesService;
use App\Models\Automation;
use App\Models\Customer;
use App\Models\InstallationService;
use App\Models\InstallationServiceFinanceItem;
use App\Models\InstallationServiceItem;
use App\Models\InstallerAppointment;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProjectEarning;
use App\Services\AfterSalesSmsNotifier;
use App\Services\InstallationSmsNotifier;
use App\Services\ProjectProfitService;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * گردش کار سرویس نصب:
 * فروش موفق ← ارجاع مدیر به نصاب (با اقلام پیش‌نیاز + پیامک به نصاب و مشتری)
 * ← ثبت اقلام مصرفی توسط نصاب و ارجاع به مدیر ← ارسال برای کارشناس فنی
 * ← تایید کارشناس / برگشت به نصاب برای رفع عیوب (و ارسال مجدد به همان کارشناس)
 * ← تکمیل نهایی (و ایجاد ردیف خدمات پس از فروش)
 */
class InstallationServiceController extends Controller
{
    private ApiController $apiController;
    private InstallationSmsNotifier $notifier;

    private const RELATIONS = [
        'customer', 'automation', 'installerAppointments',
        'requiredItems.product', 'usedItems.product', 'histories',
    ];

    public function __construct(ApiController $apiController, InstallationSmsNotifier $notifier)
    {
        $this->apiController = $apiController;
        $this->notifier = $notifier;
    }

    public function list()
    {
        $query = InstallationService::query()
            ->with(self::RELATIONS)
            ->orderbyDesc('created_at');

        // نصاب فقط سرویس‌هایی را می‌بیند که به خودش ارجاع شده
        if ($this->isInstaller())
            $query->whereHas('activeAppointments', fn($q) => $q->where('user_id', auth()->id()))
                ->where('stage', '!=', InstallationService::STAGE_DRAFT);

        return $this->apiController->respondData($this->present($query->get()));
    }

    // کارتابل سرویس نصب نصاب (کارهایی که الان منتظر اقدام او هستند)
    public function myCartable()
    {
        $list = InstallationService::query()
            ->with(self::RELATIONS)
            ->whereHas('activeAppointments', fn($q) => $q->where('user_id', auth()->id()))
            ->whereIn('stage', [InstallationService::STAGE_ASSIGNED, InstallationService::STAGE_NEEDS_FIX])
            ->orderbyDesc('updated_at')
            ->get();

        return $this->apiController->respondData($this->present($list));
    }

    public function customerAutomations(Request $request, $customerId)
    {
        Customer::findOrFail($customerId);

        // فروشی که قبلاً به سرویس نصب ارجاع شده دوباره قابل انتخاب نیست (به جز سرویس در حال ویرایش)
        $automations = Automation::where('customer_id', $customerId)
            ->where('status', Automation::SUCCESS)
            ->whereDoesntHave('installationService', fn($q) => $q->when(
                $request->query('except'),
                fn($q, $except) => $q->where('id', '!=', $except)
            ))
            ->orderByDesc('visit_date')
            ->get();

        return $this->apiController->respondData($automations);
    }

    public function customerInstallationServices($customerId)
    {
        Customer::findOrFail($customerId);

        $list = InstallationService::where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->get(['id', 'status', 'stage', 'survey_date', 'created_at']);

        return $this->apiController->respondData($list->values());
    }

    public function store(Request $request)
    {
        if ($this->isInstaller())
            return $this->apiController->respondForbidden();

        $refer = $request->input('action') === 'refer';
        $this->validatePayload($request, $refer);

        try {
            [$service, $changed] = DB::transaction(function () use ($request, $refer) {
                $stage = $refer ? InstallationService::STAGE_ASSIGNED : InstallationService::STAGE_DRAFT;

                $service = InstallationService::create($this->extractServiceAttributes($request) + [
                        'status' => InstallationService::TEMPORARY,
                        'stage' => $stage,
                    ]);
                $changed = $this->syncInstallers($service, $request->input('installers', []));
                $this->syncItems($service, InstallationServiceItem::TYPE_REQUIRED, $request->input('items', []));
                $service->logStage($stage);

                // خدمات هزینه‌دار فاکتور فروش (مثل حمل و نقل) به هزینه‌های جانبی پروژه منتقل می‌شوند
                ProjectProfitService::importInvoiceServices($service);
                ProjectProfitService::syncWages($service);
                ProjectProfitService::refresh($service);

                return [$service, $changed];
            });
        } catch (\Exception $e) {
            return $this->apiController->respondError($e->getMessage());
        }

        $warnings = $refer ? $this->notifier->notifyReferral($service, $changed) : [];

        return $this->apiController->respondCreated(
            $this->messageWithWarnings($refer ? 'سرویس نصب ثبت و به نصاب ارجاع شد' : 'با موفقیت ایجاد شد', $warnings),
            $this->present($service->load(self::RELATIONS))
        );
    }

    public function update(Request $request, $id)
    {
        if ($this->isInstaller())
            return $this->apiController->respondForbidden();

        $service = InstallationService::findOrFail($id);

        if ($service->stage === InstallationService::STAGE_COMPLETED)
            return $this->apiController->respondError('سرویس نصب تکمیل نهایی شده و قابل ویرایش نیست');

        $wasReferred = $service->stage !== InstallationService::STAGE_DRAFT;
        $refer = !$wasReferred && $request->input('action') === 'refer';

        // پس از ارجاع، نصاب و اقلام پیش‌نیاز همیشه باید کامل باشند
        $this->validatePayload($request, $wasReferred || $refer, $service->id);

        try {
            $changed = DB::transaction(function () use ($request, $service, $refer) {
                $service->update($this->extractServiceAttributes($request));
                $changed = $this->syncInstallers($service, $request->input('installers', []));
                $this->syncItems($service, InstallationServiceItem::TYPE_REQUIRED, $request->input('items', []));

                if ($refer) {
                    $service->update(['stage' => InstallationService::STAGE_ASSIGNED]);
                    $service->logStage(InstallationService::STAGE_ASSIGNED);
                }

                // با تغییر فروش مرتبط، خدمات منتقل شده از فاکتور قبلی با فاکتور جدید جایگزین می‌شوند
                if ($service->wasChanged(['automation_id', 'purchase_source']))
                    ProjectProfitService::importInvoiceServices($service);
                ProjectProfitService::syncWages($service);
                ProjectProfitService::refresh($service);

                return $changed;
            });
        } catch (\Exception $e) {
            return $this->apiController->respondError($e->getMessage());
        }

        // فقط برای نصاب‌هایی که تازه ارجاع شده یا زمان نصبشان تغییر کرده پیامک ارسال می‌شود
        $warnings = ($wasReferred || $refer) && $changed->isNotEmpty()
            ? $this->notifier->notifyReferral($service, $changed)
            : [];

        return $this->apiController->respondUpdated(
            $this->messageWithWarnings($refer ? 'سرویس نصب به نصاب ارجاع شد' : 'با موفقیت ویرایش شد', $warnings),
            $this->present($service->load(self::RELATIONS))
        );
    }

    public function show($id)
    {
        $service = InstallationService::with(self::RELATIONS)->findOrFail($id);

        if ($this->isInstaller() && !$service->isAssignedTo(auth()->id()))
            return $this->apiController->respondForbidden();

        return $this->apiController->respondData($this->present($service));
    }

    public function destroy($id)
    {
        if ($this->isInstaller())
            return $this->apiController->respondForbidden();

        $service = InstallationService::findOrFail($id);

        DB::transaction(function () use ($service) {
            $service->installerAppointments()->update(['status' => InstallerAppointment::CANCELED]);
            $service->installationServiceItems()->delete();
            $service->histories()->delete();
            $service->delete();
        });

        return $this->apiController->respondDeleted();
    }

    // ================= گردش کار =================

    /**
     * نصاب در پایان نصب (یا پس از رفع عیوب) اقلام مصرف شده را ثبت و ارجاع می‌دهد.
     * پایان نصب ← به مدیر برای ارسال به کارشناس / پس از رفع عیوب ← مستقیم به همان کارشناس.
     */
    public function installerSubmit(Request $request, $id)
    {
        $service = InstallationService::findOrFail($id);

        if ($this->isInstaller() && !$service->isAssignedTo(auth()->id()))
            return $this->apiController->respondForbidden();

        if (!in_array($service->stage, [InstallationService::STAGE_ASSIGNED, InstallationService::STAGE_NEEDS_FIX], true))
            return $this->apiController->respondError('این سرویس نصب در مرحله‌ی ثبت توسط نصاب نیست');

        $this->validate($request, [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
        ], [
            'items.required' => 'اقلام مصرف شده در این پروژه را وارد کنید',
            'items.min' => 'اقلام مصرف شده در این پروژه را وارد کنید',
            'items.*.product_id.required' => 'محصول ردیف اقلام مصرفی را انتخاب کنید',
            'items.*.quantity.required' => 'مقدار ردیف اقلام مصرفی را وارد کنید',
        ]);

        $nextStage = $service->stage === InstallationService::STAGE_NEEDS_FIX
            ? InstallationService::STAGE_UNDER_REVIEW
            : InstallationService::STAGE_INSTALLER_SUBMITTED;

        DB::transaction(function () use ($request, $service, $nextStage) {
            $this->syncItems($service, InstallationServiceItem::TYPE_USED, $request->input('items'));
            $service->update(['stage' => $nextStage]);
            $service->logStage($nextStage, $request->input('description'));
            ProjectProfitService::refresh($service);
        });

        return $this->apiController->respondUpdated(
            $nextStage === InstallationService::STAGE_UNDER_REVIEW
                ? 'رفع عیوب ثبت و برای بررسی مجدد به کارشناس فنی ارسال شد'
                : 'اقلام مصرفی ثبت و به مدیر ارجاع شد',
            $this->present($service->load(self::RELATIONS))
        );
    }

    // مدیر پروژه‌ی تکمیل شده توسط نصاب را برای بررسی به کارشناس فنی می‌فرستد
    public function sendToReview(Request $request, $id)
    {
        if ($this->isInstaller())
            return $this->apiController->respondForbidden();

        $service = InstallationService::findOrFail($id);

        if ($service->stage !== InstallationService::STAGE_INSTALLER_SUBMITTED)
            return $this->apiController->respondError('ابتدا نصاب باید اقلام مصرفی را ثبت و پروژه را ارجاع دهد');

        $this->validate($request, [
            'expert_id' => ['required', Rule::exists('users', 'id')->whereIn('role', [User::KARSHENAS_FANI, User::ADMIN])],
            'description' => 'nullable|string',
        ], [
            'expert_id.required' => 'کارشناس فنی را انتخاب کنید',
            'expert_id.exists' => 'کارشناس فنی انتخاب شده معتبر نیست',
        ]);

        DB::transaction(function () use ($request, $service) {
            $service->update([
                'technical_expert_id' => $request->input('expert_id'),
                'stage' => InstallationService::STAGE_UNDER_REVIEW,
            ]);
            $service->logStage(InstallationService::STAGE_UNDER_REVIEW, $request->input('description'));
            // دستمزد بررسی کارشناس فنی (در صورت تعریف در تنظیمات دستمزد)
            ProjectProfitService::syncWages($service);
        });

        return $this->apiController->respondUpdated('برای بررسی به کارشناس فنی ارسال شد', $this->present($service->load(self::RELATIONS)));
    }

    // کارشناس فنی پروژه را تایید می‌کند یا برای رفع عیوب به نصاب برمی‌گرداند
    public function review(Request $request, $id)
    {
        $service = InstallationService::findOrFail($id);

        if ($service->stage !== InstallationService::STAGE_UNDER_REVIEW)
            return $this->apiController->respondError('این سرویس نصب در مرحله‌ی بررسی کارشناس فنی نیست');

        if (!$this->isAdmin() && $service->technical_expert_id !== auth()->id())
            return $this->apiController->respondForbidden();

        $this->validate($request, [
            'result' => ['required', Rule::in(['approve', 'reject'])],
            'description' => 'required_if:result,reject|nullable|string',
        ], [
            'description.required_if' => 'عیوبی که نصاب باید برطرف کند را وارد کنید',
        ]);

        $approved = $request->input('result') === 'approve';
        $nextStage = $approved ? InstallationService::STAGE_APPROVED : InstallationService::STAGE_NEEDS_FIX;

        DB::transaction(function () use ($request, $service, $nextStage) {
            $service->update(['stage' => $nextStage]);
            $service->logStage($nextStage, $request->input('description'));
        });

        return $this->apiController->respondUpdated(
            $approved ? 'پروژه تایید شد' : 'پروژه برای رفع عیوب به نصاب برگردانده شد',
            $this->present($service->load(self::RELATIONS))
        );
    }

    // تکمیل نهایی پس از تایید کارشناس؛ ردیف خدمات پس از فروش این پروژه ایجاد می‌شود
    public function complete(Request $request, $id)
    {
        if ($this->isInstaller())
            return $this->apiController->respondForbidden();

        $service = InstallationService::findOrFail($id);

        if ($service->stage !== InstallationService::STAGE_APPROVED)
            return $this->apiController->respondError('تکمیل نهایی فقط پس از تایید کارشناس فنی امکان‌پذیر است');

        $afterSales = DB::transaction(function () use ($request, $service) {
            $service->update([
                'stage' => InstallationService::STAGE_COMPLETED,
                'status' => InstallationService::FINAL,
                'completed_at' => now(),
            ]);
            $service->installerAppointments()
                ->where('status', InstallerAppointment::RESERVED)
                ->update(['status' => InstallerAppointment::DONE]);
            $service->logStage(InstallationService::STAGE_COMPLETED, $request->input('description'));
            ProjectProfitService::refresh($service);

            if (!$service->afterSalesServices()->exists()) {
                return AfterSalesService::create([
                    'customer_id' => $service->customer_id,
                    'installation_service_id' => $service->id,
                    // مسئول خدمات پس از فروش، نصاب خود پروژه است
                    'responsible_user_id' => $service->projectInstaller()?->id,
                    'problem' => 'پیگیری خدمات پس از فروش پروژه‌ی نصب شده',
                    'request_date' => now()->format('Y-m-d'),
                    'follow_up_date' => now()->addDays(AfterSalesService::REMINDER_AFTER_DAYS)->format('Y-m-d'),
                    'status' => AfterSalesService::STATUS_PENDING,
                    'source' => AfterSalesService::SOURCE_INSTALLATION_REMINDER,
                ]);
            }

            return null;
        });

        $message = 'سرویس نصب تکمیل نهایی شد و ردیف خدمات پس از فروش ایجاد شد';

        // پیامک ثبت خدمات پس از فروش (مشتری: نام/شماره نصاب و تاریخ مراجعه، نصاب: کار جدید در کارتابل)
        if ($afterSales) {
            $warnings = app(AfterSalesSmsNotifier::class)->notifyRegistered($afterSales);
            $message .= $warnings ? '. ' . implode(' - ', $warnings) : ' و پیامک برای مشتری و نصاب ارسال شد';
        }

        return $this->apiController->respondUpdated($message, $this->present($service->load(self::RELATIONS)));
    }

    // ================= سود و زیان پروژه (فقط مدیر) =================

    public function finance($id)
    {
        if (!$this->isAdmin())
            return $this->apiController->respondForbidden();

        $service = InstallationService::with('customer')->findOrFail($id);

        return $this->apiController->respondData(ProjectProfitService::breakdown($service) + [
                'id' => $service->id,
                'customer_name' => $service->customer?->fullName ?? $service->customer?->name,
                'stage' => $service->stage,
                'stage_label' => $service->stage_label,
                // افرادی که در پروژه کار کرده‌اند (برای ثبت دستمزد)
                'participants' => $this->participantOptions($service),
            ]);
    }

    // ثبت هزینه یا دریافتی جانبی؛ می‌تواند یکی از خدمات تعریف شده در محصولات باشد یا عنوان آزاد
    public function storeFinanceItem(Request $request, $id)
    {
        if (!$this->isAdmin())
            return $this->apiController->respondForbidden();

        $service = InstallationService::findOrFail($id);
        $attributes = $this->validateFinanceItem($request);

        InstallationServiceFinanceItem::create($attributes + [
                'installation_service_id' => $service->id,
                'source' => InstallationServiceFinanceItem::SOURCE_MANUAL,
                'user_id' => auth()->id(),
            ]);

        return $this->apiController->respondCreated('با موفقیت ثبت شد');
    }

    public function updateFinanceItem(Request $request, $id, $itemId)
    {
        if (!$this->isAdmin())
            return $this->apiController->respondForbidden();

        $item = InstallationServiceFinanceItem::where('installation_service_id', $id)->findOrFail($itemId);
        $item->update($this->validateFinanceItem($request));

        return $this->apiController->respondUpdated('با موفقیت ویرایش شد');
    }

    public function destroyFinanceItem($id, $itemId)
    {
        if (!$this->isAdmin())
            return $this->apiController->respondForbidden();

        InstallationServiceFinanceItem::where('installation_service_id', $id)->findOrFail($itemId)->delete();

        return $this->apiController->respondDeleted();
    }

    // ویرایش قیمت خرید واحد اقلام این پروژه (قیمت در همین پروژه ذخیره می‌شود و روی محصول اثری ندارد)
    public function updateItemPrices(Request $request, $id)
    {
        if (!$this->isAdmin())
            return $this->apiController->respondForbidden();

        $service = InstallationService::findOrFail($id);

        $this->validate($request, [
            'items' => 'required|array',
            'items.*.id' => 'required|string',
            'items.*.unit_purchase_price' => 'required|numeric|min:0',
        ], [
            'items.*.unit_purchase_price.required' => 'قیمت خرید واحد را وارد کنید',
        ]);

        DB::transaction(function () use ($request, $service) {
            foreach ($request->input('items') as $row) {
                $item = $service->installationServiceItems()->find($row['id']);
                if (!$item)
                    continue;

                // قیمت یک کالا در اقلام پیش‌نیاز و مصرفی همین پروژه یکسان نگه داشته می‌شود
                $service->installationServiceItems()->where('product_id', $item->product_id)
                    ->update(['unit_purchase_price' => (int)round((float)$row['unit_purchase_price'])]);
            }
            ProjectProfitService::refresh($service);
        });

        return $this->apiController->respondUpdated('قیمت خرید اقلام ویرایش شد');
    }

    private function validateFinanceItem(Request $request): array
    {
        $this->validate($request, [
            'direction' => ['required', Rule::in([InstallationServiceFinanceItem::COST, InstallationServiceFinanceItem::INCOME])],
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('kind', Product::KIND_SERVICE)],
            'title' => 'required_without:product_id|nullable|string|max:255',
            'amount' => 'required|integer|min:1',
            'description' => 'nullable|string|max:1000',
        ], [
            'direction.required' => 'نوع (هزینه یا دریافتی) را مشخص کنید',
            'product_id.exists' => 'خدمت انتخاب شده معتبر نیست',
            'title.required_without' => 'عنوان را وارد کنید یا یک خدمت انتخاب کنید',
            'amount.required' => 'مبلغ را وارد کنید',
            'amount.integer' => 'مبلغ نامعتبر است',
            'amount.min' => 'مبلغ باید بیشتر از صفر باشد',
        ]);

        $product = $request->filled('product_id') ? Product::withoutEagerLoads()->find($request->input('product_id')) : null;

        return [
            'product_id' => $product?->id,
            'direction' => (int)$request->input('direction'),
            'title' => $request->filled('title') ? $request->input('title') : $product->name,
            'amount' => (int)$request->input('amount'),
            'description' => $request->input('description'),
        ];
    }

    // نصاب‌ها و کارشناس فنی پروژه، به همراه نوع کار، برای انتخاب در فرم دستمزد
    private function participantOptions(InstallationService $service): array
    {
        $options = [];
        $roleWork = [
            InstallationService::ROLE_PANEL => UserProjectEarning::WORK_PANEL,
            InstallationService::ROLE_WIRING => UserProjectEarning::WORK_WIRING,
            InstallationService::ROLE_COMMISSIONING => UserProjectEarning::WORK_COMMISSIONING,
        ];

        $service->activeAppointments()->whereIn('type', array_keys($roleWork))->get()
            ->each(function (InstallerAppointment $a) use (&$options, $service, $roleWork) {
                $work = $service->installer_mode === InstallationService::MODE_SINGLE ? UserProjectEarning::WORK_PROJECT : $roleWork[$a->type];
                $options[] = ['user_id' => $a->user_id, 'user_name' => $a->installer?->name, 'work_type' => $work,
                    'work_label' => UserProjectEarning::WORK_LABELS[$work]];
            });

        if ($service->technicalExpert)
            $options[] = ['user_id' => $service->technical_expert_id, 'user_name' => $service->technicalExpert->name,
                'work_type' => UserProjectEarning::WORK_REVIEW, 'work_label' => UserProjectEarning::WORK_LABELS[UserProjectEarning::WORK_REVIEW]];

        return $options;
    }

    // ================= کمکی =================

    private function isInstaller(): bool
    {
        return (int)auth()->user()->role === User::NASSAB;
    }

    private function isAdmin(): bool
    {
        return (int)auth()->user()->role === User::ADMIN;
    }

    private function messageWithWarnings(string $message, array $warnings): string
    {
        return $warnings ? $message . "\n" . implode("\n", $warnings) : $message;
    }

    private function validatePayload(Request $request, bool $isReferral, ?string $serviceId = null): void
    {
        $rules = [
            'customer_id' => 'required|exists:customers,id',
            'purchase_source' => ['required', Rule::in([InstallationService::INTERNAL, InstallationService::EXTERNAL])],
            'automation_id' => [
                'nullable',
                'exists:automation,id',
                Rule::unique('installation_services', 'automation_id')->ignore($serviceId),
            ],
            'address' => 'nullable|string',
            'contact_number' => 'nullable|string',
            'description' => 'nullable|string',
            'action' => ['nullable', Rule::in(['draft', 'refer'])],
            'installer_mode' => ['nullable', Rule::in([InstallationService::MODE_SINGLE, InstallationService::MODE_SEPARATE])],
            'installers' => 'required|array',

            'items' => $isReferral ? 'required|array|min:1' : 'nullable|array',
            'items.*.product_id' => 'required_with:items.*.quantity|exists:products,id',
            'items.*.quantity' => 'required_with:items.*.product_id|numeric|min:0.01',
        ];

        // زمان شروع و پایان هر نصاب با انتخاب خود کاربر پر می‌شود؛
        // هر سه نقش (panel/wiring/commissioning) ساختار یکسان دارند: installer_id, date, start_time, end_time
        foreach ($this->installerRoleKeys() as $roleKey) {
            $rules["installers.$roleKey"] = 'nullable|array';
            $rules["installers.$roleKey.installer_id"] = ['nullable', Rule::exists('users', 'id')->where('role', User::NASSAB)];
            $rules["installers.$roleKey.date"] = 'nullable|string';
            $rules["installers.$roleKey.start_time"] = 'nullable|date_format:H:i';
            $rules["installers.$roleKey.end_time"] = "nullable|date_format:H:i|after:installers.$roleKey.start_time";
        }

        $this->validate($request, $rules, [
            'automation_id.unique' => 'این فروش موفق قبلاً به سرویس نصب ارجاع شده است',
            'items.required' => 'اقلام پیش‌نیاز این سرویس نصب را مشخص کنید',
            'items.min' => 'اقلام پیش‌نیاز این سرویس نصب را مشخص کنید',
            'items.*.product_id.required_with' => 'محصول ردیف اقلام پیش‌نیاز را انتخاب کنید',
            'items.*.quantity.required_with' => 'مقدار ردیف اقلام پیش‌نیاز را وارد کنید',
            'installers.*.installer_id.exists' => 'نصاب انتخاب شده معتبر نیست',
        ]);

        $isSingle = $this->installerMode($request) === InstallationService::MODE_SINGLE;
        foreach ($this->activeRoleKeys($request) as $roleKey) {
            $item = $request->input("installers.$roleKey", []);
            $filled = collect(['installer_id', 'date', 'start_time', 'end_time'])->filter(fn($k) => !empty($item[$k]))->count();
            if ($filled > 0 && $filled < 4) {
                $label = $isSingle ? 'نصاب پروژه' : InstallationService::ROLE_LABELS[$roleKey];
                abort(422, "اطلاعات «{$label}» را کامل وارد کنید یا کاملاً خالی بگذارید");
            }
        }

        if ($isReferral && !$this->hasAnyInstaller($request))
            abort(422, 'برای ارجاع، حداقل یک نصاب همراه با تاریخ و ساعت نصب انتخاب کنید');
    }

    private function hasAnyInstaller(Request $request): bool
    {
        foreach ($this->activeRoleKeys($request) as $roleKey) {
            $item = $request->input("installers.$roleKey", []);
            if (!empty($item['installer_id']) && !empty($item['date']) && !empty($item['start_time']) && !empty($item['end_time']))
                return true;
        }
        return false;
    }

    private function installerRoleKeys(): array
    {
        // نوبت خدمات پس از فروش از فرم جداگانه‌ی خودش ثبت می‌شود
        return array_values(array_diff(array_keys(InstallationService::ROLE_MAP), ['after_sale']));
    }

    private function installerMode(Request $request): int
    {
        return (int)($request->input('installer_mode') ?: InstallationService::MODE_SINGLE);
    }

    // در حالت «یک نصاب برای کل پروژه» فقط نوبت نقش panel استفاده می‌شود
    private function activeRoleKeys(Request $request): array
    {
        return $this->installerMode($request) === InstallationService::MODE_SINGLE
            ? ['panel']
            : $this->installerRoleKeys();
    }

    private function extractServiceAttributes(Request $request): array
    {
        $purchaseSource = (int)$request->input('purchase_source');

        return [
            'customer_id' => $request->input('customer_id'),
            'purchase_source' => $purchaseSource,
            'installer_mode' => $this->installerMode($request),
            'automation_id' => $purchaseSource === InstallationService::INTERNAL
                ? $request->input('automation_id')
                : null,
            'address' => $request->input('address'),
            'contact_number' => $request->input('contact_number'),
            'description' => $request->input('description'),
        ];
    }

    /**
     * @return Collection<InstallerAppointment> نوبت‌هایی که تازه ایجاد شده یا نصاب/زمانشان تغییر کرده (برای ارسال پیامک)
     */
    private function syncInstallers(InstallationService $service, array $installers): Collection
    {
        $changed = collect();

        // یک نصاب برای کل پروژه: نوبت‌های سیم کشی و راه اندازی (در صورت وجود از قبل) لغو می‌شوند
        if ($service->installer_mode === InstallationService::MODE_SINGLE)
            $installers = ['panel' => $installers['panel'] ?? null];

        foreach ($this->installerRoleKeys() as $roleKey) {
            $typeValue = InstallationService::ROLE_MAP[$roleKey];
            $item = $installers[$roleKey] ?? null;
            $hasData = $item
                && !empty($item['installer_id'])
                && !empty($item['date'])
                && !empty($item['start_time'])
                && !empty($item['end_time']);

            $existing = InstallerAppointment::where('installation_service_id', $service->id)
                ->where('type', $typeValue)
                ->first();

            if (!$hasData) {
                if ($existing) {
                    $existing->update(['status' => InstallerAppointment::CANCELED]);
                }
                continue;
            }

            $attributes = [
                'user_id' => $item['installer_id'],
                'customer_id' => $service->customer_id,
                'automation_id' => $service->automation_id,
                'date' => Verta::parse($item['date'])->toCarbon()->format('Y-m-d'),
                'start_time' => $item['start_time'],
                'end_time' => $item['end_time'],
                'status' => $existing && $existing->status === InstallerAppointment::DONE
                    ? InstallerAppointment::DONE
                    : InstallerAppointment::RESERVED,
            ];

            $isChanged = !$existing
                || $existing->status === InstallerAppointment::CANCELED
                || $existing->user_id !== $attributes['user_id']
                || $existing->date?->format('Y-m-d') !== $attributes['date']
                || substr((string)$existing->start_time, 0, 5) !== $attributes['start_time'];

            $appointment = InstallerAppointment::updateOrCreate(
                ['installation_service_id' => $service->id, 'type' => $typeValue],
                $attributes
            );

            if ($isChanged)
                $changed->push($appointment);
        }

        return $changed;
    }

    /**
     * قیمت خرید واحد هر قلم هم ذخیره می‌شود: اگر همین کالا قبلاً در پروژه قیمت داشته (مثلاً مدیر ویرایش کرده) حفظ می‌شود،
     * وگرنه از فاکتور فروش یا قیمت خرید فعلی محصول خوانده می‌شود.
     */
    private function syncItems(InstallationService $service, int $type, array $items): void
    {
        $items = collect($items)->filter(fn($item) => !empty($item['product_id']) && !empty($item['quantity']));

        $existingPrices = $service->installationServiceItems()->whereNotNull('unit_purchase_price')
            ->orderBy('type', $type === InstallationServiceItem::TYPE_USED ? 'desc' : 'asc')
            ->get(['product_id', 'unit_purchase_price'])
            ->unique('product_id')
            ->pluck('unit_purchase_price', 'product_id');
        $defaultPrices = ProjectProfitService::unitPrices($service, $items->pluck('product_id')->all());

        $service->installationServiceItems()->where('type', $type)->delete();

        $items->each(fn($item) => InstallationServiceItem::create([
            'installation_service_id' => $service->id,
            'product_id' => $item['product_id'],
            'type' => $type,
            'quantity' => $item['quantity'],
            'unit_purchase_price' => $existingPrices->get($item['product_id']) ?? ($defaultPrices[$item['product_id']] ?? null),
        ]));
    }

    /**
     * اطلاعات مالی فقط برای مدیر: سود نهایی قابل مشاهده و قیمت خرید اقلام برای سایرین (مثل نصاب) مخفی می‌شود.
     */
    private function present($services)
    {
        $admin = $this->isAdmin();

        collect($services instanceof InstallationService ? [$services] : $services)
            ->each(function (InstallationService $service) use ($admin) {
                if ($admin) {
                    $service->makeVisible('final_profit');
                    return;
                }
                foreach (['requiredItems', 'usedItems'] as $relation)
                    if ($service->relationLoaded($relation))
                        $service->getRelation($relation)->each->makeHidden('unit_purchase_price');
            });

        return $services;
    }
}
