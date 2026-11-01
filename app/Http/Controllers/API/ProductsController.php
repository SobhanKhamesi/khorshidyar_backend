<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

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

        $this->validate($request, [
            'name' => 'required',

        ], [
            'name.required' => 'نام را وارد نمایید',
        ]);


        $create = Product::create([
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
        $this->validate($request, [
            'name' => 'required',
        ], [
            'name.required' => 'نام را وارد نمایید',
        ]);

        $data = Product::findOrFail($request->input('id'));

        $update = $data->update([
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


    public
    function destroy(Request $request)
    {
        if (auth()->user()->role !== User::ADMIN && auth()->user()->role !== User::ANBARDAR)
            return $this->apiController->respondForbidden('دسترسی ندارید.');

        $data = Product::findOrFail($request->input('id'));
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

}
