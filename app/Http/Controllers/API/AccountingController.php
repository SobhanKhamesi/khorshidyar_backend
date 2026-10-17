<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Accounting;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Check;
use App\Models\Customer;
use App\Models\Product;
use Hekmatinasser\Verta\Verta;
use Illuminate\Http\Request;

class AccountingController extends Controller
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
        $data = Accounting::whereNotIn('type', [Accounting::PRE_LEAD])->orderbyDesc('date')->get();
        return $this->apiController->respondData($data);
    }

    public function store(Request $request)
    {


        $this->validate($request, [
            'type' => 'required',
            'number' => 'required',
            'date' => 'required',
            'customer' => 'required'
        ], [
            'type.required' => 'نوع فاکتور را انتخاب نمایید',
            'number.required' => 'شماره فاکتور را وارد نمایید',
            'date.required' => 'تاریخ را انتخاب نمایید',
            'customer.required' => 'مشتری را انتخاب نمایید'
        ]);


        $customer = Customer::findOrFail($request->input('customer'));

        $checkType = null;

        switch ($request->input('type')) {
            case Accounting::PRE:
                if ($customer->status == 0) {
                    $customer->update([
                        'status' => Customer::OPPORTUNITY
                    ]);
                    $invoiceType = Accounting::PRE_LEAD;
                } else {
                    $invoiceType = Accounting::PRE;
                }
                break;
            case Accounting::SALES:
                $invoiceType = Accounting::SALES;
                $checkType = Check::RECEIVABLE;
                break;
            case Accounting::RETURN_FROM_SALES:
                $invoiceType = Accounting::RETURN_FROM_SALES;
                break;
            case Accounting::RETURN_FROM_PURCHASE:
                $invoiceType = Accounting::RETURN_FROM_PURCHASE;
                break;
            case Accounting::PURCHASE:
                $invoiceType = Accounting::PURCHASE;
                $checkType = Check::PAYABLE;
                break;
            default:
                return $this->apiController->respondError('نوع فاکتور را انتخاب نمایید');
        }


        $payPos = $this->apiHelper->getRealPrice($request->input('payPos'));


        $payPosBankId = null;
        if ($payPos > 0) {
            if ($request->has('posBankAccount') and $request->input('posBankAccount') != null) {
                $payPosBankId = BankAccount::findOrFail($request->input('posBankAccount'))->id;
            }
        }


        $create = Accounting::create([
            'pay_pos' => $payPos,
            'pos_bank_account_id' => $payPosBankId,
            'pay_cash' => $this->apiHelper->getRealPrice($request->input('payCash')),
            'type' => $invoiceType,
            'number' => $request->input('number'),
            'date' => Verta::parse($request->input('date'))->datetime(),
            'customer_id' => $customer->id,
            'description' => $request->input('description'),
            'user_id' => auth()->id()
        ]);

        if ($create and $create instanceof Accounting) {

            $finalPrice = 0;

            foreach ($request->input('orders') as $item) {

                $product = Product::findOrFail($item['orderProductId']);

                $orderCount = $item['orderCount'];


                if ($invoiceType == Accounting::RETURN_FROM_PURCHASE || $invoiceType == Accounting::PURCHASE) {
                    $orderSellingPrice = 0;
                    $orderPrice = $orderPurchasePrice = $this->apiHelper->getRealPrice($item['orderPrice']) > 0 ? $this->apiHelper->getRealPrice($item['orderPrice']) : $product->purchase_price;
                } else {
                    $orderPrice = $orderSellingPrice = $this->apiHelper->getRealPrice($item['orderPrice']) > 0 ? $this->apiHelper->getRealPrice($item['orderPrice']) : $product->selling_price;
                    $orderPurchasePrice = $this->apiHelper->getRealPrice($item['orderPurchasePrice']) > 0 ? $this->apiHelper->getRealPrice($item['orderPurchasePrice']) : $product->purchase_price;
                }


                $finalPrice += $orderCount * $orderPrice;

                $create->orders()->create([
                    'invoice_type' => $invoiceType,
                    'product_id' => $product->id,
                    'count' => $orderCount,
                    'selling_price' => $orderSellingPrice,
                    'purchase_price' => $orderPurchasePrice,
                ]);

            }


            $create->update([
                'final_price' => $this->apiHelper->getRealPrice($finalPrice),
            ]);


            foreach ($request->input('checks') as $item) {

                $checkAmount = $this->apiHelper->getRealPrice($item['amount']);


                $checkSeries = $item['series'];
                $checkSerial = $item['serial'];


                $checkBankId = null;
                if (isset($item['bankId']) && $item['bankId'] != null) {
                    $checkBankId = Bank::findOrFail($item['bankId'])->id;
                }

                $checkBankAccountId = null;
                if (isset($item['bankAccountId']) && $item['bankAccountId'] != null) {
                    $checkBankAccountId = BankAccount::findOrFail($item['bankAccountId'])->id;
                }


                $create->checks()->create([
                    'type' => $checkType,
                    'status' => Check::PENDING,
                    'amount' => $this->apiHelper->getRealPrice($checkAmount),
                    'date' => Verta::parse($item['date'])->datetime(),
                    'series' => $checkSeries,
                    'serial' => $checkSerial,
                    'bank_id' => $checkBankId,
                    'bank_account_id' => $checkBankAccountId,
                    'customer_id' => $customer->id,
                    'user_id' => auth()->id()
                ]);

            }

            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }

    }


    public function show(Accounting $accounting)
    {
        return $this->apiController->respondData($accounting);
    }


    public function update(Request $request)
    {

        $this->validate($request, [
            'type' => 'required',
            'number' => 'required',
            'date' => 'required',
            'customer' => 'required'
        ], [
            'type.required' => 'نوع فاکتور را انتخاب نمایید',
            'number.required' => 'شماره فاکتور را وارد نمایید',
            'date.required' => 'تاریخ را انتخاب نمایید',
            'customer.required' => 'مشتری را انتخاب نمایید'
        ]);


        $data = Accounting::findOrFail($request->input('id'));

        $customer = Customer::findOrFail($request->input('customer'));


        $checkType = null;
        switch ($request->input('type')) {
            case Accounting::SALES:
                $checkType = Check::RECEIVABLE;
                break;
            case Accounting::PURCHASE:
                $checkType = Check::PAYABLE;
                break;
        }


        $payPos = $this->apiHelper->getRealPrice($request->input('payPos'));

        $payPosBankId = null;
        if ($payPos > 0) {
            if ($request->input('posBankAccount') != null) {
                $payPosBankId = BankAccount::findOrFail($request->input('posBankAccount'))->id;
            }
        }


        $update = $data->update([
            'number' => $request->input('number'),
            'date' => Verta::parse($request->input('date'))->datetime(),
            'customer_id' => $customer->id,
            'pay_pos' => $this->apiHelper->getRealPrice($payPos),
            'pos_bank_account_id' => $payPosBankId,
            'pay_cash' => $this->apiHelper->getRealPrice($request->input('payCash')),
            'description' => $request->input('description'),
        ]);


        $invoiceType = $data->type;

        if ($update) {

            $data->orders()->delete();
            $data->checks()->delete();

            $finalPrice = 0;

            foreach ($request->input('orders') as $item) {

                $product = Product::findOrFail($item['orderProductId']);


                $orderCount = $item['orderCount'];


                if ($invoiceType == Accounting::RETURN_FROM_PURCHASE || $invoiceType == Accounting::PURCHASE) {
                    $orderSellingPrice = 0;
                    $orderPrice = $orderPurchasePrice = $this->apiHelper->getRealPrice($item['orderPrice']) > 0 ? $this->apiHelper->getRealPrice($item['orderPrice']) : $product->purchase_price;
                } else {
                    $orderPrice = $orderSellingPrice = $this->apiHelper->getRealPrice($item['orderPrice']) > 0 ? $this->apiHelper->getRealPrice($item['orderPrice']) : $product->selling_price;
                    $orderPurchasePrice = $this->apiHelper->getRealPrice($item['orderPurchasePrice']) > 0 ? $this->apiHelper->getRealPrice($item['orderPurchasePrice']) : $product->purchase_price;
                }


                $finalPrice += $orderCount * $orderPrice;

                $data->orders()->create([
                    'invoice_type' => $invoiceType,
                    'product_id' => $product->id,
                    'count' => $orderCount,
                    'selling_price' => $orderSellingPrice,
                    'purchase_price' => $orderPurchasePrice,
                ]);

            }


            $data->update([
                'final_price' => $this->apiHelper->getRealPrice($finalPrice),
            ]);

            foreach ($request->input('checks') as $item) {

                $checkAmount = $this->apiHelper->getRealPrice($item['amount']);


                $checkSeries = $item['series'];
                $checkSerial = $item['serial'];

                $checkBankId = null;
                if (isset($item['bankId']) && $item['bankId'] != null) {
                    $checkBankId = Bank::findOrFail($item['bankId'])->id;
                }

                $checkBankAccountId = null;
                if (isset($item['bankAccountId']) && $item['bankAccountId'] != null) {
                    $checkBankAccountId = BankAccount::findOrFail($item['bankAccountId'])->id;
                }


                $data->checks()->create([
                    'type' => $checkType,
                    'status' => Check::PENDING,
                    'amount' => $this->apiHelper->getRealPrice($checkAmount),
                    'date' => Verta::parse($item['date'])->datetime(),
                    'series' => $checkSeries,
                    'serial' => $checkSerial,
                    'bank_id' => $checkBankId,
                    'customer_id' => $customer->id,
                    'bank_account_id' => $checkBankAccountId,
                    'user_id' => auth()->id()
                ]);

            }


            return $this->apiController->respondUpdated();
        }


        return $this->apiController->respondInternalError();


    }


    public function destroy(Request $request)
    {

        $data = Accounting::findOrFail($request->input('id'));
        $data->orders()->delete();
        $data->checks()->delete();
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

}
