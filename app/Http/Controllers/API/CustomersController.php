<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\Referrer;
use App\Models\Reminder;
use App\Models\User;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomersController extends Controller
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
        $data = Customer::withStats()
            ->whereIn('status', [Customer::CUSTOMER, Customer::TO])
            ->when(auth()->user()->role == User::KARSHENAS_FANI, fn($q) => $q->where('user_id', auth()->id()))
            ->orderByDesc('created_at')
            ->get();

        return $this->apiController->respondData($data);
    }

    public function getAllCustomersAndLeads()
    {
        $data = Customer::withStats()
            ->when(auth()->user()->role == User::KARSHENAS_FANI, fn($q) => $q->where('user_id', auth()->id()))
            ->orderByDesc('created_at')
            ->get();

        return $this->apiController->respondData($data);
    }


    public function store(Request $request)
    {

        $customerType = $request->input('type');


        if (!($customerType == 0 || $customerType == 1)) {
            return $this->apiController->respondError('نوع مشتری را انتخاب نمایید');
        }

        if ($customerType == 0) {


            $this->validate($request, [
                'gender' => 'required',
                'name' => 'required',
                'contact_number' => 'required'
            ], [
                'gender.required' => 'جنسیت را انتخاب نمایید',
                'name.required' => 'نام و نام خانوادگی را وارد نمایید',
                'contact_number.required' => 'شماره تلفن همراه را وارد نمایید'
            ]);


            if ($request->input('gender') == 0) {
                $customerType = Customer::MALE;
            } else {
                $customerType = Customer::FEMALE;
            }


        } else {

            $this->validate($request, [
                'name' => 'required',
                'contact_number' => 'required'
            ], [
                'name.required' => 'نام سازمان / شرکت را وارد نمایید',
                'contact_number.required' => 'شماره تماس را وارد نمایید'
            ]);

            $customerType = Customer::JURIDICAL;

        }

        $contactNumber = $request->input('contact_number');
        $contactNumber1 = $request->input('contact_number1');
        $contactNumber2 = $request->input('contact_number2');

        if ($contactNumber != null && Customer::where('contact_number', $contactNumber)->exists()) {
            return $this->apiController->respondValidationError('مشتری با این شماره تلفن قبلا ثبت شده است');
        }

        $convertedBirthDate = null;
        if ($request->has('birth_date') && $request->input('birth_date') != null) {
            $convertedBirthDate = Verta::parse($request->input('birth_date'))->datetime();
        }

        $convertedMarriageDate = null;
        if ($request->has('marriage_date') && $request->input('marriage_date') != null) {
            $convertedMarriageDate = Verta::parse($request->input('marriage_date'))->datetime();
        }


        $categoryId = null;
        if ($request->has('category') && $request->input('category') != null) {
            $categoryId = CustomerCategory::findOrFail($request->input('category'))->id;
        }


        $referrerId = null;
        $referrerPercent = null;
        if ($request->has('referrer') && $request->input('referrer') != null){
            $referrerId = Referrer::findOrFail($request->input('referrer'))->id;
            $referrerPercent = $this->apiHelper->referrerPercent($request);
        }

        $attributes = [
            'status' => Customer::CUSTOMER,
            'type' => $customerType,
            'birth_date' => $convertedBirthDate,
            'marriage_date' => $convertedMarriageDate,

            'contact_number' => $contactNumber,
            'contact_number1' => $contactNumber1,
            'contact_number2' => $contactNumber2,
            'landline_number' => $this->apiHelper->getRealText($request, 'landline_number'),
            'name' => $this->apiHelper->getRealText($request, 'name'),
            'city' => $this->apiHelper->getRealText($request, 'city'),
            'province' => $this->apiHelper->getRealText($request, 'province'),
            'disc' => $this->apiHelper->getRealText($request, 'disc'),
            'national_code' => $this->apiHelper->getRealText($request, 'national_code'),
            'postal_code' => $this->apiHelper->getRealText($request, 'postal_code'),
            'address' => $this->apiHelper->getRealText($request, 'address'),
            'eitaa' => $this->apiHelper->getRealText($request, 'eitaa'),
            'email' => $this->apiHelper->getRealText($request, 'email'),
            'whatsapp' => $this->apiHelper->getRealText($request, 'whatsapp'),
            'telegram' => $this->apiHelper->getRealText($request, 'telegram'),
            'instagram' => $this->apiHelper->getRealText($request, 'instagram'),
            'website' => $this->apiHelper->getRealText($request, 'website'),
            'job' => $this->apiHelper->getRealText($request, 'job'),
            'job_address' => $this->apiHelper->getRealText($request, 'job_address'),
            'description' => $this->apiHelper->getRealText($request, 'description'),
            'category_id' => $categoryId,
            'referrer_id' => $referrerId,
            'referrer_percent' => $referrerPercent,
            'user_id' => auth()->id()
        ];

        $create = DB::transaction(function () use ($request, $attributes, $convertedBirthDate) {
            $create = Customer::create($attributes);
            $this->savePersons($create, $request->input('persons'));

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

        $customerType = $request->input('type');


        if (!($customerType == 0 || $customerType == 1)) {
            return $this->apiController->respondError('نوع مشتری را انتخاب نمایید');
        }


        if ($customerType == 0) {

            $this->validate($request, [
                'gender' => 'required',
                'name' => 'required',
                'contact_number' => 'required'
            ], [
                'gender.required' => 'جنسیت را انتخاب نمایید',
                'name.required' => 'نام و نام خانوادگی را وارد نمایید',
                'contact_number.required' => 'شماره تلفن همراه را وارد نمایید'
            ]);


            if ($request->input('gender') == 0) {
                $customerType = Customer::MALE;
            } else {
                $customerType = Customer::FEMALE;
            }


        } else {

            $this->validate($request, [
                'name' => 'required',
                'contact_number' => 'required'
            ], [
                'name.required' => 'نام سازمان / شرکت را وارد نمایید',
                'contact_number.required' => 'شماره تماس را وارد نمایید'
            ]);

            $customerType = Customer::JURIDICAL;

        }

        $contactNumber = $request->input('contact_number');
        $contactNumber1 = $request->input('contact_number1');
        $contactNumber2 = $request->input('contact_number2');

        if ($contactNumber != null && Customer::where('contact_number', $contactNumber)->where('id', '!=', $data->id)->exists()) {
            return $this->apiController->respondValidationError('مشتری با این شماره تلفن قبلا ثبت شده است');
        }


        $convertedBirthDate = null;
        if ($request->has('birth_date') && $request->input('birth_date') != null) {
            $convertedBirthDate = Verta::parse($request->input('birth_date'))->datetime();
        }

        $convertedMarriageDate = null;
        if ($request->has('marriage_date') && $request->input('marriage_date') != null) {
            $convertedMarriageDate = Verta::parse($request->input('marriage_date'))->datetime();
        }

        $categoryId = null;
        if ($request->has('category') && $request->input('category') != null) {
            $categoryId = CustomerCategory::findOrFail($request->input('category'))->id;
        }

        $referrerId = null;
        $referrerPercent = null;
        if ($request->has('referrer') && $request->input('referrer') != null){
            $referrerId = Referrer::findOrFail($request->input('referrer'))->id;
            $referrerPercent = $this->apiHelper->referrerPercent($request);
        }

        $attributes = [
            'category_id' => $categoryId,
            'type' => $customerType,
            'birth_date' => $convertedBirthDate,
            'national_code' => $this->apiHelper->getRealText($request, 'national_code'),
            'postal_code' => $this->apiHelper->getRealText($request, 'postal_code'),
            'address' => $this->apiHelper->getRealText($request, 'address'),
            'marriage_date' => $convertedMarriageDate,
            'contact_number' => $contactNumber,
            'contact_number1' => $contactNumber1,
            'contact_number2' => $contactNumber2,
            'landline_number' => $this->apiHelper->getRealText($request, 'landline_number'),
            'name' => $this->apiHelper->getRealText($request, 'name'),
            'city' => $this->apiHelper->getRealText($request, 'city'),
            'province' => $this->apiHelper->getRealText($request, 'province'),
            'disc' => $this->apiHelper->getRealText($request, 'disc'),
            'eitaa' => $this->apiHelper->getRealText($request, 'eitaa'),
            'email' => $this->apiHelper->getRealText($request, 'email'),
            'whatsapp' => $this->apiHelper->getRealText($request, 'whatsapp'),
            'telegram' => $this->apiHelper->getRealText($request, 'telegram'),
            'instagram' => $this->apiHelper->getRealText($request, 'instagram'),
            'website' => $this->apiHelper->getRealText($request, 'website'),
            'job' => $this->apiHelper->getRealText($request, 'job'),
            'job_address' => $this->apiHelper->getRealText($request, 'job_address'),
            'description' => $this->apiHelper->getRealText($request, 'description'),
            'referrer_id' => $referrerId,
            'referrer_percent' => $referrerPercent,
        ];

        $update = DB::transaction(function () use ($data, $attributes, $request) {
            $update = $data->update($attributes);

            $data->persons()->delete();
            $this->savePersons($data, $request->input('persons'));

            return $update;
        });

        if ($update) {
            return $this->apiController->respondUpdated();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

    private function savePersons(Customer $customer, ?array $persons): void
    {
        foreach ($persons ?? [] as $item) {
            $customer->persons()->create([
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

    public function quickStore(Request $request)
    {


        $this->validate($request, [
            'gender' => 'required',
            'name' => 'required',
            'contact_number' => 'required'
        ], [
            'gender.required' => 'جنسیت را انتخاب نمایید',
            'name.required' => 'نام و نام خانوادگی را وارد نمایید',
            'contact_number.required' => 'شماره تلفن همراه را وارد نمایید'
        ]);


        if ($request->input('gender') == 0) {
            $customerType = Customer::MALE;
        } else {
            $customerType = Customer::FEMALE;
        }

        $contactNumber = $request->input('contact_number');

        if ($contactNumber != null && Customer::where('contact_number', $contactNumber)->exists()) {
            return $this->apiController->respondValidationError('مشتری با این شماره تلفن قبلا ثبت شده است');
        }


        $create = Customer::create([
            'status' => Customer::CUSTOMER,
            'type' => $customerType,
            'name' => $request->input('name'),
            'contact_number' => $request->input('contact_number'),
            'user_id' => auth()->id()
        ]);

        if ($create and $create instanceof Customer) {
            return $this->apiController->respondCreated('اطلاعات با موفقیت ثبت شد', $create);
        } else {
            return $this->apiController->respondInternalError();
        }

    }

    public function installation_services(Customer $customer)
    {
        return $this->apiController->respondData($customer->installation_services);
    }

    public function after_sale_services(Customer $customer)
    {
        return $this->apiController->respondData($customer->after_sale_services);
    }

    public function automations(Customer $customer , $status)
    {
        return $this->apiController->respondData($customer->automations()->where("status" , $status)->get());
    }
}
