<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\FailureCategory;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestStatus\Failure;

class FailureCategoriesController extends Controller
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
        $data = FailureCategory::orderby('created_at', 'DESC')->get();
        return $this->apiController->respondData($data);
    }


    public function store(Request $request)
    {

        $this->validate($request, [
            'title' => 'required'
        ], [
            'title.required' => 'عنوان را وارد نمایید'
        ]);


        $create = FailureCategory::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'user_id' => auth()->id()
        ]);

        if ($create and $create instanceof FailureCategory) {
            return $this->apiController->respondCreated('با موفقیت ایجاد شد',$create);
        } else {
            return $this->apiController->respondInternalError();
        }

    }

    public function update(Request $request)
    {
        $data = FailureCategory::findOrFail($request->input('id'));

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

    public function show(FailureCategory $failureCategory)
    {
        return $this->apiController->respondData($failureCategory);
    }

    public function destroy(Request $request)
    {

        $data = FailureCategory::findOrFail($request->input('id'));
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


}
