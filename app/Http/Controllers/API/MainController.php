<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\AfterSalesService;
use App\Models\Automation;
use App\Models\Check;
use App\Models\Customer;
use App\Models\InstallationService;
use App\Models\Reminder;
use Carbon\Carbon;

class MainController extends Controller
{
    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }


    public function getExpiredItems()
    {
        $dataChecks = Check::where('user_id', auth()->id())->whereDate('date', '<=', Carbon::now())->where('status', Check::PENDING)->get();
        $dataReminder = Reminder::where('user_id', auth()->id())->whereDate('date', '<=', Carbon::now())->where('status', Reminder::ACTIVE)->get();
        $data = [];


        foreach ($dataChecks as $check) {
            $data[] = [
                'type' => 0,
                'date' => $check->created_at,
                'id' => $check->id,
                'title' => 'چک به مبلغ : ' . number_format($check->amount) . ' تومان',
            ];
        }
        foreach ($dataReminder as $reminder) {
            $data[] = [
                'type' => 1,
                'date' => $reminder->created_at,
                'id' => $reminder->id,
                'title' => $reminder->title,
            ];
        }

        $this->apiHelper->sortArray($data, 'date');

        return $this->apiController->respondData($data);

    }

    public function checking()
    {
        $darkhastCount = Automation::where('status', Automation::REQUEST)->count();
        $pishfactorCount = Automation::where('status', Automation::PRE_INVOICE)->count();
        $mozakereCount = Automation::where('status', Automation::NEGOTIATION)->count();

        $after_sale_count = AfterSalesService::query()
            ->whereIn("follow_up_date", [Carbon::today(), Carbon::tomorrow()])
            ->whereIn("status", [AfterSalesService::STATUS_PENDING, AfterSalesService::STATUS_IN_PROGRESS])->count();

        $installation_service_count = InstallationService::query()
            ->count(); // todo

        $data = [
            'darkhast_count' => $darkhastCount,
            'pishfactor_count' => $pishfactorCount,
            'mozakere_count' => $mozakereCount,
            'after_sale_count' => $after_sale_count,
            'installation_service_count' => $installation_service_count,
            // فرم‌های اولیه‌ای که مشتری تکمیل کرده و هنوز در کارتابل دیده نشده‌اند
            'intake_form_count' => IntakeFormController::unseenSubmittedCount(),
            // قراردادهایی که مشتری امضا کرده و هنوز در کارتابل دیده نشده‌اند
            'contract_signed_count' => ContractController::unseenSignedCount(),
        ];

        return $this->apiController->respondData($data);
    }
}
