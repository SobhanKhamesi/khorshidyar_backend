<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Models\Sms;
use App\Models\SourceSms;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;

class RemindersController extends Controller
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
        $data = Reminder::orderby('date', 'DESC')->get();
        return $this->apiController->respondData($data);
    }


    public function store(Request $request)
    {


        $this->validate($request, [
            'title' => 'required',
            'date' => 'required',
        ], [
            'title.required' => 'عنوان را وارد نمایید',
            'date.required' => 'تاریخ را وارد نمایید',
        ]);


        $create = Reminder::create([
            'title' => $request->input('title'),
            'date' => Verta::parse($request->input('date'))->datetime(),
            'status' => Reminder::ACTIVE,
            'description' => $request->input('description'),
            'user_id' => auth()->id()
        ]);

        if ($create and $create instanceof Reminder) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }

    }


    public function show(Reminder $reminder)
    {
        return $this->apiController->respondData($reminder);

    }


    public function update(Request $request)
    {


        $this->validate($request, [
            'title' => 'required',
            'date' => 'required',
        ], [
            'title.required' => 'عنوان را وارد نمایید',
            'date.required' => 'تاریخ را وارد نمایید',
        ]);


        $data = Reminder::findOrFail($request->input('id'));

        $update = $data->update([
            'title' => $request->input('title'),
            'date' => Verta::parse($request->input('date'))->datetime(),
            'status' => $request->input('status'),
            'description' => $request->input('description')
        ]);

        if (!$update) {
            return $this->apiController->respondInternalError();
        }

        // در صورتی که یادآوری از نوع تولد مشتری باشد، تکمیل شود و کاربر گزینه
        // ارسال پیامک تبریک را فعال کرده باشد، همزمان با تکمیل شدن، پیامک ارسال شود.
        $message = null;
        if (
            (int)$data->type === Reminder::TYPE_BIRTHDAY
            && $request->boolean('send_sms')
            && !$data->birthday_sms_sent
        ) {
            $smsResult = $this->sendBirthdaySmsForReminder($data);
            if ($smsResult === true) {
                $data->update([
                    'status' => Reminder::COMPLETED
                ]);
                $message = 'یادآوری تکمیل و پیامک تبریک تولد ارسال شد';
            } else {
                // تکمیل یادآوری با خطا مواجه نمی‌شود؛ فقط عدم ارسال پیامک اطلاع داده می‌شود
                $message = 'یادآوری تکمیل شد اما ارسال پیامک با خطا مواجه شد: ' . $smsResult;
            }
        }

        return $message !== null
            ? $this->apiController->respondUpdated($message)
            : $this->apiController->respondUpdated();


    }


    private function sendBirthdaySmsForReminder(Reminder $reminder)
    {
        $customer = $reminder->customer;
        if (!$customer || !$customer->contact_number) {
            return 'مشتری یا شماره تماس یافت نشد';
        }

        $sms = SourceSms::where('title', 'تبریک تولد')->first();
        if (!$sms) {
            return 'ابتدا قالب پیامک «تبریک تولد» را در تنظیمات پیامک ثبت نمایید';
        }

        $content = ($customer->type == 0 ? 'آقای ' : ($customer->type == 1 ? 'خانم ' : '')) . $customer->name;
        $content .= "\n";
        $content .= $sms->description;

        $mobile = $customer->contact_number;
        $urlSMS = "https://RayganSMS.com/SendMessageWithUrl.ashx?Username=" . urlencode(env('TREZ_USERNAME')) . "&Password=" . urlencode(env('TREZ_PASSWORD')) . "&PhoneNumber=" . urlencode(env('TREZ_NUMBER')) . "&MessageBody=" . urlencode($content) . "&RecNumber=" . urlencode($mobile) . "&Smsclass=1";

        $sent = @file_get_contents($urlSMS);

        Sms::create([
            'receivers' => $sent ? [$mobile] : [],
            'count' => $sent ? 1 : 0,
            'body' => $content,
            'sent_at' => Carbon::now(),
            'user_id' => auth()->id(),
            'customer_id' => $customer->id,
        ]);

        $reminder->update(['birthday_sms_sent' => true]);

        return $sent ? true : 'خطا در برقراری ارتباط با سامانه پیامک';
    }


    public function destroy(Request $request)
    {
        $data = Reminder::findOrFail($request->input('id'));
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

}
