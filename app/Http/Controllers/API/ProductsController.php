<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Support\Access;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductsController extends Controller
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
        $data = Product::withStats()->orderby('created_at', 'DESC')->get();
        return $this->apiController->respondData($data);
    }


    public function store(Request $request)
    {
        Access::ensureSales();

        $this->validateProduct($request);

        $create = Product::create($this->kindAttributes($request) + [
            'name' => $request->input('name'),
            'first_inventory' => $this->apiHelper->getRealCount($request, 'firstInventory'),
                'purchase_price' => $this->apiHelper->getRealPrice($request->input('purchasePrice')),
            'selling_price' => $this->apiHelper->getRealPrice($request->input('sellingPrice')),
            'description' => $this->apiHelper->getRealText($request, 'description'),
            'unit_id' => $this->apiHelper->getRealText($request, 'unitId'),
            'user_id' => auth()->id(),
        ]);


        if ($create and $create instanceof Product) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }
    }


    public function show(Product $product)
    {
        return $this->apiController->respondData($product);
    }


    public function update(Request $request)
    {
        Access::ensureSales();
        $this->validateProduct($request);

        $data = Product::findOrFail($request->input('id'));

        $update = $data->update($this->kindAttributes($request) + [
            'name' => $request->input('name'),
            'first_inventory' => $this->apiHelper->getRealCount($request, 'firstInventory'),
            'purchase_price' => $this->apiHelper->getRealPrice($request->input('purchasePrice')),
            'selling_price' => $this->apiHelper->getRealPrice($request->input('sellingPrice')),
            'description' => $this->apiHelper->getRealText($request, 'description'),
            'unit_id' => $this->apiHelper->getRealText($request, 'unitId'),
        ]);

        if ($update) {
            return $this->apiController->respondUpdated();
        } else {
            return $this->apiController->respondInternalError();
        }
    }


    private function validateProduct(Request $request): void
    {
        $this->validate($request, [
            'name' => 'required',
            'kind' => ['nullable', Rule::in([Product::KIND_GOODS, Product::KIND_SERVICE])],
            'serviceNature' => [
                'nullable',
                Rule::requiredIf((int)$request->input('kind') === Product::KIND_SERVICE),
                Rule::in([Product::NATURE_COST, Product::NATURE_INCOME]),
            ],
        ], [
            'name.required' => 'نام را وارد نمایید',
            'serviceNature.required' => 'مشخص کنید این خدمت از جنس هزینه است یا دریافتی',
        ]);
    }

    /**
     * خدمت موجودی انبار و قیمت خرید/فروش جداگانه ندارد؛ فقط یک «مبلغ» (serviceAmount) دارد که
     * برای خدمت هزینه در purchase_price و برای خدمت دریافتی در selling_price ذخیره می‌شود.
     * این آرایه با + ترکیب می‌شود و بر مقادیر بعدی (first_inventory و قیمت‌ها) اولویت دارد.
     */
    private function kindAttributes(Request $request): array
    {
        if ((int)$request->input('kind') !== Product::KIND_SERVICE)
            return ['kind' => Product::KIND_GOODS, 'service_nature' => null];

        $nature = (int)$request->input('serviceNature');
        $raw = $this->apiHelper->getRealPrice((string)$request->input('serviceAmount'));
        $amount = $raw === '' ? null : (int)$raw;

        return [
            'kind' => Product::KIND_SERVICE,
            'service_nature' => $nature,
            'first_inventory' => null,
            'purchase_price' => $nature === Product::NATURE_COST ? $amount : null,
            'selling_price' => $nature === Product::NATURE_INCOME ? $amount : null,
        ];
    }

    public
    function destroy(Request $request)
    {
        // حذف فقط برای مدیر (ثابت ANBARDAR وجود نداشت و برای غیر مدیر خطای سرور می‌داد)
        Access::ensureCan('inventory.manage');

        $data = Product::findOrFail($request->input('id'));
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

}
