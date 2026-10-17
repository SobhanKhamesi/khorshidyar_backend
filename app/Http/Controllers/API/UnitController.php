<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
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
        $data = Unit::orderby('created_at', 'DESC')->get();
        return $this->apiController->respondData($data);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required',
        ], [
            'title.required' => 'عنوان را وارد نمایید',
        ]);

        $create = Unit::create([
            'title' => $request->input('title'),
        ]);

        if ($create and $create instanceof Unit) {
            return $this->apiController->respondCreated('با موفقیت ایجاد شد', $create);
        } else {
            return $this->apiController->respondInternalError();
        }
    }

    public function show(Unit $unit)
    {
        return $this->apiController->respondData($unit);
    }

    public function update(Request $request)
    {
        $this->validate($request, [
            'title' => 'required',
        ], [
            'title.required' => 'عنوان را وارد نمایید',
        ]);

        $data = Unit::findOrFail($request->input('id'));

        $update = $data->update([
            'title' => $request->input('title'),
        ]);

        if ($update) {
            return $this->apiController->respondUpdated();
        } else {
            return $this->apiController->respondInternalError();
        }
    }

    public function destroy(Request $request)
    {
        $data = Unit::findOrFail($request->input('id'));
        if ($data->delete()) {
            return $this->apiController->respondDeleted();
        } else {
            return $this->apiController->respondInternalError();
        }
    }
}
