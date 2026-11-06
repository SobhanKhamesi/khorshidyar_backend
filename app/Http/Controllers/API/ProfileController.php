<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\Controller;
use App\Models\InstallationService;
use App\Models\InstallerAppointment;
use App\Models\User;
use App\Models\UserProjectEarning;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * پروفایل کاربر: اطلاعات شخصی و دریافتی هر پروژه (سرویس نصب) به تفکیک سال و ماه.
 * هر کاربر فقط پروفایل خودش را می‌بیند؛ ادمین پروفایل همه را می‌بیند و دریافتی‌ها را ثبت/تسویه می‌کند.
 */
class ProfileController extends Controller
{
    private ApiController $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function show($userId)
    {
        $user = $this->resolveUser($userId);

        return $this->apiController->respondData($this->profileData($user));
    }

    public function update(Request $request, $userId)
    {
        $user = $this->resolveUser($userId);

        $this->validate($request, [
            'name' => 'required|string|max:255',
            'national_code' => 'nullable|digits:10',
            'mobiles' => 'nullable|array|max:10',
            'mobiles.*' => ['nullable', 'regex:/^09\d{9}$/'],
            'phone' => ['nullable', 'regex:/^0\d{6,11}$/'],
            'address' => 'nullable|string|max:1000',
        ], [
            'name.required' => 'نام و نام خانوادگی را وارد کنید',
            'national_code.digits' => 'کد ملی باید ۱۰ رقم باشد',
            'mobiles.max' => 'حداکثر ۱۰ شماره همراه قابل ثبت است',
            'mobiles.*.regex' => 'شماره همراه باید ۱۱ رقم و با 09 شروع شود',
            'phone.regex' => 'شماره ثابت نامعتبر است (همراه با پیش‌شماره و با 0 شروع شود)',
        ]);

        $user->fill([
            'name' => $request->input('name'),
            'national_code' => $request->input('national_code'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
        ]);
        $user->syncMobiles($request->input('mobiles', []) ?? []);
        $user->save();

        return $this->apiController->respondUpdated('اطلاعات پروفایل ویرایش شد', $this->profileData($user));
    }

    /**
     * دریافتی‌های کاربر در یک سال شمسی، به تفکیک ماه
     */
    public function earnings(Request $request, $userId)
    {
        $user = $this->resolveUser($userId);

        $all = UserProjectEarning::where('user_id', $user->id)
            ->with(['installationService' => fn($q) => $q->withoutEagerLoads()->with(['customer' => fn($q) => $q->withoutEagerLoads()->select('id', 'name', 'type')])])
            ->orderByDesc('date')
            ->get();

        $years = $all->pluck('jalali_year')->push(Verta::now()->year)->unique()->sortDesc()->values();
        $year = (int)($request->input('year') ?: $years->first());

        $earnings = $all->where('jalali_year', $year);

        $months = $earnings->groupBy('jalali_month')
            ->sortKeysDesc()
            ->map(fn($items, $month) => [
                'month' => $month,
                'month_name' => UserProjectEarning::MONTH_NAMES[$month] ?? '',
                'total' => $items->sum('amount'),
                'settled' => $items->where('is_settled', true)->sum('amount'),
                'unsettled' => $items->where('is_settled', false)->sum('amount'),
                'items' => $items->map(fn(UserProjectEarning $e) => $this->earningData($e))->values(),
            ])->values();

        return $this->apiController->respondData([
            'year' => $year,
            'years' => $years,
            'summary' => [
                'total' => $earnings->sum('amount'),
                'settled' => $earnings->where('is_settled', true)->sum('amount'),
                'unsettled' => $earnings->where('is_settled', false)->sum('amount'),
                'count' => $earnings->count(),
            ],
            'months' => $months,
        ]);
    }

    /**
     * پروژه‌هایی (سرویس نصب) که کاربر در آن‌ها نصاب یا کارشناس فنی بوده؛ برای انتخاب در فرم دریافتی
     */
    public function projects($userId)
    {
        $this->ensureAdmin();
        $user = $this->resolveUser($userId);

        $serviceIds = InstallerAppointment::withoutGlobalScopes()->where('user_id', $user->id)
            ->whereNotNull('installation_service_id')->pluck('installation_service_id');

        $projects = InstallationService::query()
            ->withoutEagerLoads()
            ->with(['customer' => fn($q) => $q->withoutEagerLoads()->select('id', 'name', 'type')])
            ->where(fn($q) => $q->whereIn('id', $serviceIds)->orWhere('technical_expert_id', $user->id))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(InstallationService $service) => [
                'id' => $service->id,
                'title' => $this->projectTitle($service),
            ]);

        return $this->apiController->respondData($projects->values());
    }

