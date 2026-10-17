<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\BankAccount;
use Illuminate\Http\Request;

class BankAccountsController extends Controller
{

    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }


    public function getBanksList()
    {

        $data = Bank::get();
        return $this->apiController->respondData($data);

    }

    public function index()
    {
        $data = BankAccount::orderby('created_at', 'DESC')->get();
        return $this->apiController->respondData($data);

    }


    public function store(Request $request)
    {

        $this->validate($request, [
            'bank_id' => 'required',
            'number' => 'required'
        ], [
            'bank_id.required' => 'بانک را انتخاب نمایید.',
            'number.required' => 'شماره حساب را وارد نمایید.'
        ]);


        $create = BankAccount::create([
            'bank_id' => $request->input('bank_id'),
            'number' => $request->input('number'),
            'description' => $request->input('description'),
            'user_id' => auth()->id()
        ]);

        if ($create and $create instanceof BankAccount) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }

    }


    public function show(BankAccount $bankAccount)
    {
        return $this->apiController->respondData($bankAccount);
    }


    public function update(Request $request)
    {
        $this->validate($request, [
            'bank_id' => 'required',
            'number' => 'required'
        ], [
            'bank_id.required' => 'بانک را انتخاب نمایید.',
            'number.required' => 'شماره حساب را وارد نمایید.'
        ]);

        $data = BankAccount::findOrFail($request->input('id'));

        $update = $data->update([
            'bank_id' => $request->input('bank_id'),
            'number' => $request->input('number'),
            'description' => $request->input('description')
        ]);

        if ($update) {
            return $this->apiController->respondUpdated();
        } else {
            return $this->apiController->respondInternalError();
        }


    }


    public function destroy(Request $request)
    {
        $data = BankAccount::findOrFail($request->input('id'));
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

}
