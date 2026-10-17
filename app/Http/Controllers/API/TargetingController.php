<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Targeting;
use App\Models\User;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;

class TargetingController extends Controller
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

        if (auth()->user()->role !== User::ADMIN) {
            $data = Targeting::where('user', auth()->id())->orderby('created_at', 'DESC')->get();
        } else {
            $data = Targeting::orderby('created_at', 'DESC')->get();
        }

        return $this->apiController->respondData($data);

    }


    public function store(Request $request)
    {

        if (auth()->user()->role !== User::ADMIN)
            return $this->apiController->respondForbidden();

        $this->validate($request, [
            'user' => 'required',
            'month' => 'required'
        ], [
            'user.required' => 'کاربر را انتخاب نمایید',
            'month.required' => 'ماه را انتخاب نمایید'
        ]);


        $v = verta();

        switch ($request->input('month')) {
            case 'فروردین':
                $startDate = $v->startYear()->format('Y/m/d');
                $endDate = $v->year . '/01/31';
                break;
            case 'اردیبهشت':
                $startDate = $v->year . '/02/01';
                $endDate = $v->year . '/02/31';
                break;
            case 'خرداد':
                $startDate = $v->year . '/03/01';
                $endDate = $v->year . '/03/31';
                break;
            case 'تیر':
                $startDate = $v->year . '/04/01';
                $endDate = $v->year . '/04/31';
                break;
            case 'مرداد':
                $startDate = $v->year . '/05/01';
                $endDate = $v->year . '/05/31';
                break;
            case 'شهریور':
                $startDate = $v->year . '/06/01';
                $endDate = $v->year . '/06/31';
                break;
            case 'مهر':
                $startDate = $v->year . '/07/01';
                $endDate = $v->year . '/07/30';
                break;
            case 'آبان':
                $startDate = $v->year . '/08/01';
                $endDate = $v->year . '/08/30';
                break;
            case 'آذر':
                $startDate = $v->year . '/09/01';
                $endDate = $v->year . '/09/30';
                break;
            case 'دی':
                $startDate = $v->year . '/10/01';
                $endDate = $v->year . '/10/30';
                break;
            case 'بهمن':
                $startDate = $v->year . '/11/01';
                $endDate = $v->year . '/11/30';
                break;
            case 'اسفند':
                $startDate = $v->year . '/12/01';
                $endDate = $v->endYear()->format('Y/m/d');
                break;
            default:
                return $this->apiController->respondError('نوع فاکتور را انتخاب نمایید');
        }


        if ($startDate != null) {
            $startDate = Verta::parse($startDate)->datetime();
        }

        if ($endDate != null) {
            $endDate = Verta::parse($endDate)->datetime();
        }


        $userId = User::findOrFail($request->input('user'))->id;

        if ((Targeting::where('user', $userId)->whereBetween('start_date', [$startDate, $endDate])->first())
            ||
            (Targeting::where('user', $userId)->whereBetween('end_date', [$startDate, $endDate])->first())) {
            return $this->apiController->respondValidationError('هدف گذاری برای این کاربر در تاریخ های انتخاب شده موجود است.');
        }


        $monthYear = $request->input('month') . ' ' . $v->year;

        $create = Targeting::create([
            'user' => $userId,
            'month' => $monthYear,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 0,
            'sales' => $this->apiHelper->getRealPrice($request->input('sales')),
            'calls' => $request->input('calls'),
            'user_id' => auth()->id()
        ]);

        if ($create and $create instanceof Targeting) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }

    }


    public function show(Targeting $targeting)
    {
        if (auth()->user()->role !== User::ADMIN && $targeting->user !== auth()->id())
            return $this->apiController->respondForbidden();

        return $this->apiController->respondData($targeting);
    }


    public function update(Request $request)
    {
        if (auth()->user()->role !== User::ADMIN)
            return $this->apiController->respondForbidden();

        $data = Targeting::findOrFail($request->input('id'));

        $update = $data->update([
            'sales' => $this->apiHelper->getRealPrice($request->input('sales')),
            'calls' => $request->input('calls'),
            'user_id' => auth()->id()
        ]);

        if ($update) {
            return $this->apiController->respondUpdated();
        } else {
            return $this->apiController->respondInternalError();
        }


    }

    public function destroy(Request $request)
    {
        if (auth()->user()->role !== User::ADMIN)
            return $this->apiController->respondForbidden();

        $data = Targeting::findOrFail($request->input('id'));
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }


}
