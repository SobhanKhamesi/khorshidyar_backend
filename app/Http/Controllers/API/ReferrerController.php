<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Models\Automation;
use App\Models\Customer;
use App\Models\Referrer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReferrerController extends Controller
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
        $referrers = Referrer::all();
        return $this->apiController->respondData($referrers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $referrer = Referrer::create($request->only('name', 'description'));

        if ($referrer and $referrer instanceof Referrer) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

    public function update(Referrer $referrer, Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $update = $referrer->update($request->only('name', 'description'));

        if ($update) {
            return $this->apiController->respondUpdated();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

    public function destroy(Referrer $referrer)
    {
        // درصد پروژه‌های قبلی روی خود پروژه ثبت شده و دست نمی‌خورد
        Customer::where("referrer_id", $referrer->id)->update(['referrer_id' => null, 'referrer_percent' => null]);
        if ($referrer->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

    /**
     * معرفی‌شدگان یک معرف به همراه مجموع خرید موفق و مجموع دریافتی معرف از پروژه‌های هر کدام.
     */
    public function introduces(Referrer $referrer)
    {
        $data = Customer::withStats()->where("referrer_id", $referrer->id)->orderByDesc('created_at')->get();

        $incomes = Automation::query()->toBase()
            ->whereIn('customer_id', $data->pluck('id'))
            ->where('status', Automation::SUCCESS)
            ->whereNotNull('referrer_percent')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, count(*) as projects_count, round(sum(final_price * referrer_percent / 100)) as income')
            ->get()
            ->keyBy('customer_id');

        foreach ($data as $customer) {
            $customer->setAttribute('referrer_income', (int)($incomes[$customer->id]->income ?? 0));
            $customer->setAttribute('referrer_projects_count', (int)($incomes[$customer->id]->projects_count ?? 0));
        }

        return $this->apiController->respondData($data);
    }

    /**
     * پروژه‌های موفق یک معرفی‌شده با درصد و دریافتی معرف از هر پروژه.
     * درصد هر پروژه همان مقداری است که روی خود پروژه ثبت شده (تغییر درصد مشتری روی پروژه‌های قبلی اثری ندارد).
     */
    public function introducesDetails(Customer $customer)
    {
        $automations = Automation::query()
            ->withoutEagerLoads()
            ->where("customer_id", $customer->id)
            ->where("status", Automation::SUCCESS)
            ->orderByDesc('created_at')
            ->get(['id', 'invoice_number', 'final_price', 'referrer_percent', 'created_at']);

        $data = [];
        foreach ($automations as $automation)
            $data[] = [
                "id" => $automation->id,
                "invoice_number" => $automation->invoice_number,
                "purchase_amount" => $automation->final_price,
                "purchase_introducer_percent" => $automation->referrer_percent,
                "purchase_introducer_income" => $automation->referrer_income,
                "purchase_jalali_date" => $automation->jalali_date,
            ];

        return $this->apiController->respondData($data);
    }

    /**
     * ویرایش درصد معرف یک سرنخ/مشتری. فقط برای پروژه‌هایی که از این به بعد ساخته می‌شوند اعمال می‌شود.
     */
    public function updatePercent(Customer $customer, Request $request)
    {
        if (!$customer->referrer_id)
            return $this->apiController->respondError('برای این سرنخ/مشتری معرفی ثبت نشده است');

        $percent = $this->apiHelper->referrerPercent($request);
        if ($percent === null)
            return $this->apiController->respondError('درصد معرف را وارد نمایید');

        $customer->update(['referrer_percent' => $percent]);

        return $this->apiController->respondUpdated(
            'درصد معرف ویرایش شد و برای پروژه‌های جدید اعمال می‌شود',
            ['referrer_percent' => $percent]
        );
    }
}
