<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\API\Contracts\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\SourceSms;
use Illuminate\Http\Request;

class SourceSmsController extends Controller
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
        $data = SourceSms::orderby('created_at', 'DESC')->get();
        return $this->apiController->respondData($data);
    }


    public function store(Request $request)
    {

        $this->validate($request, [
            'title' => 'required'
        ], [
            'title.required' => 'عنوان را وارد نمایید'
        ]);


        $create = SourceSms::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'user_id' => auth()->id()
        ]);

        if ($create and $create instanceof SourceSms) {
            return $this->apiController->respondCreated('با موفقیت ایجاد شد',$create);
        } else {
            return $this->apiController->respondInternalError();
        }

    }

    public function show(SourceSms $sourceSms)
    {

        return $this->apiController->respondData($sourceSms);


    }
    public function destroy(Request $request)
    {

        $data = SourceSms::findOrFail($request->input('id'));
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
        $data = SourceSms::findOrFail($request->input('id'));

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

}
