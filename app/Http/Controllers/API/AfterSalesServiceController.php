<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\Controller;
use App\Models\AfterSalesService;
use App\Models\Customer;
use App\Models\Rating;
use App\Models\Sms;
use App\Models\SourceSms;
use Hekmatinasser\Verta\Verta;
use App\Services\SmsService;
use Illuminate\Http\Request;
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
            ->with(['customer', 'installationService', 'serviceType', 'responsibleUser'])
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
                'resolution_description' => $service->resolution_description,
            ];
        });

        return $this->apiController->respondData($data->values());
    }

    public function store(Request $request)
    {
        $this->validatePayload($request);

        try {
            $service = DB::transaction(function () use ($request) {
                $attributes = $this->extractServiceAttributes($request);
                $attributes['status'] = AfterSalesService::STATUS_PENDING;
                $attributes['source'] = AfterSalesService::SOURCE_MANUAL;

                $service = AfterSalesService::create($attributes);
                $this->syncInstaller($service, $request);
                return $service;
            });
        } catch (\Exception $e) {
            return $this->apiController->respondError($e->getMessage());
        }

        return $this->apiController->respondCreated(
            'با موفقیت ثبت شد',
            $service->load(['customer', 'installationService', 'serviceType', 'responsibleUser', 'installerAppointments.installer'])
        );
    }

    public function update(Request $request, $id)
    {
        $service = AfterSalesService::findOrFail($id);
        $this->validatePayload($request);

        try {
            DB::transaction(function () use ($request, $service) {
                $service->update($this->extractServiceAttributes($request));
                $this->syncInstaller($service, $request);
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

    private function validatePayload(Request $request): void
    {
        $this->validate($request, [
            'customer_id' => 'required|exists:customers,id',
            'installation_service_id' => 'nullable|exists:installation_services,id',
            'problem' => 'required|string',
            'service_type_id' => 'required|exists:service_types,id',
            'responsible_user_id' => 'required|exists:users,id',
            'request_date' => 'nullable',
            'follow_up_date' => 'nullable',
            'installer_id' => 'nullable|exists:users,id',
        ], [
            'customer_id.required' => 'مشتری را انتخاب کنید',
            'problem.required' => 'مشکل را وارد کنید',
            'service_type_id.required' => 'نوع خدمت را انتخاب کنید',
            'responsible_user_id.required' => 'مسئول این خدمت را انتخاب کنید',
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
        $sms = SourceSms::where('title', 'نظرسنجی')->first();

        if (!$sms)
            return $this->apiController->respondError("قالب sms برای 'نظرسنجی' ثبت نشده است.");

        $new_rating = Rating::getOrCreateFor($service);

        $customer = $service->customer;
        $content = $customer->greeting_name . "\n\n" . $sms->description . "\n"
            . "لینک صفحه نظرسنجی : " . rtrim(config('services.frontend_url'), '/') . '/survey/' . $new_rating->token;

        $create = app(SmsService::class)->sendAndLog($customer, $content);

        if ($create) {
            return $this->apiController->respondCreated('با موفقیت ویرایش و پیامک ارسال شد');
        } else {
            return $this->apiController->respondInternalError();
        }
    }
}
