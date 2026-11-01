<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sms;
use App\Models\User;
use Carbon\Carbon;
use App\Services\SmsService;
use Illuminate\Http\Request;

class SmsController extends Controller
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
        $data = [];

        if (auth()->user()->role == User::ADMIN) {
            $data = Sms::orderby('created_at', 'DESC')->get();
        }

        return $this->apiController->respondData($data);
    }

    public function store(Request $request)
    {
//        if (Sms::sum('count') > env('SMS_MAX_COUNT')) {
//            return $this->apiController->respondError('تعداد پیامک ارسالی شما به حد نصاب رسیده است. لطفا با پشتیبانی تماس بگیرید');
//        }

        $this->validate($request, [
            'type' => 'required|in:' . implode(',', [
                    Sms::TYPE_CUSTOMERS,
                    Sms::TYPE_LEADS,
                    Sms::TYPE_BOTH,
                    Sms::TYPE_SPECIFIC,
                ]),
            'category_id' => 'nullable|exists:customer_categories,id',
            'receivers' => 'required_if:type,' . Sms::TYPE_SPECIFIC . '|array',
            'receivers.*.id' => 'required_with:receivers|exists:customers,id',
            'body' => 'required',
        ], [
            'type.required' => 'نوع ارسال را انتخاب نمایید',
            'type.in' => 'نوع ارسال معتبر نیست',
            'category_id.exists' => 'دسته بندی انتخاب شده معتبر نیست',
            'receivers.required_if' => 'حداقل یک دریافت کننده را انتخاب نمایید',
            'body.required' => 'متن پیامک را وارد نمایید',
        ]);

        $type = $request->input('type');
        $categoryId = $request->input('category_id');
        $body = $request->input('body');

        $customers = $this->resolveReceivers($type, $categoryId, $request->input('receivers', []));

        if ($customers->isEmpty()) {
            return $this->apiController->respondError('هیچ دریافت کننده‌ای با این مشخصات یافت نشد');
        }

        [$mobilesArray, $smsTotalCount] = $this->sendToCustomers($customers, $body);

        $create = Sms::create([
            'send_to_all' => $type !== Sms::TYPE_SPECIFIC,
            'receiver_type' => $type,
            'category_id' => $categoryId,
            'receivers' => $mobilesArray,
            'count' => $smsTotalCount,
            'body' => $body,
            'sent_at' => Carbon::now(),
            'user_id' => auth()->id(),
        ]);

        if ($create) {
            return $this->apiController->respondCreated('ارسال شد');
        }

        return $this->apiController->respondInternalError();
    }

    private function resolveReceivers(string $type, ?string $categoryId, array $receivers)
    {
        if ($type === Sms::TYPE_SPECIFIC) {
            $ids = collect($receivers)->pluck('id')->filter()->all();

            return Customer::query()->withoutEagerLoads()->whereIn('id', $ids)->get(['id', 'type', 'contact_number']);
        }

        $query = Customer::query();

        switch ($type) {
            case Sms::TYPE_CUSTOMERS:
                $query->where('status', Customer::CUSTOMER);
                break;
            case Sms::TYPE_LEADS:
                $query->where('status', Customer::LEAD);
                break;
            case Sms::TYPE_BOTH:
                $query->whereIn('status', [Customer::LEAD, Customer::CUSTOMER]);
                break;
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return $query->withoutEagerLoads()->get(['id', 'type', 'contact_number']);
    }

    // ارسال گروهی به‌صورت هم‌زمان (به جای یک درخواست HTTP پشت سر هم برای هر مشتری)
    private function sendToCustomers($customers, string $body): array
    {
        $mobiles = $customers
            ->filter(fn($customer) => (int)$customer->type !== Customer::JURIDICAL && !empty($customer->contact_number))
            ->pluck('contact_number')
            ->all();

        $sent = app(SmsService::class)->sendMany($mobiles, $body);

        return [$sent, count($sent)];
    }

}
