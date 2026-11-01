<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Accounting;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;

class OpportunitiesController extends Controller
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
        if (auth()->user()->role === User::KARSHENAS_FANI) {
            $data = Customer::withStats()->where('status', Customer::OPPORTUNITY)->where('user_id', auth()->id())->orderbyDesc('created_at')->get();
        } else {
            $data = Customer::withStats()->where('status', Customer::OPPORTUNITY)->orderbyDesc('created_at')->get();
        }
        return $this->apiController->respondData($data);

    }

    public function convert(Request $request)
    {
        $invoice = Accounting::findOrFail($request->input('id'));
        $customer = Customer::findOrFail($invoice->customer_id);
        $invoice->update([
            'type' => Accounting::SALES
        ]);

        $customer->update([
            'status' => Customer::TO
        ]);

        return $this->apiController->respondUpdated();
    }


}
