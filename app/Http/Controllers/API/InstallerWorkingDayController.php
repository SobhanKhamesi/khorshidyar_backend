<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\Controller;
use App\Models\InstallerAppointment;
use App\Models\InstallerWorkingDay;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstallerWorkingDayController extends Controller
{
    private ApiController $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    public function index($installerId)
    {
        $installer = User::findOrFail($installerId);

        $days = InstallerWorkingDay::where('user_id', $installer->id)
            ->orderBy('weekday')
            ->get()
            ->keyBy('weekday');

        $list = [];
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $day = $days->get($weekday);
            $list[] = [
                'weekday' => $weekday,
                'is_active' => $day->is_active ?? false,
                'start_time' => $day && $day->start_time ? substr($day->start_time, 0, 5) : null,
                'end_time' => $day && $day->end_time ? substr($day->end_time, 0, 5) : null,
            ];
        }

        return $this->apiController->respondData($list);
    }

    public function store(Request $request, $installerId)
    {
        $installer = User::findOrFail($installerId);

        $this->validate($request, [
            'days' => 'required|array|size:7',
            'days.*.weekday' => 'required|integer|between:0,6',
            'days.*.is_active' => 'required|boolean',
            'days.*.start_time' => 'nullable|required_if:days.*.is_active,true|date_format:H:i',
            'days.*.end_time' => 'nullable|required_if:days.*.is_active,true|date_format:H:i|after:days.*.start_time',
        ], [
            'days.*.start_time.required_if' => 'ساعت شروع را وارد نمایید',
            'days.*.end_time.required_if' => 'ساعت پایان را وارد نمایید',
            'days.*.end_time.after' => 'ساعت پایان باید بعد از ساعت شروع باشد',
        ]);

        $conflict = $this->findActiveAppointmentsOnDisabledDays($installer->id, $request->input('days'));
        if ($conflict) {
            return $this->apiController->respondError(
                "امکان غیرفعال‌سازی «{$conflict}» وجود ندارد؛ نوبت‌های رزرو شده در این روز باید ابتدا لغو یا جابجا شوند."
            );
        }

        DB::transaction(function () use ($request, $installer) {
            foreach ($request->input('days') as $day) {
                InstallerWorkingDay::updateOrCreate(
                    ['user_id' => $installer->id, 'weekday' => $day['weekday']],
                    [
                        'is_active' => $day['is_active'],
                        'start_time' => $day['is_active'] ? $day['start_time'] : null,
                        'end_time' => $day['is_active'] ? $day['end_time'] : null,
                    ]
                );
            }
        });

        return $this->apiController->respondUpdated();
    }

    private function findActiveAppointmentsOnDisabledDays($installerId, array $days): ?string
    {
        $weekDaysFa = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

        foreach ($days as $day) {
            if ($day['is_active']) {
                continue;
            }

            $hasAppointments = InstallerAppointment::forInstaller($installerId)
                ->active()
                ->whereDate('date', '>=', now()->format('Y-m-d'))
                ->get()
                ->contains(function ($appointment) use ($day) {
                    return (($appointment->date->dayOfWeek + 1) % 7) === $day['weekday'];
                });

            if ($hasAppointments) {
                return $weekDaysFa[$day['weekday']];
            }
        }

        return null;
    }
}
