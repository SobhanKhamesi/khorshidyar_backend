<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Call;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\SourceCategory;
use App\Models\Targeting;
use App\Models\User;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;

class ChartsController extends Controller
{

    private ApiController $apiController;
    private ApiHelper $apiHelper;

    private const MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }

    /**
     * اعمال بازه تاریخ شمسی startDate/endDate درخواست روی ستون داده شده؛
     * اگر هیچ‌کدام ارسال نشده باشد ۳۰ روز اخیر در نظر گرفته می‌شود.
     */
    private function applyDateRange($query, Request $request, string $column)
    {
        $start = $request->input('startDate') ? Verta::parse($request->input('startDate'))->datetime() : null;
        $end = $request->input('endDate') ? Verta::parse($request->input('endDate'))->datetime() : null;

        if ($start && $end)
            return $query->whereBetween($column, [$start, $end]);
        if ($start)
            return $query->whereDate($column, '>=', $start);
        if ($end)
            return $query->whereDate($column, '<=', $end);

        return $query->whereBetween($column, [Carbon::now()->subDays(30), Carbon::now()]);
    }

    private function onlyMine($query)
    {
        return $query->when(auth()->user()->role === User::KARSHENAS_FANI, fn($q) => $q->where('user_id', auth()->id()));
    }

    public function getProducts()
    {
        $collection = Order::query()
            ->whereHas('automation', fn($query) => $query->where('status', Automation::SUCCESS))
            ->toBase()
            ->groupBy('product_id')
            ->selectRaw('SUM(count) as total, product_id')
            ->orderByDesc('total')
            ->get();

        $names = Product::whereIn('id', $collection->pluck('product_id'))->pluck('name', 'id');

        $products = [];
        $counts = [];
        foreach ($collection as $row) {
            if (isset($names[$row->product_id])) {
                $products[] = $names[$row->product_id];
                $counts[] = $row->total;
            }
        }

        return $this->apiController->respondData([
            'product' => $products,
            'count' => $counts
        ]);
    }

    // تعداد اتوماسیون‌های ثبت شده به تفکیک روز (یا ماه در صورت ارسال monthly)
    public function getInvoice(Request $request)
    {
        $monthly = (bool)$request->input('monthly');

        $createdDates = $this->applyDateRange(Automation::query()->toBase(), $request, 'created_at')
            ->pluck('created_at');

        $chart = [];
        foreach ($createdDates as $createdAt) {
            $key = (new Verta($createdAt))->format($monthly ? 'Y/m' : 'Y/m/d');
            $chart[$key] = ($chart[$key] ?? 0) + 1;
        }

        ksort($chart);

        return $this->apiController->respondData([
            'date' => array_keys($chart),
            'count' => array_values($chart),
        ]);
    }

    public function getSeller(Request $request)
    {
        $list = $this->applyDateRange(Automation::query()->toBase(), $request, 'created_at')
            ->where('status', Automation::SUCCESS)
            ->groupBy('user_id')
            ->selectRaw('sum(final_price) as total, user_id')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        $names = User::whereIn('id', $list->pluck('user_id'))->pluck('name', 'id');

        $users = [];
        $counts = [];
        foreach ($list as $row) {
            if (isset($names[$row->user_id])) {
                $users[] = $names[$row->user_id];
                $counts[] = $row->total;
            }
        }

        return [
            'user' => $users,
            'count' => $counts
        ];
    }

    // فروش هر کاربر در یک ماه شمسی در مقایسه با هدف تعیین شده برای او
    public function getTargeting(Request $request)
    {
        $now = verta();

        // ورودی به شکل «مهر 1405»؛ در صورت عدم ارسال، ماه جاری
        $year = $now->year;
        $month = $now->month;
        if ($request->input('date')) {
            [$monthName, $requestedYear] = array_pad(explode(' ', trim($request->input('date')), 2), 2, null);
            $requestedMonth = array_search($monthName, self::MONTHS, true);
            if ($requestedMonth !== false && is_numeric($requestedYear)) {
                $month = $requestedMonth;
                $year = (int)$requestedYear;
            }
        }
        $selectedDate = self::MONTHS[$month] . ' ' . $year;

        $monthStart = Verta::createJalali($year, $month, 1, 0, 0, 0);
        $startDate = $monthStart->datetime();
        $endDate = (clone $monthStart)->endMonth()->datetime();

        $sales = Automation::query()->toBase()
            ->where('status', Automation::SUCCESS)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('user_id')
            ->selectRaw('sum(final_price) as total, user_id')
            ->having('total', '>', 0)
            ->get();

        $userIds = $sales->pluck('user_id');
        $names = User::whereIn('id', $userIds)->pluck('name', 'id');

        $targets = Targeting::query()->toBase()
            ->whereIn('user', $userIds)
            ->where('month', $selectedDate)
            ->whereDate('start_date', '>=', $startDate)
            ->whereDate('end_date', '<=', $endDate)
            ->groupBy('user')
            ->selectRaw('sum(sales) as total, user')
            ->pluck('total', 'user');

        $users = [];
        $forosh = [];
        $mande = [];
        foreach ($sales as $row) {
            if (!isset($names[$row->user_id]))
                continue;

            $amount = intval($row->total);
            $users[] = $names[$row->user_id];
            $forosh[] = $amount;
            $mande[] = max(intval($targets[$row->user_id] ?? 0) - $amount, 0);
        }

        $dates = Targeting::query()->toBase()
            ->groupBy('month')
            ->selectRaw('month, min(start_date) as first_start')
            ->orderBy('first_start')
            ->pluck('month')
            ->all();

        return $this->apiController->respondData([
            'current' => self::MONTHS[$now->month] . ' ' . $now->year,
            'dates' => array_reverse($dates),
            'users' => $users,
            'forosh' => $forosh,
            'mande' => $mande
        ]);
    }

    public function getMethod()
    {
        $collection = $this->onlyMine(Customer::query()->toBase())
            ->groupBy('source_id')
            ->selectRaw('count(*) as total, source_id')
            ->orderByDesc('total')
            ->get();

        $titles = SourceCategory::whereIn('id', $collection->pluck('source_id')->filter())->pluck('title', 'id');

        $source = [];
        $percent = [];
        foreach ($collection as $row) {
            if (isset($titles[$row->source_id])) {
                $source[] = $titles[$row->source_id];
                $percent[] = (int)$row->total;
            }
        }

        return $this->apiController->respondData([
            'source' => $source,
            'percent' => $percent
        ]);
    }

    public function getStatus(Request $request)
    {
        $list = $this->applyDateRange($this->onlyMine(Customer::query()->toBase()), $request, 'created_at')
            ->groupBy(['user_id', 'lead_status'])
            ->selectRaw('count(`lead_status`) as total, user_id, `lead_status`')
            ->get();

        return response()->json(['chartsData' => DashboardController::leadStatusChart($list)]);
    }

    public function getReport(Request $request)
    {
        $collection = $this->applyDateRange(Call::query()->toBase(), $request, 'created_at')
            ->groupBy('user_id')
            ->selectRaw('COUNT(*) as total, user_id')
            ->orderByDesc('user_id')
            ->get();

        $names = User::whereIn('id', $collection->pluck('user_id'))->pluck('name', 'id');

        $call = [];
        $count = [];
        foreach ($collection as $row) {
            if (isset($names[$row->user_id])) {
                $call[] = $names[$row->user_id];
                $count[] = $row->total;
            }
        }

        return response()->json([
            'data' => [
                'call' => $call,
                'count' => $count
            ]
        ]);
    }
}
