<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\Controller;
use App\Models\AfterSalesService;
use App\Models\Customer;
use App\Models\InstallationService;
use App\Models\Rating;
use App\Models\SourceSms;
use App\Services\AfterSalesSmsNotifier;
use Hekmatinasser\Verta\Verta;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;


class AfterSalesServiceController extends Controller
{
    private ApiController $apiController;


    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function list()
    {
        $list = AfterSalesService::query()
            ->with(['customer', 'installationService', 'serviceType', 'responsibleUser', 'ratings.reviewer:id,name'])
            ->orderByDesc('created_at')
            ->orderBy('follow_up_date')
            ->get();

        return $this->apiController->respondData($list);
    }

    public function customerHistory($customerId)
    {
        Customer::findOrFail($customerId);

        $history = AfterSalesService::where('customer_id', $customerId)
            ->with(['serviceType', 'responsibleUser', 'installationService'])
            ->orderByDesc('created_at')
            ->get();

        $data = $history->map(function (AfterSalesService $service) {
            return [
                'id' => $service->id,
                'problem' => $service->problem,
                'status' => $service->status,
                'status_label' => $service->status_label,
                'source' => $service->source,
                'source_label' => $service->source_label,
                'service_type' => $service->serviceType,
                'responsible_user' => $service->responsibleUser,
                'request_date' => $service->request_date,
                'jalali_request_date' => $service->jalali_request_date,
                'follow_up_date' => $service->follow_up_date,
                'jalali_follow_up_date' => $service->jalali_follow_up_date,
                'jalali_date' => $service->jalali_date,
                'installation_service_id' => $service->installation_service_id,
                'installation_service_title' => $service->installation_service_title,
                'resolution_description' => $service->resolution_description,
            ];
        });

        return $this->apiController->respondData($data->values());
    }

    /**
     * سرویس‌های نصب تکمیل شده‌ی مشتری برای انتخاب در فرم خدمات پس از فروش، همراه با نصاب پروژه
     */
    public function customerInstallations($customerId)
    {
        Customer::findOrFail($customerId);

        $services = InstallationService::query()
            ->withoutEagerLoads()
            ->where('customer_id', $customerId)
            ->where('stage', InstallationService::STAGE_COMPLETED)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(InstallationService $service) => [
                'id' => $service->id,
                'title' => $service->title,
                'project_installer' => $service->projectInstaller()?->only(['id', 'name', 'mobile']),
            ]);

