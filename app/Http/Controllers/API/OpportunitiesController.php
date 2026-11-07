<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Accounting;
use App\Models\Customer;
use App\Models\User;
use App\Support\Access;
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
        $data = Customer::withStats()->where('status', Customer::OPPORTUNITY)
            ->tap(fn($q) => Access::scopeCustomers($q))
            ->orderbyDesc('created_at')->get();
        return $this->apiController->respondData($data);

    }

    public function convert(Request $request)
    {
        Access::ensureCan('accounting.manage');
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