    public function storeEarning(Request $request, $userId)
    {
        $this->ensureAdmin();
        $user = $this->resolveUser($userId);
        $this->validateEarning($request);

        $earning = UserProjectEarning::create($this->earningAttributes($request) + [
                'user_id' => $user->id,
                'created_by' => auth()->id(),
            ]);

        return $this->apiController->respondCreated('دریافتی پروژه ثبت شد', $earning);
    }

    public function updateEarning(Request $request, $id)
    {
        $this->ensureAdmin();
        $earning = UserProjectEarning::findOrFail($id);
        $this->validateEarning($request);

        $earning->update($this->earningAttributes($request));

        return $this->apiController->respondUpdated('دریافتی پروژه ویرایش شد', $earning);
    }

    public function destroyEarning($id)
    {
        $this->ensureAdmin();
        UserProjectEarning::findOrFail($id)->delete();

        return $this->apiController->respondDeleted();
    }

    private function validateEarning(Request $request): void
    {
        $this->validate($request, [
            'installation_service_id' => 'required|exists:installation_services,id',
            'amount' => 'required|integer|min:1',
            'date' => 'required',
            'is_settled' => 'boolean',
            'settled_at' => 'nullable',
            'description' => 'nullable|string|max:1000',
        ], [
            'installation_service_id.required' => 'پروژه (سرویس نصب) را انتخاب کنید',
            'amount.required' => 'مبلغ دریافتی را وارد کنید',
            'amount.integer' => 'مبلغ دریافتی نامعتبر است',
            'amount.min' => 'مبلغ دریافتی باید بیشتر از صفر باشد',
            'date.required' => 'تاریخ را وارد کنید',
        ]);
    }

    private function earningAttributes(Request $request): array
    {
        $settled = $request->boolean('is_settled');

        return [
            'installation_service_id' => $request->input('installation_service_id'),
            'amount' => (int)$request->input('amount'),
            'date' => Verta::parse($request->input('date'))->datetime()->format('Y-m-d'),
            'is_settled' => $settled,
            'settled_at' => !$settled ? null : ($request->filled('settled_at')
                ? Verta::parse($request->input('settled_at'))->datetime()->format('Y-m-d')
                : now()->format('Y-m-d')),
            'description' => $request->input('description'),
        ];
    }

    private function earningData(UserProjectEarning $earning): array
    {
        return [
            'id' => $earning->id,
            'installation_service_id' => $earning->installation_service_id,
            'project_title' => $earning->installationService ? $this->projectTitle($earning->installationService) : '-',
            'amount' => $earning->amount,
            'jalali_date' => $earning->jalali_date,
            'is_settled' => $earning->is_settled,
            'jalali_settled_at' => $earning->jalali_settled_at,
            'description' => $earning->description,
        ];
    }

    private function projectTitle(InstallationService $service): string
    {
        $customer = $service->customer?->fullName;
        return ($customer ? $customer . ' - ' : '') . $service->title;
    }

    private function profileData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'national_code' => $user->national_code,
            'mobiles' => $user->mobiles ?: ($user->mobile ? [$user->mobile] : []),
            'phone' => $user->phone,
            'address' => $user->address,
            'role' => $user->role,
            'role_label' => $user->role_label,
            'status' => $user->status,
            'profile' => $user->profile,
            'emza' => $user->emza,
            'is_self' => $user->id === auth()->id(),
            'can_manage_earnings' => $this->isAdmin(),
        ];
    }

    // «me» یعنی کاربر جاری؛ کاربر غیر ادمین فقط به پروفایل خودش دسترسی دارد
    private function resolveUser($userId): User
    {
        if ($userId === 'me' || $userId === auth()->id())
            return auth()->user();

        $this->ensureAdmin();

        return User::findOrFail($userId);
    }

    private function isAdmin(): bool
    {
        return (int)auth()->user()->role === User::ADMIN;
    }

    private function ensureAdmin(): void
    {
        abort_unless($this->isAdmin(), 403, 'دسترسی به این بخش فقط برای مدیر مجاز است');
    }
}
