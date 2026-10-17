<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryController extends Controller
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
        $data = Inventory::orderby('created_at', 'DESC')->get();
        return $this->apiController->respondData($data);

    }

    public function show(Inventory $inventory)
    {
        return $this->apiController->respondData($inventory);
    }

    public function store(Request $request)
    {

        $this->validate($request, [
            'title' => 'required',
        ], [
            'title.required' => 'عنوان را وارد کنید.',
        ]);


        $create = Inventory::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'user_id' => auth()->id()
        ]);

        if ($create and $create instanceof Inventory) {
            return $this->apiController->respondCreated();
        } else {
            return $this->apiController->respondInternalError();
        }

    }

    public function update(Request $request)
    {
        $data = Inventory::findOrFail($request->input('id'));

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

    public function destroy(Request $request) {

        $inventory = Inventory::findOrFail($request->id);

        if(!$inventory) {
            return response()->json(['message' => 'Inventory not found'], 404);
        }
        foreach($inventory->productGroups as $group) {
            $products = $group->product;
            if(!$products) continue;
            foreach($products as $product) {
                if(!$product->orders) continue;
                $product->orders()->delete();
            }
            $products->each->delete();

//            $products->each->update(['product_category_id' => null]);
        }

        // Delete groups first
        $inventory->productGroups()->delete();

        // Then delete inventory
        if($inventory->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }

    }
}
