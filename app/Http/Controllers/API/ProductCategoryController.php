<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    private ApiController $apiController;
    private ApiHelper $apiHelper;

    public function __construct(ApiController $apiController, ApiHelper $apiHelper)
    {
        $this->apiController = $apiController;
        $this->apiHelper = $apiHelper;
    }

    public function index(Request $request)
    {
        $data = ProductCategory::orderby('created_at', 'DESC')->get();
        return $this->apiController->respondData($data);
    }



    public function product(Request $request)
    {
        $kalaId = $request->input('id');
        $comments = Product::where('category_id', $kalaId)->orderBy('created_at', 'DESC')->get();
        return $this->apiController->respondData($comments);
    }
    public function store(Request $request)
    {

        $this->validate($request, [
            'title' => 'required',
        ], [
            'title.required' => 'عنوان را وارد کنید.',
        ]);

        $InventoryId = null;
        if ($request->has('inventory') && $request->input('inventory') != null) {
            $InventoryId = Inventory::findOrFail($request->input('inventory'))->id;
        }


        $create = ProductCategory::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'user_id' => auth()->id(),
            'inventory_id' => $InventoryId
        ]);

        if ($create and $create instanceof ProductCategory) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }

    }

    public function newProduct(Request $request)
    {

        $this->validate($request, [
            'name' => 'required'
        ], [
            'name.required' => 'نام محصول را بنویسید'
        ]);

        $product = ProductCategory::findOrFail($request->input('id'));

        if ($request->has('picture') && $request->file('picture') != null)
        {
            $file = $request->file('picture');
            $fileExtension = $file->getClientOriginalExtension();
            $pictureName = Carbon::now()->format('Y-m-d') . '-' . rand(100, 9999) . '.' . $fileExtension;
            $file->move(public_path('picture'), $pictureName);

            $newProduct = Product::create([
                'category_id' => $product->id,
                'name' => $request->input('name'),
                'code' => $request->input('code'),
                'unit_id' => $request->input('unitId'),
                'technical' => $this->apiHelper->getRealText($request, 'technical'),
                'first_inventory' => $this->apiHelper->getRealCount($request, 'firstInventory'),
                'purchase_price' => $this->apiHelper->getRealPrice($request->input('purchasePrice')),
                'selling_price' => $this->apiHelper->getRealPrice($request->input('sellingPrice')),
                'description' => $this->apiHelper->getRealText($request, 'productDescription'),
                'user_id' => auth()->id(),
                'picture' => $pictureName,
                ]);


            if ($newProduct and $newProduct instanceof Product) {
                return $this->apiController->respondCreated();
            } else {
                return $this->apiController->respondInternalError();
            }
        }else{
            $newProduct = Product::create([
                'category_id' => $product->id,
                'name' => $request->input('name'),
                'code' => $request->input('code'),
                'unit_id' => $request->input('unitId'),
                'technical' => $this->apiHelper->getRealText($request, 'technical'),
                'first_inventory' => $this->apiHelper->getRealCount($request, 'firstInventory'),
                'purchase_price' => $this->apiHelper->getRealPrice($request->input('purchasePrice')),
                'selling_price' => $this->apiHelper->getRealPrice($request->input('sellingPrice')),
                'description' => $this->apiHelper->getRealText($request, 'productDescription'),
                'user_id' => auth()->id(),
                'picture' => 'images.png',
            ]);


            if ($newProduct and $newProduct instanceof Product) {
                return $this->apiController->respondCreated();
            } else {
                return $this->apiController->respondInternalError();
            }
        }
    }
    public function destroy(Request $request) {

        $category = ProductCategory::findOrFail($request->id);

        $products = $category->product;

        foreach($products as $product) {
            $product->orders()->delete();
        }

        $products->each->delete();

        if ( $category->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }
    public function deleteProduct(Request $request)
    {

        $data = Product::findOrFail($request->input('id'));
        if ($data) {
            if ($data->delete()) {
                return $this->apiController->respondDeleted();
            } else {
                return $this->apiController->respondInternalError();
            }
        } else {
            return $this->apiController->respondNotFound();
        }

    }
    public function update(Request $request)
    {
        $data = ProductCategory::findOrFail($request->input('id'));

        $this->validate($request, [
            'title' => 'required'
        ], [
            'title.required' => 'عنوان را وارد نمایید'
        ]);


        $update = $data->update([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
        ]);

        if ($update) {
            return $this->apiController->respondUpdated();
        } else {
            return $this->apiController->respondInternalError();
        }

    }
    public function show(ProductCategory $Product_Category)
    {
        return $this->apiController->respondData($Product_Category);
    }

}
