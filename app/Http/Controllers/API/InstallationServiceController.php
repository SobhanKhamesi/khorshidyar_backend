<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Customer;
use App\Models\InstallationService;
use App\Models\InstallationServiceItem;
use App\Models\InstallerAppointment;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InstallationServiceController extends Controller
{
    private ApiController $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function list()
    {
        $list = InstallationService::query()
            ->with(['customer', 'automation', 'technicalExpert', 'installerAppointments', 'installationServiceItems.product'])
            ->orderbyDesc('created_at')->get();

        return $this->apiController->respondData($list);
    }
    public function customerAutomations($customerId)
    {
        Customer::findOrFail($customerId);

        $automations = Automation::where('customer_id', $customerId)
            ->where('status', Automation::SUCCESS)
            ->orderByDesc('visit_date')
            ->get();

        return $this->apiController->respondData($automations);
    }

    public function customerInstallationServices($customerId)
    {
        Customer::findOrFail($customerId);

        $list = InstallationService::where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->get(['id', 'status', 'survey_date', 'created_at']);

        return $this->apiController->respondData($list->values());
    }

    public function store(Request $request)
    {
        $this->validatePayload($request);

        try {
            $service = DB::transaction(function () use ($request) {
                $service = InstallationService::create($this->extractServiceAttributes($request));
                $this->syncInstallers($service, $request->input('installers', []));
                $this->syncConsumableItems($service, $request->input('items', []));
                return $service;
            });
        } catch (\Exception $e) {
            return $this->apiController->respondError($e->getMessage());
        }

        return $this->apiController->respondCreated("با موفقیت ایجاد شد", $service->load(['installerAppointments', 'installationServiceItems.product']));
    }

    public function update(Request $request, $id)
    {
        $service = InstallationService::findOrFail($id);

        $this->validatePayload($request);

        try {
            DB::transaction(function () use ($request, $service) {
                $service->update($this->extractServiceAttributes($request));
                $this->syncInstallers($service, $request->input('installers', []));
                $this->syncConsumableItems($service, $request->input('items', []));
            });
        } catch (\Exception $e) {
            return $this->apiController->respondError($e->getMessage());
        }

        return $this->apiController->respondUpdated('با موفقیت ویرایش شد', $service->load(['installerAppointments', 'installationServiceItems.product']));
    }

    public function show($id)
    {
        $service = InstallationService::with(['customer', 'automation', 'technicalExpert', 'installerAppointments', 'installationServiceItems.product'])
            ->findOrFail($id);

        return $this->apiController->respondData($service);
    }
    public function destroy($id)
    {
        $service = InstallationService::findOrFail($id);
        $service->installerAppointments()->update(['status' => InstallerAppointment::CANCELED]);
        $service->delete();
        return $this->apiController->respondDeleted();
    }

    private function validatePayload(Request $request): void
    {
        $isFinal = $request->input('status') === InstallationService::FINAL;

        $rules = [
            'customer_id' => 'required|exists:customers,id',
            'purchase_source' => ['required', Rule::in([InstallationService::INTERNAL, InstallationService::EXTERNAL])],
            'automation_id' => 'nullable|exists:automation,id',
            'address' => 'nullable|string',
            'contact_number' => 'nullable|string',
            'description' => 'nullable|string',
            'survey_date' => $isFinal ? 'required' : 'nullable',
            'technical_expert' => $isFinal ? 'required' : 'nullable',
            'technical_expert.id' => $isFinal ? 'required|exists:users,id' : 'nullable|exists:users,id',
            'status' => ['required', Rule::in([InstallationService::TEMPORARY, InstallationService::FINAL])],
            'installers' => 'required|array',

            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items.*.quantity|exists:products,id',
            'items.*.quantity' => 'required_with:items.*.product_id|numeric|min:0.01',
        ];

        // زمان شروع و پایان هر نصاب همیشه با انتخاب خود کاربر پر می‌شود؛
        // هیچ بازه‌ی از پیش آماده‌ای وجود ندارد. هر سه نقش (panel/wiring/commissioning)
        // یک ساختار یکسان دارند: installer_id, date, start_time, end_time
        foreach (array_keys(InstallationService::ROLE_MAP) as $roleKey) {
            if ($roleKey === 'after_sale') {
                continue; // نوبت خدمات پس از فروش از فرم جداگانه‌ی خودش ثبت می‌شود
            }

            $rules["installers.$roleKey"] = 'nullable|array';
            $rules["installers.$roleKey.installer_id"] = 'nullable|exists:users,id';
            $rules["installers.$roleKey.date"] = 'nullable|date';
            $rules["installers.$roleKey.start_time"] = 'nullable|date_format:H:i';
            $rules["installers.$roleKey.end_time"] = "nullable|date_format:H:i|after:installers.$roleKey.start_time";
        }

        $this->validate($request, $rules, [
            'survey_date.required' => 'تاریخ بازدید را وارد کنید',
            'technical_expert.required' => 'کارشناس فنی را انتخاب کنید',
            'items.*.product_id.required_with' => 'محصول ردیف اقلام مصرفی را انتخاب کنید',
            'items.*.quantity.required_with' => 'تعداد ردیف اقلام مصرفی را وارد کنید',
        ]);

        if ($isFinal) {
            $this->assertRoleComplete($request, 'panel', 'نصاب پنل');
            $this->assertRoleComplete($request, 'wiring', 'نصاب سیم کشی');
            $this->assertRoleComplete($request, 'commissioning', 'نصاب راه اندازی');
        }
    }

    private function assertRoleComplete(Request $request, string $role, string $label): void
    {
        $item = $request->input("installers.$role", []);

        if (empty($item['installer_id']) || empty($item['date']) || empty($item['start_time']) || empty($item['end_time'])) {
            abort(422, "اطلاعات «{$label}» تکمیل نشده است");
        }
    }

    private function extractServiceAttributes(Request $request): array
    {
        $purchaseSource = (int)$request->input('purchase_source');

        return [
            'customer_id' => $request->input('customer_id'),
            'purchase_source' => $purchaseSource,
            'automation_id' => $purchaseSource === InstallationService::INTERNAL
                ? $request->input('automation_id')
                : null,
            'address' => $request->input('address'),
            'contact_number' => $request->input('contact_number'),
            'description' => $request->input('description'),
            'survey_date' => $request->filled('survey_date')
                ? Verta::parse($request->input('survey_date'))->datetime()->format('Y-m-d')
                : null,
            'technical_expert_id' => $request->input('technical_expert.id'),
            'status' => $request->input('status'),
        ];
    }

    private function syncInstallers(InstallationService $service, array $installers): void
    {
        foreach (InstallationService::ROLE_MAP as $roleKey => $typeValue) {
            if ($roleKey === 'after_sale') {
                continue;
            }

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

            $date = Verta::parse($item['date'])->toCarbon();
            $startTime = $item['start_time'];
            $endTime = $item['end_time'];

            InstallerAppointment::updateOrCreate(
                ['installation_service_id' => $service->id, 'type' => $typeValue],
                [
                    'user_id' => $item['installer_id'],
                    'customer_id' => $service->customer_id,
                    'automation_id' => $service->automation_id,
                    'date' => $date->format('Y-m-d'),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => InstallerAppointment::RESERVED,
                ]
            );
        }
    }

    private function syncConsumableItems(InstallationService $service, array $items): void
    {
        $service->installationServiceItems()->delete();

        $rows = collect($items)
            ->filter(fn($item) => !empty($item['product_id']) && !empty($item['quantity']))
            ->map(fn($item) => [
                'installation_service_id' => $service->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values();

        if ($rows->isNotEmpty()) {
            foreach ($rows->toArray() as $item)
                InstallationServiceItem::create($item);
        }
    }
}
