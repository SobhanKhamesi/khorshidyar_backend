<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Referrer;
use App\Models\User;
use App\Models\CustomerCategory;
use App\Models\Reminder;
use App\Models\SourceCategory;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Hekmatinasser\Verta\Verta;

class LeadsController extends Controller
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


    public function index()
    {
        $data = Customer::withStats()
            ->where('status', Customer::LEAD)
            ->when(auth()->user()->role === User::KARSHENAS_FANI, fn($q) => $q->where('user_id', auth()->id()))
            ->orderByDesc('created_at')
            ->get();
        return $this->apiController->respondData($data);

    }


    public function Sms(Customer $customer)
    {
        $sms = $this->smsService->sendTemplateToCustomer('خوش آمدگویی', $customer);
        if (!$sms)
            return $this->apiController->respondError("قالب sms برای 'خوش آمدگویی' ثبت نشده است.");

        return $this->apiController->respondCreated('سرنخ با موفقیت ایجاد و پیامک خوش آمد گویی ارسال شد.');
    }

    public function store(Request $request)
    {

        $leadType = $request->input('type');

        if (!($leadType == 0 || $leadType == 1))
            return $this->apiController->respondError('نوع سرنخ را انتخاب نمایید');

        if ($leadType == 0) {

            $this->validate($request, [
                'gender' => 'required',
                'name' => 'required',
                'contact_number' => 'required',
                'category' => 'required',
                'lead_source' => 'required'
            ], [
                'gender.required' => 'جنسیت را انتخاب نمایید',
                'name.required' => 'نام و نام خانوادگی را وارد نمایید',
                'contact_number.required' => 'شماره تلفن همراه را وارد نمایید',
                'category.required' => 'دسته بندی را انتخاب نمایید',
                'lead_source.required' => 'شیوه آشنایی را انتخاب نمایید'
            ]);


            if ($request->input('gender') == 0) {
                $leadType = Customer::MALE;
            } else {
                $leadType = Customer::FEMALE;
            }


        } else {

            $this->validate($request, [
                'name' => 'required',
                'contact_number' => 'required',
                'category' => 'required',
                'lead_source' => 'required'

            ], [
                'name.required' => 'نام سازمان / شرکت را وارد نمایید',
                'contact_number.required' => 'شماره تماس را وارد نمایید',
                'category.required' => 'دسته بندی را انتخاب نمایید',
                'lead_source.required' => 'شیوه آشنایی را انتخاب نمایید'
            ]);

            $leadType = Customer::JURIDICAL;

        }


        $contactNumber = $request->input('contact_number');
        $contactNumber1 = $request->input('contact_number1');
        $contactNumber2 = $request->input('contact_number2');

        if ($contactNumber != null && Customer::where('contact_number', $contactNumber)->exists())
            return $this->apiController->respondValidationError('سرنخ یا مشتری با این شماره تلفن قبلا ثبت شده است');

        $categoryId = null;
        if ($request->has('category') && $request->input('category') != null)
            $categoryId = CustomerCategory::findOrFail($request->input('category'))->id;

        $sourceId = null;
        if ($request->has('lead_source') && $request->input('lead_source') != null)
            $sourceId = SourceCategory::findOrFail($request->input('lead_source'))->id;

        $referrerId = null;
        $referrerPercent = null;
        if ($request->has('referrer') && $request->input('referrer') != null){
            $referrerId = Referrer::findOrFail($request->input('referrer'))->id;
            $referrerPercent = $this->apiHelper->referrerPercent($request);
        }

        $convertedBirthDate = null;
        if ($request->has('birth_date') && $request->input('birth_date') != null) {
            $convertedBirthDate = Verta::parse($request->input('birth_date'))->datetime();
        }

        $create = DB::transaction(function () use ($request, $leadType, $convertedBirthDate, $contactNumber, $contactNumber1, $contactNumber2, $categoryId, $sourceId, $referrerId, $referrerPercent) {
            $create = Customer::create([
                'type' => $leadType,
                'status' => Customer::LEAD,
                'birth_date' => $convertedBirthDate,

                'name' => $this->apiHelper->getRealText($request, 'name'),
                'city' => $this->apiHelper->getRealText($request, 'city'),
                'province' => $this->apiHelper->getRealText($request, 'province'),
                'national_code' => $this->apiHelper->getRealText($request, 'national_code'),
                'postal_code' => $this->apiHelper->getRealText($request, 'postal_code'),
                'address' => $this->apiHelper->getRealText($request, 'address'),
                'disc' => $this->apiHelper->getRealText($request, 'disc'),
                'email' => $this->apiHelper->getRealText($request, 'email'),
                'whatsapp' => $this->apiHelper->getRealText($request, 'whatsapp'),
                'telegram' => $this->apiHelper->getRealText($request, 'telegram'),
                'eitaa' => $this->apiHelper->getRealText($request, 'eitaa'),
                'instagram' => $this->apiHelper->getRealText($request, 'instagram'),
                'description' => $this->apiHelper->getRealText($request, 'description'),
                'website' => $this->apiHelper->getRealText($request, 'website'),

                'contact_number' => $contactNumber,
                'contact_number1' => $contactNumber1,
                'contact_number2' => $contactNumber2,
                'landline_number' => $this->apiHelper->getRealText($request, 'landline_number'),
                'lead_status' => $this->apiHelper->getRealText($request, 'lead_status'),
                'reject_reason' => $this->apiHelper->getRealText($request, 'reject_reason'),

                'category_id' => $categoryId,
                'source_id' => $sourceId,
                'referrer_id' => $referrerId,
                'referrer_percent' => $referrerPercent,
                'user_id' => auth()->id()
            ]);


            foreach ($request->input('persons') ?? [] as $item)
                $create->persons()->create([
                    'gender' => $item['gender'],
                    'name' => $this->apiHelper->getRealTextRare($item, 'name'),
                    'contact_number' => $this->apiHelper->getRealTextRare($item, 'contact_number'),
                    'position' => $this->apiHelper->getRealTextRare($item, 'position'),
                    'disc' => $this->apiHelper->getRealTextRare($item, 'disc'),
                    'whatsapp' => $this->apiHelper->getRealTextRare($item, 'whatsapp'),
                    'email' => $this->apiHelper->getRealTextRare($item, 'email'),
                    'user_id' => auth()->id()
                ]);

            foreach ($request->input('calls') ?? [] as $item) {

                $jalaliReFollowDate = '';
                if ($item['reFollowDate'] != null)
                    $jalaliReFollowDate = Verta::parse($item['reFollowDate'])->datetime();

                $connectedName = '';
                if (isset($item['contacted']))
                    $connectedName = $item['contacted']['name'];

                $create->calls()->create([
                    'date' => Verta::parse($item['date'])->datetime(),
                    'type_call' => $this->apiHelper->getRealTextRare($item, 'type_call'),
                    're_follow_date' => $jalaliReFollowDate,
                    're_follow_subject' => $this->apiHelper->getRealTextRare($item, 'reFollowSubject'),
                    'time' => $this->apiHelper->getRealTextRare($item, 'time'),
                    're_follow_time' => $this->apiHelper->getRealTextRare($item, 're_follow_time'),
                    'subject' => $this->apiHelper->getRealTextRare($item, 'subject'),
                    'contacted' => $connectedName,
                    'description' => $this->apiHelper->getRealTextRare($item, 'description'),
                    'user_id' => auth()->id(),
                ]);

            }

            foreach ($request->input('reminders') ?? [] as $item) {

                $jalaliDate = null;
                if ($item['date'] != null)
                    $jalaliDate = Verta::parse($item['date'])->datetime();

                Reminder::create([
                    'title' => $this->apiHelper->getRealTextRare($item, 'title'),
                    'description' => $this->apiHelper->getRealTextRare($item, 'description'),
                    'date' => $jalaliDate,
                    'status' => Reminder::ACTIVE,
                    'user_id' => auth()->id()
                ]);

            }

            // در صورتی که تاریخ تولد ثبت شده باشد، یادآوری تولد برای آن به‌صورت خودکار ساخته می‌شود
            if ($convertedBirthDate) {
                Reminder::create([
                    'title' => 'تولد ' . $create->fullName,
                    'date' => Reminder::nextYearlyOccurrence($convertedBirthDate),
                    'status' => Reminder::ACTIVE,
                    'type' => Reminder::TYPE_BIRTHDAY,
                    'customer_id' => $create->id,
                    'user_id' => auth()->id(),
                ]);
            }

            return $create;
        });


        if ($request->input("send_sms"))
            return $this->Sms($create);

        if ($create and $create instanceof Customer) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }


    }


    public function show(Customer $customer)
    {
        if (auth()->user()->role === User::KARSHENAS_FANI && $customer->user_id !== auth()->user()->id)
            return $this->apiController->respondForbidden();

        return $this->apiController->respondData($customer);
    }

    public function update(Request $request)
    {

        $data = Customer::findOrFail($request->input('id'));

        if (auth()->user()->role === User::KARSHENAS_FANI && $data->user_id !== auth()->user()->id)
            return $this->apiController->respondForbidden();

        $leadType = $request->input('type');


        if (!($leadType == 0 || $leadType == 1)) {
            return $this->apiController->respondError('نوع سرنخ را انتخاب نمایید');
        }

        if ($leadType == 0) {


            $this->validate($request, [
                'gender' => 'required',
                'name' => 'required',
                'contact_number' => 'required',
                'category' => 'required',
                'lead_source' => 'required'

            ], [
                'gender.required' => 'جنسیت را انتخاب نمایید',
                'name.required' => 'نام و نام خانوادگی را وارد نمایید',
                'contact_number.required' => 'شماره تلفن همراه را وارد نمایید',
                'category.required' => 'دسته بندی را انتخاب نمایید',
                'lead_source.required' => 'شیوه آشنایی را انتخاب نمایید'

            ]);


            if ($request->input('gender') == 0) {
                $leadType = Customer::MALE;
            } else {
                $leadType = Customer::FEMALE;
            }


        } else {

            $this->validate($request, [
                'name' => 'required',
                'contact_number' => 'required',
                'category' => 'required',

            ], [
                'name.required' => 'نام سازمان / شرکت را وارد نمایید',
                'contact_number.required' => 'شماره تماس را وارد نمایید',
                'category.required' => 'دسته بندی را انتخاب نمایید',

            ]);

            $leadType = Customer::JURIDICAL;

        }


        $contactNumber = $request->input('contact_number');
        $contactNumber1 = $request->input('contact_number1');
        $contactNumber2 = $request->input('contact_number2');


        if ($contactNumber != null && Customer::where('contact_number', $contactNumber)->where('id', '!=', $data->id)->exists()) {
            return $this->apiController->respondValidationError('سرنخ یا مشتری با این شماره تلفن قبلا ثبت شده است');
        }


        $categoryId = null;
        if ($request->has('category') && $request->input('category') != null) {
            $categoryId = CustomerCategory::findOrFail($request->input('category'))->id;
        }


        $sourceId = null;
        if ($request->has('lead_source') && $request->input('lead_source') != null) {
            $sourceId = SourceCategory::findOrFail($request->input('lead_source'))->id;
        }

        $referrerId = null;
        $referrerPercent = null;
        if ($request->has('referrer') && $request->input('referrer') != null){
            $referrerId = Referrer::findOrFail($request->input('referrer'))->id;
            $referrerPercent = $this->apiHelper->referrerPercent($request);
        }

        $attributes = [
            'type' => $leadType,
            'status' => Customer::LEAD,


            'name' => $this->apiHelper->getRealText($request, 'name'),
            'city' => $this->apiHelper->getRealText($request, 'city'),
            'province' => $this->apiHelper->getRealText($request, 'province'),
            'national_code' => $this->apiHelper->getRealText($request, 'national_code'),
            'postal_code' => $this->apiHelper->getRealText($request, 'postal_code'),
            'address' => $this->apiHelper->getRealText($request, 'address'),
            'disc' => $this->apiHelper->getRealText($request, 'disc'),
            'email' => $this->apiHelper->getRealText($request, 'email'),
            'eitaa' => $this->apiHelper->getRealText($request, 'eitaa'),
            'whatsapp' => $this->apiHelper->getRealText($request, 'whatsapp'),
            'telegram' => $this->apiHelper->getRealText($request, 'telegram'),
            'instagram' => $this->apiHelper->getRealText($request, 'instagram'),
            'website' => $this->apiHelper->getRealText($request, 'website'),
            'description' => $this->apiHelper->getRealText($request, 'description'),
            'contact_number' => $contactNumber,
            'contact_number1' => $contactNumber1,
            'contact_number2' => $contactNumber2,
            'landline_number' => $this->apiHelper->getRealText($request, 'landline_number'),
            'lead_status' => $this->apiHelper->getRealText($request, 'lead_status'),
            'reject_reason' => $this->apiHelper->getRealText($request, 'reject_reason'),

            'source_id' => $sourceId,
            'category_id' => $categoryId,
            'referrer_id' => $referrerId,
            'referrer_percent' => $referrerPercent,
        ];

        $update = DB::transaction(function () use ($data, $attributes, $request) {
            $update = $data->update($attributes);

            $data->persons()->delete();

            foreach ($request->input('persons') ?? [] as $item) {
                $data->persons()->create([
                    'gender' => $item['gender'],
                    'name' => $this->apiHelper->getRealTextRare($item, 'name'),
                    'contact_number' => $this->apiHelper->getRealTextRare($item, 'contact_number'),
                    'position' => $this->apiHelper->getRealTextRare($item, 'position'),
                    'disc' => $this->apiHelper->getRealTextRare($item, 'disc'),
                    'whatsapp' => $this->apiHelper->getRealTextRare($item, 'whatsapp'),
                    'email' => $this->apiHelper->getRealTextRare($item, 'email'),
                    'user_id' => auth()->id()
                ]);
            }

            return $update;
        });

        if ($update) {
            return $this->apiController->respondUpdated();
        } else {
            return $this->apiController->respondInternalError();
        }


    }

    public function destroy(Request $request)
    {
        $data = Customer::findOrFail($request->input('id'));

        if (auth()->user()->role === User::KARSHENAS_FANI && $data->user_id !== auth()->user()->id)
            return $this->apiController->respondForbidden();

        if ($data->deleteWithRelations()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }

    }


}
