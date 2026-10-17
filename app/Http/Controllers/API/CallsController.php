<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Customer;
use App\Models\Reminder;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;

class CallsController extends Controller
{

    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }


    public function index(Customer $customer)
    {
        $calls = Call::where('customer_id', $customer->id)->orderbyDesc('created_at')->get();
        $data = [
            'data' => $calls,
            'customer' => $customer
        ];
        return $this->apiController->respondData($data);
    }


    public function store(Request $request)
    {

        $this->validate($request, [
            'type_call' => 'required',
            'subject' => 'required',
        ], [
            'type_call.required' => 'نوع تماس را انتخاب نمایید',
            'subject.required' => 'خلاصه تماس را وارد نمایید',
        ]);

        $customer = Customer::findOrFail($request->input('id'));

        $connectedName = '';
        if ($request->input('contacted') !== null && is_array($request->input('contacted')))
            $connectedName = $request->input('contacted')['name'];

        $reFollowDate = null;
        $reFollowSub = null;
        if ($request->has('reFollowDate') && $request->input('reFollowDate') != null) {
            $reFollowDate = Verta::parse($request->input('reFollowDate'))->datetime();
            $reFollowSub = $request->input('reFollowSubject');
        }

        $create = Call::create([
            'customer_id' => $customer->id,
            'subject' => $request->input('subject'),
            'type_call' => $request->input('type_call'),
            'date' => $request->input('date') ? Verta::parse($request->input('date'))->datetime() : null,
            'time' => $request->input('time'),
            're_follow_date' => $reFollowDate,
            'contacted' => $connectedName,
            're_follow_time' => $request->input('reFollowTime'),
            're_follow_subject' => $reFollowSub,
            'description' => $request->input('description'),
            'user_id' => auth()->id(),
        ]);

        $customer->update([
            'lead_status' => $request->input('lead_status')
        ]);

        $customer->update([
            'reject_reason' => $request->input('reject_reason')
        ]);

        if ($create and $create instanceof Call) {
            if ($request->has('reFollowDate') && $request->input('reFollowDate') != null) {

                $reFollowDate = null;
                if ($request->has('reFollowDate') && $request->input('reFollowDate') != null) {
                    $reFollowDate = Verta::parse($request->input('reFollowDate'))->datetime();
                }

                Reminder::create([
                    'title' => 'پیگیری مجدد مکالمه با ' . $customer->full_name,
                    'date' => $reFollowDate,
                    'description' => $request->input('title'),
                    'status' => Reminder::ACTIVE,
                    'user_id' => auth()->id()
                ]);
            }


            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }


    }


    public function show(Call $call)
    {
        return $this->apiController->respondData($call);
    }


    public function update(Request $request)
    {

        $this->validate($request, [
            'type_call' => 'required',
            'subject' => 'required',
        ], [
            'type_call.required' => 'نوع تماس را انتخاب نمایید',
            'subject.required' => 'خلاصه تماس را وارد نمایید',
        ]);

        $data = Call::findOrFail($request->input('id'));
        $customer = Customer::findOrFail($request->input('customer',));

        $reFollowDate = null;
        if ($request->has('reFollowDate') && $request->input('reFollowDate') != null) {
            $reFollowDate = Verta::parse($request->input('reFollowDate'))->datetime();
        }

        $connectedName = $data->contacted;
        if ($request->input('contacted') !== null && is_array($request->input('contacted')))
            $connectedName = $request->input('contacted')['name'];

        $update = $data->update([
            'subject' => $request->input('subject'),
            'type_call' => $request->input('type_call'),
            'description' => $request->input('description'),
            'date' => Verta::parse($request->input('date'))->datetime(),
            'contacted' => $connectedName,
            're_follow_date' => $reFollowDate,
            're_follow_time' => $request->input('reFollowTime'),
            're_follow_subject' => $request->input('reFollowSubject'),
            'time' => $request->input('time'),
        ]);

        $customer->update([
            'lead_status' => $request->input('lead_status')
        ]);


        $customer->update([
            'reject_reason' => $request->input('reject_reason')
        ]);

        if ($update) {
            return $this->apiController->respondUpdated();
        } else {
            return $this->apiController->respondInternalError();
        }

    }

    public function destroy(Request $request)
    {
        $data = Call::findOrFail($request->input('id'));
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

}
