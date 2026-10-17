<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Check;
use App\Models\Customer;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;

class ChecksController extends Controller
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
        $data = Check::orderby('created_at', 'DESC')->get();
        return $this->apiController->respondData($data);
    }


    public function store(Request $request)
    {

        $this->validate($request, [
            'series' => 'required',
            'serial' => 'required',
            'date' => 'required',
            'amount' => 'required',
            'bank' => 'required',
            'type' => 'required',
            'status' => 'required',
            'bankAccount' => 'required'
        ], [
            'series.required' => 'سری چک را وارد نمایید',
            'serial.required' => 'سریال چک را وارد نمایید',
            'date.required' => 'تاریخ چک را وارد نمایید',
            'amount.required' => 'مبلغ چک را وارد نمایید',
            'bank.required' => 'بانک را انتخاب نمایید',
            'type.required' => 'نوع چک را انتخاب نمایید',
            'status.required' => 'وضعیت چک را انتخاب نمایید',
            'bankAccount.required' => 'حساب بانکی را انتخاب نمایید'
        ]);


        $bankAccountId = null;
        $customerId = null;
        $bankId = null;

        if ($request->has('bankAccount') and $request->input('bankAccount') != null) {
            $bankAccountId = BankAccount::findOrFail($request->input('bankAccount'))->id;
        }

        if ($request->has('customer') and $request->input('customer') != null) {
            $customerId = Customer::findOrFail($request->input('customer'))->id;
        }

        if ($request->has('bank') and $request->input('bank') != null) {
            $bankId = Bank::findOrFail($request->input('bank'))->id;
        }

        $create = Check::create([
            'type' => $request->input('type'),
            'status' => $request->input('status'),
            'amount' => $this->apiHelper->getRealPrice($request->input('amount')),
            'date' => Verta::parse($request->input('date'))->datetime(),
            'series' => $request->input('series'),
            'serial' => $request->input('serial'),
            'bank_id' => $bankId,
            'customer_id' => $customerId,
            'bank_account_id' => $bankAccountId,
            'description' => $request->input('description'),
            'user_id' => auth()->id()
        ]);

        if ($create and $create instanceof Check) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }

    }


    public function show(Check $check)
    {
        return $this->apiController->respondData($check);
    }


    public function update(Request $request)
    {

        $this->validate($request, [
            'series' => 'required',
            'serial' => 'required',
            'date' => 'required',
            'amount' => 'required',
            'bank' => 'required',
            'type' => 'required',
            'status' => 'required',
            'bankAccount' => 'required'
        ], [
            'series.required' => 'سری چک را وارد نمایید',
            'serial.required' => 'سریال چک را وارد نمایید',
            'date.required' => 'تاریخ چک را وارد نمایید',
            'amount.required' => 'مبلغ چک را وارد نمایید',
            'bank.required' => 'بانک را انتخاب نمایید',
            'type.required' => 'نوع چک را انتخاب نمایید',
            'status.required' => 'وضعیت چک را انتخاب نمایید',
            'bankAccount.required' => 'حساب بانکی را انتخاب نمایید'
        ]);

        $data = Check::findOrFail($request->input('id'));


        $bankAccountId = null;
        $customerId = null;
        $bankId = null;

        if ($request->has('bankAccount') and $request->input('bankAccount') != null) {
            $bankAccountId = BankAccount::findOrFail($request->input('bankAccount'))->id;
        }

        if ($request->has('customer') and $request->input('customer') != null) {
            $customerId = Customer::findOrFail($request->input('customer'))->id;
        }

        if ($request->has('bank') and $request->input('bank') != null) {
            $bankId = Bank::findOrFail($request->input('bank'))->id;
        }

        $update = $data->update([
            'type' => $request->input('type'),
            'status' => $request->input('status'),
            'amount' => $this->apiHelper->getRealPrice($request->input('amount')),
            'date' => Verta::parse($request->input('date'))->datetime(),
            'serial' => $request->input('serial'),
            'series' => $request->input('series'),
            'bank_id' => $bankId,
            'customer_id' => $customerId,
            'bank_account_id' => $bankAccountId,
            'description' => $request->input('description'),
        ]);

        if ($update) {
            return $this->apiController->respondUpdated();
        } else {
            return $this->apiController->respondInternalError();
        }

    }


    public function destroy(Request $request)
    {
        $data = Check::findOrFail($request->input('id'));
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }


}
