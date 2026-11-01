<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Call;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\FailureCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\SourceCategory;
use App\Models\User;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;

/**
 * کوئری‌های گروهی این کنترلر با toBase اجرا می‌شوند تا مدل‌ها و روابط $with آن‌ها
 * بی‌دلیل ساخته و بارگذاری نشوند؛ عناوین هم با یک کوئری (pluck) گرفته می‌شوند نه find در حلقه.
 */
class DashboardController extends Controller
{

    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }

    private function isTechnicalExpert(): bool
    {
        return auth()->user()->role === User::KARSHENAS_FANI;
    }

    public function getWidgets()
    {
        $onlyMine = $this->isTechnicalExpert();
        $userId = auth()->id();

        $customers = Customer::query()->toBase()
            ->when($onlyMine, fn($q) => $q->where('user_id', $userId))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sales = Automation::query()->toBase()
            ->when($onlyMine, fn($q) => $q->where('user_id', $userId))
            ->whereIn('status', [Automation::SUCCESS, Automation::FAILURE])
            ->selectRaw('status, count(*) as total, sum(final_price) as price')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $data = [
            'customers_count' => (int)($customers[Customer::CUSTOMER] ?? 0) + (int)($customers[Customer::TO] ?? 0),
            'leads_count' => (int)($customers[Customer::LEAD] ?? 0),
            'opportunities_count' => (int)($customers[Customer::OPPORTUNITY] ?? 0),
            'call_tracking_count' => Call::query()->when($onlyMine, fn($q) => $q->where('user_id', $userId))->count(),
            'successful_sales' => (int)($sales[Automation::SUCCESS]->total ?? 0),
            'price_successful_sales' => $sales[Automation::SUCCESS]->price ?? 0,
            'unsuccessful_sales' => (int)($sales[Automation::FAILURE]->total ?? 0),
            'price_unsuccessful_sales' => $sales[Automation::FAILURE]->price ?? 0,
        ];

        return $this->apiController->respondData($data);
    }

    public function getCharts()
    {
        $data = [
            'invoices' => $this->getInvoice(),
            'top_products' => $this->getTopProducts(),
            'customers_categories' => $this->getCustomersCategories(),
            'source' => $this->getSource(),
            'status' => $this->getStatus(),
            'failure_Categories' => $this->getFailureCategories(),
            'best_customer' => $this->getBestCuctomer(),
            'top_seller' => $this->getTopSeller(),
            'top_user' => $this->getReport(),
            'sales_source' => $this->getSalesSource(),
        ];

        return $this->apiController->respondData($data);
    }

    // تعداد فروش‌های موفق ۳۰ روز اخیر به تفکیک روز
    public function getInvoice()
    {
        $createdDates = Automation::query()->toBase()
            ->where('status', Automation::SUCCESS)
            ->whereNotNull('final_price')
            ->whereDate('created_at', '>=', Carbon::now()->subDays(30))
            ->pluck('created_at');

        $chartInvoice = [];
        foreach ($createdDates as $createdAt) {
            $day = (new Verta($createdAt))->format('Y/m/d');
            $chartInvoice[$day] = ($chartInvoice[$day] ?? 0) + 1;
        }

        ksort($chartInvoice);

        return [
            'date' => array_keys($chartInvoice),
            'count' => array_values($chartInvoice),
        ];
    }

    public function getCustomersCategories()
    {
        $collection = Customer::query()->toBase()
            ->when($this->isTechnicalExpert(), fn($q) => $q->where('user_id', auth()->id()))
            ->groupBy('category_id')
            ->selectRaw('count(*) as total, category_id')
            ->orderByDesc('total')
            ->get();

        $titles = CustomerCategory::whereIn('id', $collection->pluck('category_id')->filter())->pluck('title', 'id');

        $category = [];
        $percent = [];
        foreach ($collection as $row) {
            if (isset($titles[$row->category_id])) {
                $category[] = $titles[$row->category_id];
                $percent[] = (int)$row->total;
            }
        }

        return compact('category', 'percent');
    }

    public function getTopProducts()
    {
        $collection = Order::query()->toBase()
            ->groupBy('product_id')
            ->selectRaw('sum(count) as total, product_id')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        $names = Product::whereIn('id', $collection->pluck('product_id'))->pluck('name', 'id');

        $product = [];
        $count = [];
        foreach ($collection as $row) {
            if (isset($names[$row->product_id])) {
                $product[] = $names[$row->product_id];
                $count[] = $row->total;
            }
        }

        return [
            'product' => $product,
            'count' => $count
        ];
    }

    public function getSource()
    {
        $collection = Customer::query()->toBase()
            ->when($this->isTechnicalExpert(), fn($q) => $q->where('user_id', auth()->id()))
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

        return compact('source', 'percent');
    }

    public function getStatus()
    {
        $list = Customer::query()->toBase()
            ->when($this->isTechnicalExpert(), fn($q) => $q->where('user_id', auth()->id()))
            ->whereBetween('created_at', [Carbon::now()->subDays(30), Carbon::now()])
            ->groupBy(['user_id', 'lead_status'])
            ->selectRaw('count(`lead_status`) as total, user_id, `lead_status`')
            ->get();

        return response()->json(['chartsData' => self::leadStatusChart($list)]);
    }

    /**
     * ساخت داده نمودار وضعیت سرنخ‌ها به تفکیک کاربر (مشترک با ChartsController)
     */
    public static function leadStatusChart($list): array
    {
        $statuses = [
            0 => 'تمایل به خرید',
            1 => 'نیاز به پیگیری',
            2 => 'عدم تمایل به خرید',
            3 => 'عدم پاسخگویی',
            4 => 'تماس گرفته نشد',
        ];

        $users = User::whereIn('id', collect($list)->pluck('user_id')->unique())->pluck('name', 'id');

        $data = [];
        foreach ($list as $row) {
            $userName = $users[$row->user_id] ?? null;
            if ($userName === null)
                continue;

            if (!isset($data[$userName]))
                $data[$userName] = array_fill_keys(array_keys($statuses), 0);

            $data[$userName][$row->lead_status] = $row->total;
        }

        return $data;
    }

    public function getFailureCategories()
    {
        $collection = Automation::query()->toBase()
            ->groupBy('failure_id')
            ->selectRaw('count(*) as total, failure_id')
            ->orderByDesc('total')
            ->get();

        $titles = FailureCategory::whereIn('id', $collection->pluck('failure_id')->filter())->pluck('title', 'id');

        $failure = [];
        $percent = [];
        foreach ($collection as $row) {
            if (isset($titles[$row->failure_id])) {
                $failure[] = $titles[$row->failure_id];
                $percent[] = (int)$row->total;
            }
        }

        return [
            'failure' => $failure,
            'percent' => $percent
        ];
    }

    public function getBestCuctomer()
    {
        $collection = Automation::query()->toBase()
            ->where('status', Automation::SUCCESS)
            ->groupBy('customer_id')
            ->selectRaw('sum(final_price) as total, customer_id')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        $names = Customer::query()->toBase()->whereIn('id', $collection->pluck('customer_id'))->pluck('name', 'id');

        $customer = [];
        $count = [];
        foreach ($collection as $row) {
            if (isset($names[$row->customer_id])) {
                $customer[] = $names[$row->customer_id];
                $count[] = $row->total;
            }
        }

        return [
            'customer' => $customer,
            'count' => $count
        ];
    }

    public function getTopSeller()
    {
        $collection = Automation::query()->toBase()
            ->where('status', Automation::SUCCESS)
            ->groupBy('user_id')
            ->selectRaw('sum(final_price) as total, user_id')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        $names = User::whereIn('id', $collection->pluck('user_id'))->pluck('name', 'id');

        $user = [];
        $count = [];
        foreach ($collection as $row) {
            if (isset($names[$row->user_id])) {
                $user[] = $names[$row->user_id];
                $count[] = $row->total;
            }
        }

        return [
            'user' => $user,
            'count' => $count
        ];
    }

    // فقط برای مدیر محاسبه می‌شود
    public function getReport()
    {
        if (auth()->user()->role !== User::ADMIN)
            return null;

        $collection = Call::query()->toBase()
            ->groupBy('user_id')
            ->selectRaw('COUNT(*) as total, user_id')
            ->orderByDesc('total')
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

        return [
            'call' => $call,
            'count' => $count
        ];
    }

    public function getSalesSource()
    {
        $collection = Automation::query()->toBase()
            ->join('customers', 'automation.customer_id', '=', 'customers.id')
            ->join('source_categories', 'customers.source_id', '=', 'source_categories.id')
            ->where('automation.status', Automation::SUCCESS)
            ->groupBy('customers.source_id', 'source_categories.title')
            ->selectRaw('COUNT(*) as total, source_categories.title as title')
            ->orderByDesc('total')
            ->get();

        return [
            'salesSource' => $collection->pluck('title')->all(),
            'count' => $collection->pluck('total')->all(),
        ];
    }

}
