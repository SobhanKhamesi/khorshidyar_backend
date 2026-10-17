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
use App\Models\Source;
use App\Models\SourceCategory;
use App\Models\User;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;

class DashboardController extends Controller
{

    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }


    public function getWidgets()
    {
        if (auth()->user()->role === User::KARSHENAS_FANI) {
            $data = [
                'customers_count' => Customer::where('user_id', auth()->id())->whereIn('status', [Customer::CUSTOMER, Customer::TO])->count(),
                'leads_count' => Customer::where('user_id', auth()->id())->where('status', Customer::LEAD)->count(),
                'opportunities_count' => Customer::where('user_id', auth()->id())->where('status', Customer::OPPORTUNITY)->count(),
                'call_tracking_count' => Call::where('user_id', auth()->id())->count(),
                'successful_sales' => Automation::where('user_id', auth()->id())->where('status', 5)->count(),
                'price_successful_sales' => Automation::where('user_id', auth()->id())->where('status', 5)->sum('final_price'),
                'unsuccessful_sales' => Automation::where('user_id', auth()->id())->where('status', Automation::FAILURE)->count(),
                'price_unsuccessful_sales' => Automation::where('user_id', auth()->id())->where('status', Automation::FAILURE)->sum('final_price'),
            ];

        } else {
            $data = [
                'customers_count' => Customer::whereIn('status', [Customer::CUSTOMER, Customer::TO])->count(),
                'leads_count' => Customer::where('status', Customer::LEAD)->count(),
                'opportunities_count' => Customer::where('status', Customer::OPPORTUNITY)->count(),
                'call_tracking_count' => Call::count(),
                'successful_sales' => Automation::where('status', Automation::SUCCESS)->count(),
                'price_successful_sales' => Automation::where('status', Automation::SUCCESS)->sum('final_price'),
                'unsuccessful_sales' => Automation::where('status', Automation::FAILURE)->count(),
                'price_unsuccessful_sales' => Automation::where('status', Automation::FAILURE)->sum('final_price'),

            ];
        }

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


    public function getInvoice()
    {

        $collectionProfitFilteredSales = Automation::where('status', 5)->whereDate('created_at', '>=', Carbon::now()->subDays(30))->get();

        $dates = [];
        $counts = [];
        $chartInvoice = [];


        foreach ($collectionProfitFilteredSales as $item) {

            if ($item->final_price != null) {

                if (array_key_exists($item->jalali_date, $chartInvoice)) {
                    $chartInvoice[$item->jalali_date]['count']++;
                } else {
                    $chartInvoice[$item->jalali_date] = [
                        'count' => 1
                    ];
                }
            }
        }


        ksort($chartInvoice);


        foreach ($chartInvoice as $key => $val) {
            if ($val['count'] > 0 || $val['count'] < 0) {
                $dates[] = $key;
                $counts[] = $val['count'];
            }
        }


        return [
            'date' => $dates,
            'count' => $counts
        ];
    }

    public function getCustomersCategories()
    {
        if (auth()->user()->role === User::KARSHENAS_FANI) {
            $collectionCustomersCategories = Customer::where('user_id', auth()->id())
                ->groupBy('category_id')
                ->selectRaw('count(*) as total,category_id')
                ->orderbyDesc('total')
                ->get();
        } else {
            $collectionCustomersCategories = Customer::groupBy('category_id')
                ->selectRaw('count(*) as total,category_id')
                ->orderbyDesc('total')
                ->get();
        }

        $category = [];
        $percent = [];

        foreach ($collectionCustomersCategories as $collection) {
            $categoryItem = CustomerCategory::find($collection->category_id);
            if ($categoryItem) {
                $category[] = $categoryItem->title;
                $percent[] = (int)$collection->total;
            }
        }


        return compact('category', 'percent');

    }

    public function getTopProducts()
    {

        $collectionProducts = Order::
            groupBy('product_id')
            ->selectRaw('sum(count) as total,product_id')
            ->orderbyDesc('total')
            ->take(5)
            ->get();

        $product = [];
        $count = [];

        foreach ($collectionProducts as $collection) {
            if ($collection->product) {
                $product[] = $collection->product->name;
                $count[] = $collection->total;
            }
        }

        return [
            'product' => $product,
            'count' => $count
        ];
    }

    public function getSource()
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

        return compact('source', 'percent');
    }


    public function getStatus()
    {

        if (auth()->user()->role === User::KARSHENAS_FANI) {
            $list = Customer::where('user_id', auth()->id())
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

    public function getFailureCategories()
    {
        $collectionFailureCategories = Automation::groupBy('failure_id')
            ->selectRaw('count(*) as total,failure_id')
            ->orderby('total', 'DESC')
            ->get();

        $failure = [];
        $percent = [];

        $all = $collectionFailureCategories->sum('total');


        foreach ($collectionFailureCategories as $collection) {
            $categoryItem = FailureCategory::find($collection->failure_id);
            if ($categoryItem) {
                $failure[] = $categoryItem->title;
                $percent[] = (integer)$collection->total;
            }
        }


        return [
            'failure' => $failure,
            'percent' => $percent
        ];
    }

    public function getBestCuctomer()
    {

        $collectionCustomer = Automation::where('status', Automation::SUCCESS)
            ->groupBy('customer_id')
            ->selectRaw('sum(final_price) as total,customer_id')
            ->orderby('total', 'DESC')
            ->take(5)
            ->get();

        $customer = [];
        $count = [];

        foreach ($collectionCustomer as $collection) {
            if ($collection->customer) {
                $customer[] = $collection->customer->name;
                $count[] = $collection->total;
            }
        }

        return [
            'customer' => $customer,
            'count' => $count
        ];
    }

    public function getTopSeller()
    {

        $collectionSeller = Automation::where('status', Automation::SUCCESS)
            ->groupBy('user_id')
            ->selectRaw('sum(final_price) as total,user_id')
            ->orderby('total', 'DESC')
            ->take(5)
            ->get();

        $user = [];
        $count = [];

        foreach ($collectionSeller as $collection) {
            if ($collection->user) {
                $user[] = $collection->user->name;
                $count[] = $collection->total;
            }
        }

        return [
            'user' => $user,
            'count' => $count
        ];
    }


    public function getReport()
    {

        $collectionCalls = Call::groupBy('user_id')
            ->selectRaw('COUNT(*) as total, user_id')
            ->orderBy('total', 'DESC')
            ->get();

        $call = [];
        $count = [];

        foreach ($collectionCalls as $collection) {
            $user = User::find($collection->user_id);
            if ($user) {
                $call[] = $user->name;
                $count[] = $collection->total;
            }
        }

        if (auth()->user()->role === User::ADMIN)
            return [
                'call' => $call,
                'count' => $count
            ];

        return null;
    }

    public function getSalesSource()
    {
        $collectionSalesSource = Automation::join('customers', 'automation.customer_id', '=', 'customers.id')
            ->join('source_categories', 'customers.source_id', '=', 'source_categories.id')
            ->where('automation.status', Automation::SUCCESS)
            ->groupBy('customers.source_id')
            ->selectRaw('COUNT(*) as total, source_categories.title as title')
            ->orderBy('total', 'DESC')
            ->get();

        $salesSource = [];
        $count = [];

        foreach ($collectionSalesSource as $collection) {
            $sourceTitle = $collection->title; // عنوان منبع را دریافت می‌کنیم
            $salesSource[] = $sourceTitle;
            $count[] = $collection->total;
        }

        return [
            'salesSource' => $salesSource,
            'count' => $count
        ];
    }

}
