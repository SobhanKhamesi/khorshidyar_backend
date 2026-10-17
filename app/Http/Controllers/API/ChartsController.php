<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Call;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SourceCategory;
use App\Models\Targeting;
use App\Models\User;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;

class ChartsController extends Controller
{

    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }


    public function getProducts()
    {
        $collectionProducts = Order::query()
            ->whereHas('automation', function ($query) {
                $query->where('status', Automation::SUCCESS);
            })
            ->groupBy('product_id')
            ->selectRaw('SUM(count) as total, product_id')
            ->orderByDesc('total')
            ->get();

        $products = [];
        $counts = [];

        foreach ($collectionProducts as $collection) {
            if ($collection->product) {
                $products[] = $collection->product->name;
                $counts[] = $collection->total;
            }
        }


        return $this->apiController->respondData([
            'product' => $products,
            'count' => $counts
        ]);

    }

    public function getInvoice(Request $request)
    {

        $startDate = $request->input('startDate');
        $endDate = $request->input('endDate');
        $type = $request->input('monthly');

        $convertedStartDate = $startDate ? Verta::parse($startDate)->datetime() : null;
        $convertedEndDate = $endDate ? Verta::parse($endDate)->datetime() : null;

        $collectionProfitFilteredSales = Automation::query();

        if ($convertedStartDate && $convertedEndDate) {
            $collectionProfitFilteredSales->whereBetween('date', [$convertedStartDate, $convertedEndDate]);
        } elseif ($convertedStartDate && !$convertedEndDate) {
            $collectionProfitFilteredSales->whereDate('date', '>=', $convertedStartDate);
        } elseif (!$convertedStartDate && $convertedEndDate) {
            $collectionProfitFilteredSales->whereDate('date', '<=', $convertedEndDate);
        } else {
            $collectionProfitFilteredSales->whereBetween('date', [Carbon::now()->subDays(30), Carbon::now()]);
        }


        $dates = [];
        $counts = [];
        $chartInvoice = [];

        foreach ($collectionProfitFilteredSales->get() as $item) {
            $jalaliDate = $item->jalali_date;
            $chartInvoice[$jalaliDate] = $chartInvoice[$jalaliDate] ?? ['count' => 0];
            $chartInvoice[$jalaliDate]['count']++;
        }

        ksort($chartInvoice);

        $chartInvoiceMonthAndYear = [];

        foreach ($chartInvoice as $key => $val) {
            if ($val['count'] !== 0) {
                if ($type) {
                    $monthAndYear = implode('/', explode('/', $key, 2));
                    $chartInvoiceMonthAndYear[$monthAndYear] = $chartInvoiceMonthAndYear[$monthAndYear] ?? ['count' => 0];
                    $chartInvoiceMonthAndYear[$monthAndYear]['count'] += $val['count'];
                } else {
                    $dates[] = $key;
                    $counts[] = $val['count'];
                }
            }
        }

        if ($type) {
            foreach ($chartInvoiceMonthAndYear as $key => $val) {
                $dates[] = $key;
                $counts[] = $val['count'];
            }
        }

        $invoicesChart = [
            'date' => $dates ?? [],
            'count' => $counts ?? []
        ];

        return $this->apiController->respondData($invoicesChart);

    }

    public function getSeller(Request $request)
    {
        $startDate = $request ? $request->input('startDate') : null;
        $endDate = $request ? $request->input('endDate') : null;


        if ($startDate != null and $endDate != null) {
            $convertedStartDate = Verta::parse($request->input('startDate'))->datetime();
            $convertedEndDate = Verta::parse($request->input('endDate'))->datetime();


            $list = Automation::where('status', Automation::SUCCESS)->
            whereBetween('created_at', [$convertedStartDate, $convertedEndDate])
                ->groupBy('user_id')
                ->selectRaw('sum(final_price) as total, user_id')
                ->orderBy('total', 'DESC')
                ->take(5)
                ->get();

        } elseif ($startDate != null and $endDate == null) {
            $convertedStartDate = Verta::parse($request->input('startDate'))->datetime();

            $list = Automation::where('status', Automation::SUCCESS)->
            whereDate('created_at', '>=', $convertedStartDate)
                ->groupBy('user_id')
                ->selectRaw('sum(final_price) as total, user_id')
                ->orderBy('total', 'DESC')
                ->take(5)
                ->get();


        } elseif ($startDate == null and $endDate != null) {
            $convertedEndDate = Verta::parse($request->input('endDate'))->datetime();

            $list = Automation::where('status', Automation::SUCCESS)->
            whereDate('created_at', '<=', $convertedEndDate)
                ->groupBy('user_id')
                ->selectRaw('sum(final_price) as total, user_id')
                ->orderBy('total', 'DESC')
                ->take(5)
                ->get();


        } else {
            $list = Automation::where('status', Automation::SUCCESS)->
            whereBetween('created_at', [Carbon::now()->subDays(30), Carbon::now()])
                ->groupBy('user_id')
                ->selectRaw('sum(final_price) as total, user_id')
                ->orderBy('total', 'DESC')
                ->take(5)
                ->get();


        }


        $users = [];
        $counts = [];

        foreach ($list as $collection) {
            if ($collection->user) {
                $users[] = $collection->user->name;
                $counts[] = $collection->total;
            }
        }

        return [
            'user' => $users,
            'count' => $counts
        ];


    }

    public function getTargeting(Request $request)
    {

        $v = verta();
        $selectedMonth = null;
        $startDate = null;
        $endDate = null;


        if ($request->has('date') && $request->input('date') != null) {
            $requestedDate = $selectedDate = $request->input('date');
            $requestedMonth = explode(' ', $requestedDate)[0];
            $requestedYear = explode(' ', $requestedDate)[1];
            switch ($requestedMonth) {
                case 'فروردین':
                    $startDate = $requestedYear . '/01/01';
                    $endDate = $requestedYear . '/01/31';
                    break;
                case 'اردیبهشت':
                    $startDate = $requestedYear . '/02/01';
                    $endDate = $requestedYear . '/02/31';
                    break;
                case 'خرداد':
                    $startDate = $requestedYear . '/03/01';
                    $endDate = $requestedYear . '/03/31';
                    break;
                case 'تیر':
                    $startDate = $requestedYear . '/04/01';
                    $endDate = $requestedYear . '/04/31';
                    break;
                case 'مرداد':
                    $startDate = $requestedYear . '/05/01';
                    $endDate = $requestedYear . '/05/31';
                    break;
                case 'شهریور':
                    $startDate = $requestedYear . '/06/01';
                    $endDate = $requestedYear . '/06/31';
                    break;
                case 'مهر':
                    $startDate = $requestedYear . '/07/01';
                    $endDate = $requestedYear . '/07/30';
                    break;
                case 'آبان':
                    $startDate = $requestedYear . '/08/01';
                    $endDate = $requestedYear . '/08/30';
                    break;
                case 'آذر':
                    $startDate = $requestedYear . '/09/01';
                    $endDate = $requestedYear . '/09/30';
                    break;
                case 'دی':
                    $startDate = $requestedYear . '/10/01';
                    $endDate = $requestedYear . '/10/30';
                    break;
                case 'بهمن':
                    $startDate = $requestedYear . '/11/01';
                    $endDate = $requestedYear . '/11/30';
                    break;
                case 'اسفند':
                    $startDate = $requestedYear . '/12/01';
                    $endDate = $requestedYear . '/12/30';
                    break;
            }


        } else {
            switch ($v->month) {
                case '1':
                    $selectedMonth = 'فروردین';
                    $startDate = $v->startYear()->format('Y/m/d');
                    $endDate = $v->year . '/01/31';
                    break;
                case '2':
                    $startDate = $v->year . '/02/01';
                    $endDate = $v->year . '/02/31';
                    $selectedMonth = 'اردیبهشت';
                    break;
                case '3':
                    $startDate = $v->year . '/03/01';
                    $endDate = $v->year . '/03/31';
                    $selectedMonth = 'خرداد';
                    break;
                case '4':
                    $startDate = $v->year . '/04/01';
                    $endDate = $v->year . '/04/31';
                    $selectedMonth = 'تیر';
                    break;
                case '5':
                    $startDate = $v->year . '/05/01';
                    $endDate = $v->year . '/05/31';
                    $selectedMonth = 'مرداد';
                    break;
                case '6':
                    $startDate = $v->year . '/06/01';
                    $endDate = $v->year . '/06/31';
                    $selectedMonth = 'شهریور';
                    break;
                case '7':
                    $startDate = $v->year . '/07/01';
                    $endDate = $v->year . '/07/30';
                    $selectedMonth = 'مهر';
                    break;
                case '8':
                    $startDate = $v->year . '/08/01';
                    $endDate = $v->year . '/08/30';
                    $selectedMonth = 'آبان';
                    break;
                case '9':
                    $startDate = $v->year . '/09/01';
                    $endDate = $v->year . '/09/30';
                    $selectedMonth = 'آذر';
                    break;
                case '10':
                    $startDate = $v->year . '/10/01';
                    $endDate = $v->year . '/10/30';
                    $selectedMonth = 'دی';
                    break;
                case '11':
                    $startDate = $v->year . '/11/01';
                    $endDate = $v->year . '/11/30';
                    $selectedMonth = 'بهمن';
                    break;
                case '12':
                    $startDate = $v->year . '/12/01';
                    $endDate = $v->endYear()->format('Y/m/d');
                    $selectedMonth = 'اسفند';
                    break;
            }
            $selectedDate = $selectedMonth . ' ' . $v->year;
        }


        if ($startDate != null)
            $startDate = Verta::parse($startDate)->datetime();
        if ($endDate != null)
            $endDate = Verta::parse($endDate)->datetime();


        if ($startDate != null and $endDate != null) {

            $sales = Automation::query()
                ->groupBy('user_id')
                ->selectRaw('sum(final_price) as total,user_id')
                ->whereBetween('date', [$startDate, $endDate])
                ->get();


        } elseif ($startDate != null and $endDate == null) {


            $sales = Automation::query()
                ->groupBy('user_id')
                ->selectRaw('sum(final_price) as total,user_id')
                ->whereDate('date', '>=', $startDate)
                ->get();


        } elseif ($startDate == null and $endDate != null) {


            $sales = Automation::query()
                ->groupBy('user_id')
                ->selectRaw('sum(final_price) as total,user_id')
                ->whereDate('date', '<=', $endDate)
                ->get();

        } else {

            $currentStartMonthDay = verta()->startMonth();
            $currentEndMonthDay = verta()->endMonth()->addDay();
            $currentStartDate = Verta::getGregorian($currentStartMonthDay->year, $currentStartMonthDay->month, $currentStartMonthDay->day);
            $currentEndDate = Verta::getGregorian($currentEndMonthDay->year, $currentEndMonthDay->month, $currentEndMonthDay->day);

            $convertedCurrentStartDate = $currentStartDate[0] . '-' . $currentStartDate[1] . '-' . $currentStartDate[2];
            $convertedCurrentEndDate = $currentEndDate[0] . '-' . $currentEndDate[1] . '-' . $currentEndDate[2];


            $sales = Automation::query()
                ->whereBetween('date', [$convertedCurrentStartDate, $convertedCurrentEndDate])
                ->groupBy('user_id')
                ->selectRaw('sum(final_price) as total,user_id')
                ->get();

        }

        $targetingInvoice = [];


        foreach ($sales as $collection) {
            $amount = $collection->total;
            if ($amount > 0) {
                if (array_key_exists($collection->user_id, $targetingInvoice)) {
                    $targetingInvoice[$collection->user_id]['amount'] += $amount;
                } else {
                    $targetingInvoice[$collection->user_id] = [
                        'amount' => $amount,
                        'username' => User::findOrFail($collection->user_id)->name,
                    ];
                }
            }
        }

        $users = [];
        $forosh = [];
        $mande = [];

        foreach ($targetingInvoice as $key => $val) {
            $target = null;
            $users[] = $val['username'];
            $target = Targeting::where('user', $key)->where('month', $selectedDate);

            if (isset($startDate) && isset($endDate)) {


                $target->whereDate('start_date', '>=', $startDate)
                    ->whereDate('end_date', '<=', $endDate);


            } else if (isset($startDate) && !isset($endDate)) {


                $target->whereDate('start_date', '>=', $startDate);


            } else if (!isset($startDate) && isset($endDate)) {
                $target->whereDate('end_date', '<=', $endDate);


            } else {
                $currentStartMonthDay = verta()->startMonth();
                $currentEndMonthDay = verta()->endMonth()->addDay();
                $currentStartDate = Verta::getGregorian($currentStartMonthDay->year, $currentStartMonthDay->month, $currentStartMonthDay->day);
                $currentEndDate = Verta::getGregorian($currentEndMonthDay->year, $currentEndMonthDay->month, $currentEndMonthDay->day);

                $convertedCurrentStartDate = $currentStartDate[0] . '-' . $currentStartDate[1] . '-' . $currentStartDate[2];
                $convertedCurrentEndDate = $currentEndDate[0] . '-' . $currentEndDate[1] . '-' . $currentEndDate[2];


                $target->whereDate('start_date', '>=', $convertedCurrentStartDate)->whereDate('end_date', '<', $convertedCurrentEndDate);

            }

            $mande[] = intval($target->sum('sales')) - intval($val['amount']) > 0 ? intval($target->sum('sales')) - intval($val['amount']) : 0;
            $forosh[] = intval($val['amount']);

        }

        $dates = Targeting::groupBy('month')->get();
        $datesArr = [];
        foreach ($dates as $item) {
            $datesArr[] = $item->month;
        }


        $v = verta();
        switch ($v->month) {
            case '1':
                $selectedMonth = 'فروردین';
                break;
            case '2':
                $selectedMonth = 'اردیبهشت';
                break;
            case '3':
                $selectedMonth = 'خرداد';
                break;
            case '4':
                $selectedMonth = 'تیر';
                break;
            case '5':
                $selectedMonth = 'مرداد';
                break;
            case '6':
                $selectedMonth = 'شهریور';
                break;
            case '7':
                $selectedMonth = 'مهر';
                break;
            case '8':
                $selectedMonth = 'آبان';
                break;
            case '9':
                $selectedMonth = 'آذر';
                break;
            case '10':
                $selectedMonth = 'دی';
                break;
            case '11':
                $selectedMonth = 'بهمن';
                break;
            case '12':
                $selectedMonth = 'اسفند';
                break;
        }


        $current = $selectedMonth . ' ' . $v->year;

        $targetingChart = [
            'current' => $current,
            'dates' => array_reverse($datesArr),
            'users' => $users,
            'forosh' => $forosh,
            'mande' => $mande
        ];


        return $this->apiController->respondData($targetingChart);

    }


    public function getMethod()
    {

        if (auth()->user()->role === User::KARSHENAS_FANI) {
            $collectionCustomersSource = Customer::where('user_id', auth()->id())->groupBy('source_id')->selectRaw('count(*) as total,source_id')->orderby('total', 'DESC')->get();
        } else {
            $collectionCustomersSource = Customer::groupBy('source_id')->selectRaw('count(*) as total,source_id')->orderby('total', 'DESC')->get();
        }

        $source = [];
        $percent = [];

        $all = $collectionCustomersSource->sum('total');


        foreach ($collectionCustomersSource as $collection) {
            $sourceItem = SourceCategory::find($collection->source_id);
            if ($sourceItem) {
                $source[] = $sourceItem->title;
                $percent[] = (int)$collection->total;
            }
        }


        $methodchart = [
            'source' => $source,
            'percent' => $percent
        ];
        return $this->apiController->respondData($methodchart);
    }

    public function getStatus(Request $request)
    {
        $startDate = $request ? $request->input('startDate') : null;
        $endDate = $request ? $request->input('endDate') : null;


        if ($startDate != null and $endDate != null) {
            $convertedStartDate = Verta::parse($request->input('startDate'))->datetime();
            $convertedEndDate = Verta::parse($request->input('endDate'))->datetime();


            if (auth()->user()->role === User::KARSHENAS_FANI) {
                $list = Customer::where('user_id', auth()->id())
                    ->whereBetween('created_at', [$convertedStartDate, $convertedEndDate])
                    ->groupBy(['user_id', 'lead_status'])
                    ->selectRaw('count(`lead_status`) as total, user_id, `lead_status`')
                    ->get();
            } else {
                $list = Customer::
                whereBetween('created_at', [$convertedStartDate, $convertedEndDate])
                    ->groupBy(['user_id', 'lead_status'])
                    ->selectRaw('count(`lead_status`) as total, user_id, `lead_status`')
                    ->get();
            }


        } elseif ($startDate != null and $endDate == null) {
            $convertedStartDate = Verta::parse($request->input('startDate'))->datetime();

            if (auth()->user()->role === User::KARSHENAS_FANI) {
                $list = Customer::where('user_id', auth()->id())
                    ->whereDate('created_at', '>=', $convertedStartDate)
                    ->groupBy(['user_id', 'lead_status'])
                    ->selectRaw('count(`lead_status`) as total, user_id, `lead_status`')
                    ->get();
            } else {
                $list = Customer::
                whereDate('created_at', '>=', $convertedStartDate)
                    ->groupBy(['user_id', 'lead_status'])
                    ->selectRaw('count(`lead_status`) as total, user_id, `lead_status`')
                    ->get();
            }


        } elseif ($startDate == null and $endDate != null) {
            $convertedEndDate = Verta::parse($request->input('endDate'))->datetime();

            if (auth()->user()->role === User::KARSHENAS_FANI) {
                $list = Customer::where('user_id', auth()->id())
                    ->whereDate('created_at', '<=', $convertedEndDate)
                    ->groupBy(['user_id', 'lead_status'])
                    ->selectRaw('count(`lead_status`) as total, user_id, `lead_status`')
                    ->get();
            } else {
                $list = Customer::whereDate('created_at', '<=', $convertedEndDate)
                    ->groupBy(['user_id', 'lead_status'])
                    ->selectRaw('count(`lead_status`) as total, user_id, `lead_status`')
                    ->get();
            }


        } else {

            if (auth()->user()->role === User::KARSHENAS_FANI) {
                $list = Customer::where('user_id' , auth()->id())
                    ->whereBetween('created_at', [Carbon::now()->subDays(30), Carbon::now()])
                    ->groupBy(['user_id', 'lead_status'])
                    ->selectRaw('count(`lead_status`) as total, user_id, `lead_status`')
                    ->get();
            } else {
                $list = Customer::whereBetween('created_at', [Carbon::now()->subDays(30), Carbon::now()])
                    ->groupBy(['user_id', 'lead_status'])
                    ->selectRaw('count(`lead_status`) as total, user_id, `lead_status`')
                    ->get();
            }
        }


        $statusOptions = [
            ['label' => 'تمایل به خرید', 'value' => 0],
            ['label' => 'نیاز به پیگیری', 'value' => 1],
            ['label' => 'عدم تمایل به خرید', 'value' => 2],
            ['label' => 'عدم پاسخگویی', 'value' => 3],
            ['label' => 'تماس گرفته نشد', 'value' => 4]
        ];

        $users = User::pluck('name', 'id')->toArray();
        $statuses = array_column($statusOptions, 'label', 'value');


        $data = [];


        foreach ($list as $collection) {
            $userId = $collection->user_id;
            $lead_status = $collection->lead_status;
            $total = $collection->total;

            if (!isset($data[$users[$userId]])) {
                $data[$users[$userId]] = array_fill_keys(array_keys($statuses), 0);
            }

            $data[$users[$userId]][$lead_status] = $total;
        }
        return response()->json(['chartsData' => $data]);

    }


    public $call = [];
    public $count = [];

    public function getReport(Request $request)
    {

        $startDate = $request ? $request->input('startDate') : null;
        $endDate = $request ? $request->input('endDate') : null;


        if ($startDate != null and $endDate != null) {
            $convertedStartDate = Verta::parse($request->input('startDate'))->datetime();
            $convertedEndDate = Verta::parse($request->input('endDate'))->datetime();

            $collectionCalls = Call::
            whereBetween('created_at', [$convertedStartDate, $convertedEndDate])
                ->groupBy('user_id')
                ->selectRaw('COUNT(*) as total, user_id')
                ->orderBy('user_id', 'desc')
                ->get();

        } elseif ($startDate != null and $endDate == null) {
            $convertedStartDate = Verta::parse($request->input('startDate'))->datetime();

            $collectionCalls = Call::
            whereDate('created_at', '>=', $convertedStartDate)
                ->groupBy('user_id')
                ->selectRaw('COUNT(*) as total, user_id')
                ->orderBy('user_id', 'desc')
                ->get();


        } elseif ($startDate == null and $endDate != null) {
            $convertedEndDate = Verta::parse($request->input('endDate'))->datetime();

            $collectionCalls = Call::whereDate('created_at', '<=', $convertedEndDate)
                ->groupBy('user_id')
                ->selectRaw('COUNT(*) as total, user_id')
                ->orderBy('user_id', 'desc')
                ->get();


        } else {
            $collectionCalls = Call::whereBetween('created_at', [Carbon::now()->subDays(30), Carbon::now()])
                ->groupBy('user_id')
                ->selectRaw('COUNT(*) as total, user_id')
                ->orderBy('user_id', 'desc')
                ->get();


        }

        foreach ($collectionCalls as $collection) {

            $user = User::find($collection->user_id);

            $this->call[] = $user->name;
            $this->count[] = $collection->total;

        }


        return response()->json([
            'data' => [
                'call' => $this->call,
                'count' => $this->count
            ]
        ]);
    }
}