        return $this->apiController->respondData($services->values());
    }

    public function store(Request $request, AfterSalesSmsNotifier $notifier)
    {
        $this->applyProjectInstaller($request);
        $this->validatePayload($request, true);

        try {
            $service = DB::transaction(function () use ($request) {
                $attributes = $this->extractServiceAttributes($request);
                $attributes['status'] = AfterSalesService::STATUS_PENDING;
                $attributes['source'] = AfterSalesService::SOURCE_MANUAL;

                return AfterSalesService::create($attributes);
            });
        } catch (\Exception $e) {
            return $this->apiController->respondError($e->getMessage());
        }

        $warnings = $notifier->notifyRegistered($service);
        $message = 'با موفقیت ثبت شد و پیامک برای مشتری و مسئول پیگیری ارسال شد';
        if ($warnings)
            $message = 'با موفقیت ثبت شد. ' . implode(' - ', $warnings);

        return $this->apiController->respondCreated(
            $message,
            $service->load(['customer', 'installationService', 'serviceType', 'responsibleUser'])
        );
    }

    public function update(Request $request, $id)
    {
        $service = AfterSalesService::findOrFail($id);
        $this->applyProjectInstaller($request);
        $this->validatePayload($request);

        try {
            DB::transaction(function () use ($request, $service) {
                $service->update($this->extractServiceAttributes($request));
            });
        } catch (\Exception $e) {
            return $this->apiController->respondError($e->getMessage());
        }

        return $this->apiController->respondUpdated(
            'با موفقیت ویرایش شد',
            $service->load(['customer', 'installationService', 'serviceType', 'responsibleUser'])
        );
    }

    public function complete(Request $request, $id)
    {
        $service = AfterSalesService::findOrFail($id);

        $this->validate($request, [
            'resolution_description' => 'required|string',
        ], [
            'resolution_description.required' => 'توضیحات نتیجه پیگیری را وارد کنید',
        ]);

        if ($service->status === AfterSalesService::STATUS_COMPLETED) {
            return $this->apiController->respondError('این خدمت پس از فروش قبلاً تکمیل شده است');
        }

        DB::transaction(function () use ($request, $service) {
            $service->update([
                'status' => AfterSalesService::STATUS_COMPLETED,
                'resolution_description' => $request->input('resolution_description'),
                'completed_at' => now(),
            ]);
        });

        if ($request->input("send_sms"))
            return $this->Sms($service);

        return $this->apiController->respondUpdated(
            "با موفقیت ویرایش شد",
            $service->load(['customer', 'installationService', 'serviceType', 'responsibleUser'])
        );
    }

    /**
     * ثبت نتیجه بررسی دلیل نارضایتی مشتری برای نظرسنجی با سطح «ضعیف» (رفع آلارم)
     */
    public function reviewRating(Request $request, $ratingId)
    {
        $rating = Rating::findOrFail($ratingId);

        $this->validate($request, [
            'review_description' => 'required|string|max:2000',
        ], [
            'review_description.required' => 'دلیل نارضایتی و نتیجه بررسی را وارد کنید',
            'review_description.max' => 'متن بررسی بیش از حد طولانی است',
        ]);

        if ($rating->level !== Rating::LEVEL_POOR)
            return $this->apiController->respondError('فقط نظرسنجی‌های با سطح «ضعیف» نیاز به بررسی دارند');

        $rating->update([
            'review_description' => $request->input('review_description'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return $this->apiController->respondUpdated('نتیجه بررسی نارضایتی ثبت شد', $rating->load('reviewer:id,name'));
    }

    public function show($id)
    {
        $service = AfterSalesService::with([
            'customer', 'installationService', 'serviceType', 'responsibleUser'
        ])->findOrFail($id);

        return $this->apiController->respondData($service);
    }

    public function destroy($id)
    {
        $service = AfterSalesService::findOrFail($id);
        DB::transaction(function () use ($service) {
            $service->delete();
        });
        return $this->apiController->respondDeleted();
    }

    /**
     * مسئول خدمتِ مرتبط با یک سرویس نصب، نصاب خود همان پروژه است (اگر مسئول دیگری انتخاب نشده باشد)
     */
    private function applyProjectInstaller(Request $request): void
    {
        if ($request->filled('responsible_user_id') || !$request->filled('installation_service_id'))
            return;

        $installer = InstallationService::find($request->input('installation_service_id'))?->projectInstaller();
        if ($installer)
            $request->merge(['responsible_user_id' => $installer->id]);
    }

    private function validatePayload(Request $request, bool $creating = false): void
    {
        $this->validate($request, [
            'customer_id' => 'required|exists:customers,id',
            'installation_service_id' => [
                'nullable',
                Rule::exists('installation_services', 'id')->where('customer_id', $request->input('customer_id')),
            ],
            'problem' => 'required|string',
            'service_type_id' => 'required|exists:service_types,id',
            'responsible_user_id' => 'required|exists:users,id',
            'request_date' => 'nullable',
            // تاریخ مراجعه در پیامک مشتری ارسال می‌شود
            'follow_up_date' => $creating ? 'required' : 'nullable',
        ], [
            'customer_id.required' => 'مشتری را انتخاب کنید',
            'installation_service_id.exists' => 'سرویس نصب انتخاب شده متعلق به این مشتری نیست',
            'problem.required' => 'مشکل را وارد کنید',
            'service_type_id.required' => 'نوع خدمت را انتخاب کنید',
            'responsible_user_id.required' => 'مسئول این خدمت را انتخاب کنید',
            'follow_up_date.required' => 'تاریخ مراجعه را وارد کنید',
        ]);
    }

    private function extractServiceAttributes(Request $request): array
    {
        return [
            'customer_id' => $request->input('customer_id'),
            'installation_service_id' => $request->input('installation_service_id'),
            'problem' => $request->input('problem'),
            'service_type_id' => $request->input('service_type_id'),
            'responsible_user_id' => $request->input('responsible_user_id'),
            'request_date' => $request->filled('request_date')
                ? Verta::parse($request->input('request_date'))->datetime()->format('Y-m-d')
                : now()->format('Y-m-d'),
            'follow_up_date' => $request->filled('follow_up_date')
                ? Verta::parse($request->input('follow_up_date'))->datetime()->format('Y-m-d')
                : null,
        ];
    }

    public function Sms(AfterSalesService $service)
    {
        $new_rating = Rating::getOrCreateFor($service);
        $link = rtrim(config('services.frontend_url'), '/') . '/survey/' . $new_rating->token;

        // جای لینک در متن با {link} در قالب «نظرسنجی» (تنظیمات پیامک) مشخص می‌شود
        $customer = $service->customer;
        $content = $customer->greeting_name . "\n\n"
            . SourceSms::renderWithLink(Rating::SMS_TEMPLATE_TITLE, Rating::DEFAULT_SMS_TEXT, $link, 'لینک صفحه نظرسنجی : ');

        $create = app(SmsService::class)->sendAndLog($customer, $content);

        if ($create) {
            return $this->apiController->respondCreated('با موفقیت ویرایش و پیامک ارسال شد');
        } else {
            return $this->apiController->respondInternalError();
        }
    }
}
