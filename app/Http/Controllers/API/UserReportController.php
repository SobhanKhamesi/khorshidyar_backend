<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Call;
use App\Models\Check;
use App\Models\Customer;
use App\Models\Imprest;
use App\Models\Kala;
use App\Models\Leave;
use App\Models\Letter;
use App\Models\Mamoriat;
use App\Models\OverTime;
use App\Models\Planning;
use App\Models\Product;
use App\Models\Reminder;
use App\Models\Sms;
use App\Models\Targeting;
use App\Models\User;
use App\Models\UserReport;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;

class UserReportController extends Controller
{

    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }


    public function index()
    {
        if (auth()->user()->role == User::ADMIN) {


            $invoices = Automation::where('status', Automation::SUCCESS)->get();
            $calls = Call::get();
            $products = Product::withStats()->get();
            $sms = Sms::get();
            $leads = Customer::withStats()->where('status', Customer::LEAD)->get();
            $plan = Planning::get();
            $reminder = Reminder::get();
            $report = [];

            foreach ($leads as $item) {
                $data = [
                    'date' => $item->jalali_created,
                    'type' => 'ثبت سرنخ جدید',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user
                ];
                array_push($report, $data);
            }

            foreach ($invoices as $item) {
                $data = [
                    'date' => $item->jalali_date,
                    'type' => 'فروش نهایی شده',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user
                ];
                $report[] = $data;

            }

            foreach ($calls as $item) {
                $data = [
                    'date' => $item->jalali_created,
                    'type' => 'تماس پیگیری',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user
                ];

                $report[] = $data;
            }


            foreach ($products as $item) {
                $data = [
                    'date' => $item->jalali_created,
                    'type' => 'افزودن محصول یا خدمات',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user
                ];
                $report[] = $data;
            }

            foreach ($sms as $item) {
                $data = [
                    'date' => $item->jalali_created_at,
                    'type' => 'ارسال پیامک',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user
                ];
                $report[] = $data;
            }

            foreach ($plan as $item) {
                $data = [
                    'date' => $item->jalali_date,
                    'type' => 'جلسه',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user
                ];
                $report[] = $data;
            }

            foreach ($reminder as $item) {
                $data = [
                    'date' => $item->jalali_created,
                    'type' => 'ثبت یادآوری',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user

                ];
                $report[] = $data;
            }

            $this->array_sort_by_column($report, 'date');

            return $this->apiController->respondData($report);



        } elseif (auth()->user()->role == User::KARSHENAS_FANI || auth()->user()->role == User::NASSAB || auth()->user()->role == User::KARSHENAS_FOROSH || auth()->user()->role == User::MODIR_FOROSH) {

            $invoices = Automation::where('user_id', auth()->id())->where('status', Automation::SUCCESS)->get();
            $calls = Call::where('user_id', auth()->id())->get();
            $products = Product::withStats()->where('user_id', auth()->id())->get();
            $sms = Sms::where('user_id', auth()->id())->get();
            $leads = Customer::withStats()->where('user_id', auth()->id())->where('status', Customer::LEAD)->get();
            $plan = Planning::where('user_id', auth()->id())->get();
            $reminder = Reminder::where('user_id', auth()->id())->get();
            $report = [];

            foreach ($leads as $item) {
                $data = [
                    'date' => $item->jalali_date,
                    'type' => 'ثبت سرنخ جدید',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user
                ];
                array_push($report, $data);
            }

            foreach ($invoices as $item) {
                $data = [
                    'date' => $item->jalali_date,
                    'type' => 'فروش نهایی شده',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user
                ];
                $report[] = $data;
            }

            foreach ($calls as $item) {
                $data = [
                    'date' => $item->jalali_created,
                    'type' => 'تماس پیگیری',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user
                ];

                $report[] = $data;
            }


            foreach ($products as $item) {
                $data = [
                    'date' => $item->jalali_date,
                    'type' => 'افزودن محصول یا خدمات',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user
                ];
                $report[] = $data;
            }

            foreach ($sms as $item) {
                $data = [
                    'date' => $item->jalali_created_at,
                    'type' => 'ارسال پیامک',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user
                ];
                $report[] = $data;
            }

            foreach ($plan as $item) {
                $data = [
                    'date' => $item->jalali_date,
                    'type' => 'جلسه',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user
                ];
                $report[] = $data;
            }

            foreach ($reminder as $item) {
                $data = [
                    'date' => $item->jalali_created,
                    'type' => 'ثبت یادآوری',
                    'SabtKonande' => $item->SabtKonande,
                    'user' => $item->user

                ];
                $report[] = $data;
            }

            $this->array_sort_by_column($report, 'date');

            return $this->apiController->respondData($report);
        } else {
            return $this->apiController->respondNotFound();
        }
    }

    public function array_sort_by_column(&$array, $column, $direction = SORT_DESC)
    {
        $reference_array = array();

        foreach ($array as $key => $row) {
            $reference_array[$key] = $row[$column];
        }

        array_multisort($reference_array, $direction, $array);
    }


}
