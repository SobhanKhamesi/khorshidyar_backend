<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Models\DailyReportItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DailyReportsController extends Controller
{

    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }


    public function index($type)
    {
        if ($type == 'today') {
            $data = DailyReport::whereDate('date', Carbon::today())->where('status', DailyReport::FINISHED)->orderbyDesc('created_at')->get();
        } else {
            $data = DailyReport::where('status', DailyReport::FINISHED)->orderbyDesc('created_at')->get();
        }
        return $this->apiController->respondData($data);
    }


    public function MyList()
    {
        $data = new \stdClass();

        $todayReport = DailyReport::where('user_id', auth()->id())->whereDate('date', Carbon::today())->with('items')->first();
        if ($todayReport) {
            if ($todayReport->status != DailyReport::FINISHED) {
                $data->saved = $todayReport;
                $data->todo = true;
            } else {
                $data->saved = null;
                $data->todo = false;
            }
        } else {
            $data->todo = true;
        }

        return $this->apiController->respondData($data);
    }


    public function save(Request $request)
    {

        $from = null;
        $to = null;

        if ($request->has('startTime')) {
            if (Carbon::hasFormat($request->input('startTime'), 'H:i')) {
                $from = Carbon::createFromFormat('H:i', $request->input('startTime'));
            } else if (Carbon::hasFormat($request->input('startTime'), 'H:i:s')) {
                $from = Carbon::createFromFormat('H:i:s', $request->input('startTime'));
            }
        }

        if ($request->has('endTime')) {
            if (Carbon::hasFormat($request->input('endTime'), 'H:i')) {
                $to = Carbon::createFromFormat('H:i', $request->input('endTime'));
            } else if (Carbon::hasFormat($request->input('endTime'), 'H:i:s')) {
                $to = Carbon::createFromFormat('H:i:s', $request->input('endTime'));
            }
        }


        $create = DailyReport::updateOrCreate([
            'user_id' => auth()->id(),
            'date' => Carbon::today(),
            'status' => DailyReport::STARTED
        ], [
            'start_time' => $from,
            'end_time' => $to
        ]);

        if ($create and $create instanceof DailyReport) {


            DailyReportItem::where('report_id', $create->id)->delete();

            foreach ($request->input('items') as $item) {
                $itemTime = $item['time'];
                $itemBody = $item['body'];

                DailyReportItem::create([
                    'user_id' => auth()->id(),
                    'report_id' => $create->id,
                    'time' => $itemTime,
                    'body' => $itemBody
                ]);
            }


            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }
    }


    public function store(Request $request)
    {

        $this->validate($request, [
            'startTime' => 'required',
            'endTime' => 'required'
        ], [
            'startTime.required' => 'زمان شروع را وارد نمایید',
            'endTime.required' => 'زمان پایان را وارد نمایید'
        ]);

        $from = null;
        $to = null;

        if ($request->has('startTime')) {
            if (Carbon::hasFormat($request->input('startTime'), 'H:i')) {
                $from = Carbon::createFromFormat('H:i', $request->input('startTime'));
            } else if (Carbon::hasFormat($request->input('startTime'), 'H:i:s')) {
                $from = Carbon::createFromFormat('H:i:s', $request->input('startTime'));
            }
        }


        if ($request->has('endTime')) {
            if (Carbon::hasFormat($request->input('endTime'), 'H:i')) {
                $to = Carbon::createFromFormat('H:i', $request->input('endTime'));
            } else if (Carbon::hasFormat($request->input('endTime'), 'H:i:s')) {
                $to = Carbon::createFromFormat('H:i:s', $request->input('endTime'));
            }
        }


        $check = DailyReport::where('user_id', auth()->id())->where('status', DailyReport::STARTED)->whereDate('date', Carbon::today())->first();
        if ($check) {
            $update = $check->update([
                'start_time' => $from,
                'end_time' => $to,
                'status' => DailyReport::FINISHED,
            ]);

            $reportId = $check->id;

        } else {


            $create = DailyReport::create([
                'user_id' => auth()->id(),
                'date' => Carbon::today(),
                'start_time' => $from,
                'end_time' => $to,
                'status' => DailyReport::STARTED,
            ]);

            $reportId = $create->id;

        }

        if ((isset($create) and $create and $create instanceof DailyReport) or (isset($update) and $update)) {


            DailyReportItem::where('report_id', $reportId)->delete();

            foreach ($request->input('items') as $item) {
                $itemTime = $item['time'];
                $itemBody = $item['body'];

                DailyReportItem::create([
                    'user_id' => auth()->id(),
                    'report_id' => $reportId,
                    'time' => $itemTime,
                    'body' => $itemBody
                ]);
            }


            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

    public function show(DailyReport $dailyReport)
    {
        return $this->apiController->respondData($dailyReport);
    }


    public function destroy(Request $request)
    {
        $data = DailyReport::findOrFail($request->input('id'));
        $data->items()->delete();
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

}
