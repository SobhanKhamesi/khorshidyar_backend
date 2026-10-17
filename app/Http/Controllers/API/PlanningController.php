<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Planning;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;

class PlanningController extends Controller
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
        $data = Planning::orderby('created_at', 'DESC')->get();
        return $this->apiController->respondData($data);
    }


    public function store(Request $request)
    {


        $this->validate($request, [
            'title' => 'required',
            'date' => 'required',
            'startTime' => 'required',
            'endTime' => 'required'
        ], [
            'title.required' => 'عنوان را وارد نمایید',
            'date.required' => 'تاریخ را وارد نمایید',
            'startTime.required' => 'زمان شروع را وارد نمایید',
            'endTime.required' => 'زمان اتمام را وارد نمایید'
        ]);


        $create = Planning::create([
            'title' => $request->input('title'),
            'date' => Verta::parse($request->input('date'))->datetime(),
            'time_start' => $request->input('startTime'),
            'time_end' => $request->input('endTime'),
            'status' => Planning::ACTIVE,
            'description' => $request->input('description'),
            'user_id' => auth()->id()
        ]);

        if ($create and $create instanceof Planning) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }

    }


    public function show(Planning $planning)
    {
        return $this->apiController->respondData($planning);
    }


    public function update(Request $request)
    {


        $this->validate($request, [
            'title' => 'required',
            'date' => 'required',
            'startTime' => 'required',
            'endTime' => 'required'
        ], [
            'title.required' => 'عنوان را وارد نمایید',
            'date.required' => 'تاریخ را وارد نمایید',
            'startTime.required' => 'زمان شروع را وارد نمایید',
            'endTime.required' => 'زمان اتمام را وارد نمایید'
        ]);

        $data = Planning::findOrFail($request->input('id'));


        $update = $data->update([
            'title' => $request->input('title'),
            'date' => Verta::parse($request->input('date'))->datetime(),
            'time_start' => $request->input('startTime'),
            'time_end' => $request->input('endTime'),
            'status' => $request->input('status'),
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

        $data = Planning::findOrFail($request->input('id'));
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }

    }

}
