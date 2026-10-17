<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Accounting;
use App\Models\PreInvoiceStatus;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;

class PreInvoiceStatusController extends Controller
{

    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }


    public function index(Accounting $accounting)
    {
        $data = PreInvoiceStatus::where('invoice_id', $accounting->id)->orderbyDesc('created_at')->get();
        return $this->apiController->respondData($data);
    }


    public function store(Request $request)
    {

        $data = Accounting::findOrFail($request->input('id'));


        $this->validate($request, [
            'status' => 'required',
            'date' => 'required'
        ], [
            'status.required' => 'وضعیت را وارد نمایید',
            'date.required' => 'تاریخ را انتخاب نمایید'
        ]);


        $create = PreInvoiceStatus::create([
            'invoice_id' => $data->id,
            'status' => $request->input('status'),
            'date' => Verta::parse($request->input('date'))->datetime(),
            'reject_reason' => $request->input('reject_reason'),
            'description' => $request->input('description'),
            'user_id' => auth()->id()
        ]);


        if ($create and $create instanceof PreInvoiceStatus) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }


    }


    public function destroy(Request $request)
    {

        $data = PreInvoiceStatus::findOrFail($request->input('id'));
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }

    }
}
