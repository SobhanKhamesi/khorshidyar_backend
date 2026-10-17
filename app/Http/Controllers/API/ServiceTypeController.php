<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\Controller;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceTypeController extends Controller
{
    private ApiController $apiController;
    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }
    public function index()
    {
        $serviceTypes = ServiceType::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title']);

        return response()->json([
            'data' => $serviceTypes,
        ]);
    }

    public function quickAdd(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('service_types', 'title')],
        ], [
            'title.unique' => 'این نوع خدمت قبلاً ثبت شده است',
        ]);

        $serviceType = ServiceType::create([
            'title' => $validated['title'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'نوع خدمت با موفقیت ثبت شد',
            'data' => $serviceType->only(['id', 'title']),
        ]);
    }
    public function update(Request $request , ServiceType $serviceType)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('service_types', 'title')],
        ], [
            'title.unique' => 'این نوع خدمت قبلاً ثبت شده است',
        ]);

        $serviceType->update([
            'title' => $validated['title'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'نوع خدمت با موفقیت ویرایش شد',
            'data' => $serviceType->only(['id', 'title']),
        ]);
    }

    public function destroy(Request $request)
    {
        $service = ServiceType::findOrFail($request->input("id"));
        $service->delete();
        return $this->apiController->respondDeleted();
    }
}
