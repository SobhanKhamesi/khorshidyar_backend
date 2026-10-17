<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\Controller;
use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    private ApiController $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function show($token)
    {
        $rating = Rating::with(['afterSalesService.serviceType', 'afterSalesService.customer'])
            ->where('token', $token)
            ->first();

        if (!$rating) {
            return $this->apiController->respondError('لینک نظرسنجی نامعتبر است یا منقضی شده است');
        }

        $service = $rating->afterSalesService;

        return $this->apiController->respondData([
            'is_submitted' => $rating->is_submitted,
            'rating' => $rating->rating,
            'feedback' => $rating->feedback,
            'submitted_at' => $rating->submitted_at,
            'service' => $service ? [
                'problem' => $service->problem,
                'jalali_date' => $service->jalali_date,
                'service_type' => $service->serviceType ? $service->serviceType->title : null,
                'customer_name' => $service->customer ? $service->customer->fullName : null,
            ] : null,
        ]);
    }

    public function submit(Request $request, $token)
    {
        $rating = Rating::where('token', $token)->first();

        if (!$rating) {
            return $this->apiController->respondError('لینک نظرسنجی نامعتبر است یا منقضی شده است');
        }

        if ($rating->is_submitted) {
            return $this->apiController->respondError('نظر شما پیش‌تر ثبت شده است');
        }

        $this->validate($request, [
            'rating' => 'required|numeric|min:1|max:5',
            'feedback' => 'nullable|string|max:2000',
        ], [
            'rating.required' => 'لطفاً امتیاز خود را انتخاب کنید',
            'rating.numeric' => 'امتیاز وارد شده نامعتبر است',
            'rating.min' => 'امتیاز باید بین ۱ تا ۵ باشد',
            'rating.max' => 'امتیاز باید بین ۱ تا ۵ باشد',
            'feedback.max' => 'متن بازخورد بیش از حد طولانی است',
        ]);

        $rating->update([
            'rating' => round((float) $request->input('rating'), 1),
            'feedback' => $request->input('feedback'),
            'submitted_at' => now(),
        ]);

        return $this->apiController->respondUpdated('با تشکر از شما، نظر شما با موفقیت ثبت شد', [
            'is_submitted' => true,
            'rating' => $rating->rating,
            'feedback' => $rating->feedback,
            'submitted_at' => $rating->submitted_at,
        ]);
    }
}
