<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Contracts\ApiController;
use App\Http\Controllers\Controller;
use App\Support\Access;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * صفحه‌ی «سطوح دسترسی» (فقط ادمین): هر نقش چه داده‌هایی را ببیند و چه کارهایی انجام دهد.
 * نقش ادمین همیشه همه‌ی دسترسی‌ها را دارد و قابل ویرایش نیست.
 */
class AccessLevelController extends Controller
{
    public function __construct(private ApiController $apiController)
    {
    }

    public function index()
    {
        Access::ensureAdmin();

        return $this->apiController->respondData(Permissions::describe());
    }

    public function update(Request $request)
    {
        Access::ensureAdmin();

        $roles = Permissions::editableRoles();
        $this->validate($request, [
            'matrix' => 'required|array',
            'matrix.*' => 'array',
            'matrix.*.*' => ['string', Rule::in(Permissions::keys())],
        ], [
            'matrix.required' => 'سطوح دسترسی ارسال نشده است',
            'matrix.*.*.in' => 'دسترسی نامعتبر است',
        ]);

        $matrix = collect($request->input('matrix'))
            ->filter(fn($permissions, $role) => in_array((int)$role, $roles, true))
            ->all();

        Permissions::save($matrix);

        return $this->apiController->respondData(Permissions::describe(), 'سطوح دسترسی با موفقیت ذخیره شد');
    }

    public function reset(int $role)
    {
        Access::ensureAdmin();

        if (!in_array($role, Permissions::editableRoles(), true))
            return $this->apiController->respondError('دسترسی این نقش قابل تغییر نیست');

        Permissions::reset($role);

        return $this->apiController->respondData(Permissions::describe(), 'دسترسی‌های پیش‌فرض این نقش بازگردانی شد');
    }
}
