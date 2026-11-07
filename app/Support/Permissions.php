<?php

namespace App\Support;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * فهرست سطوح دسترسی سامانه و دسترسی‌های هر نقش.
 *
 *  - ادمین همیشه همه‌ی دسترسی‌ها را دارد و قابل ویرایش نیست (مدیریت کاربران و سطوح دسترسی فقط برای ادمین است).
 *  - دسترسی سایر نقش‌ها توسط ادمین در صفحه‌ی «سطوح دسترسی» ویرایش و در جدول role_permissions ذخیره می‌شود.
 *  - نقشی که هنوز ذخیره نشده از DEFAULTS استفاده می‌کند (همان قوانین قبلی سامانه).
 *  - requires: دسترسی پیش‌نیاز؛ بدون آن، این دسترسی ذخیره نمی‌شود.
 */
class Permissions
{
    const CACHE_KEY = 'role_permissions';

    const GROUPS = [
        [
            'key' => 'sales',
            'label' => 'فروش و مشتریان',
            'icon' => 'storefront',
            'permissions' => [
                'sales.access' => [
                    'label' => 'دسترسی به بخش فروش',
                    'description' => 'سرنخ، فرصت، مشتری، معرف، اتوماسیون فروش، محصولات، پیامک و نمودارها (فقط داده‌های خودش)',
                    'type' => 'view',
                ],
                'sales.view_all' => [
                    'label' => 'مشاهده‌ی همه‌ی داده‌های فروش',
                    'description' => 'سرنخ، فرصت، مشتری و اتوماسیون همه‌ی کاربران و آمار کامل داشبورد و نمودارها',
                    'type' => 'view',
                    'requires' => 'sales.access',
                ],
                'sales.manage_stages' => [
                    'label' => 'انجام مراحل مدیریتی فروش',
                    'description' => 'پیش فاکتور ← مذاکره، تایید قرارداد، فروش موفق، بازگردانی فروش ناموفق، اصلاحیه و ابطال فروش موفق',
                    'type' => 'action',
                    'requires' => 'sales.access',
                ],
                'sales.approve_contract' => [
                    'label' => 'تایید متن قرارداد و ارسال برای مشتری',
                    'description' => 'تایید نهایی قرارداد و ارسال لینک امضا برای مشتری',
                    'type' => 'action',
                    'requires' => 'sales.access',
                ],
                'sales.delete' => [
                    'label' => 'حذف داده‌های فروش',
                    'description' => 'حذف سرنخ، مشتری، تماس، معرف و یادداشت‌های اتوماسیون',
                    'type' => 'delete',
                    'requires' => 'sales.access',
                ],
                'sms.view_all' => [
                    'label' => 'مشاهده‌ی تاریخچه‌ی پیامک‌های ارسالی',
                    'description' => 'لیست همه‌ی پیامک‌های ارسال شده از سامانه',
                    'type' => 'view',
                ],
                'targeting.manage' => [
                    'label' => 'مدیریت تارگت فروش',
                    'description' => 'تعریف، ویرایش و حذف تارگت کاربران و مشاهده‌ی تارگت همه (بدون آن فقط تارگت خودش)',
                    'type' => 'action',
                ],
            ],
        ],
        [
            'key' => 'installation',
            'label' => 'سرویس نصب',
            'icon' => 'construction',
            'permissions' => [
                'installation.view_assigned' => [
                    'label' => 'مشاهده‌ی سرویس‌های ارجاع شده به خودش',
                    'description' => 'سرویس‌هایی که برای نصب یا بررسی فنی به این کاربر ارجاع شده و ثبت اقلام مصرفی/بررسی آن‌ها',
                    'type' => 'view',
                ],
                'installation.view_all' => [
                    'label' => 'مشاهده‌ی همه‌ی سرویس‌های نصب',
                    'description' => 'همه‌ی سرویس‌های نصب، از جمله پیش‌نویس‌ها',
                    'type' => 'view',
                ],
                'installation.manage' => [
                    'label' => 'مدیریت سرویس نصب',
                    'description' => 'ثبت، ویرایش، ارجاع به نصاب و کارشناس فنی، حذف و تکمیل نهایی سرویس نصب',
                    'type' => 'action',
                    'requires' => 'installation.view_all',
                ],
            ],
        ],
        [
            'key' => 'after_sales',
            'label' => 'خدمات پس از فروش',
            'icon' => 'support_agent',
            'permissions' => [
                'after_sales.view_assigned' => [
                    'label' => 'مشاهده‌ی خدمات ارجاع شده به خودش',
                    'description' => 'خدماتی که مسئول آن است یا در آن نوبت دارد و ثبت تکمیل پیگیری آن‌ها',
                    'type' => 'view',
                ],
                'after_sales.view_all' => [
                    'label' => 'مشاهده‌ی همه‌ی خدمات پس از فروش',
                    'description' => 'همه‌ی خدمات پس از فروش و سوابق خدمات مشتریان',
                    'type' => 'view',
                ],
                'after_sales.manage' => [
                    'label' => 'مدیریت خدمات پس از فروش',
                    'description' => 'ثبت، ویرایش و حذف خدمات پس از فروش',
                    'type' => 'action',
                    'requires' => 'after_sales.view_all',
                ],
                'after_sales.review_ratings' => [
                    'label' => 'بررسی نظرسنجی‌های ضعیف',
                    'description' => 'هشدار رضایت ضعیف مشتری و ثبت نتیجه‌ی بررسی نارضایتی',
                    'type' => 'action',
                    'requires' => 'after_sales.view_all',
                ],
            ],
        ],
        [
            'key' => 'finance',
            'label' => 'مالی و گزارش‌ها',
            'icon' => 'account_balance',
            'permissions' => [
                'accounting.manage' => [
                    'label' => 'حسابداری، چک و حساب‌های بانکی',
                    'description' => 'مشاهده و ثبت اسناد حسابداری، چک‌ها، حساب‌های بانکی، وضعیت پیش فاکتور و تبدیل فرصت',
                    'type' => 'action',
                ],
                'profit.view' => [
                    'label' => 'سود و زیان پروژه',
                    'description' => 'مشاهده‌ی سود فروش و پروژه، قیمت خرید اقلام و ثبت هزینه‌ها/دریافتی‌های جانبی',
                    'type' => 'view',
                ],
                'dashboard.reports' => [
                    'label' => 'گزارش عملکرد کاربران در داشبورد',
                    'description' => 'نمودار گزارش تماس‌ها و فعالیت کاربران در داشبورد',
                    'type' => 'view',
                ],
            ],
        ],
        [
            'key' => 'base',
            'label' => 'انبار و اطلاعات پایه',
            'icon' => 'inventory_2',
            'permissions' => [
                'inventory.manage' => [
                    'label' => 'مدیریت انبار و دسته‌بندی کالا',
                    'description' => 'ثبت و ویرایش انبار، دسته‌بندی کالا و حذف کالا',
                    'type' => 'action',
                ],
                'settings.manage' => [
                    'label' => 'مدیریت اطلاعات پایه (تنظیمات)',
                    'description' => 'ویرایش و حذف دسته‌بندی مشتری، علت شکست، منبع، نوع خدمت، قالب پیامک و واحد',
                    'type' => 'action',
                ],
            ],
        ],
        [
            'key' => 'personal',
            'label' => 'امور اداری',
            'icon' => 'event_note',
            'permissions' => [
                'personal.view_all' => [
                    'label' => 'مشاهده‌ی امور اداری همه‌ی کاربران',
                    'description' => 'یادآوری، قرار ملاقات، گزارش روزانه و ارجاع‌های سایر کاربران (بدون آن فقط مال خودش)',
                    'type' => 'view',
                ],
                'personal.delete' => [
                    'label' => 'حذف امور اداری',
                    'description' => 'حذف یادآوری، قرار ملاقات، گزارش روزانه، ارجاع و پیوست/یادداشت‌های ارجاع',
                    'type' => 'delete',
                ],
            ],
        ],
    ];

    // قوانین قبلی سامانه (پیش از امکان ویرایش)
    const DEFAULTS = [
        User::KARSHENAS_FANI => ['sales.access', 'installation.view_assigned', 'after_sales.view_assigned'],
        User::NASSAB => ['installation.view_assigned', 'after_sales.view_assigned'],
        User::KARSHENAS_FOROSH => ['sales.access'],
        User::MODIR_FOROSH => ['sales.access', 'sales.view_all', 'sales.manage_stages'],
    ];

    /** @var array<int, string[]>|null دسترسی‌های همه‌ی نقش‌ها در همین درخواست */
    private static ?array $resolved = null;

    /** کلید ← تعریف */
    public static function all(): array
    {
        $all = [];
        foreach (self::GROUPS as $group)
            foreach ($group['permissions'] as $key => $definition)
                $all[$key] = $definition;

        return $all;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /** نقش‌های قابل ویرایش (همه به جز ادمین) */
    public static function editableRoles(): array
    {
        return array_values(array_filter(array_keys(User::ROLE_LABELS), fn($role) => $role !== User::ADMIN));
    }

    public static function defaultsFor(int $role): array
    {
        return $role === User::ADMIN ? self::keys() : self::normalize(self::DEFAULTS[$role] ?? []);
    }

    /** دسترسی‌های فعلی یک نقش */
    public static function forRole(?int $role): array
    {
        if ($role === null)
            return [];
        if ($role === User::ADMIN)
            return self::keys();

        return self::matrix()[$role] ?? [];
    }

    public static function roleHas(?int $role, string $permission): bool
    {
        return in_array($permission, self::forRole($role), true);
    }

    /** نقش ← دسترسی‌ها (برای همه‌ی نقش‌ها، از جمله ادمین) */
    public static function matrix(): array
    {
        if (self::$resolved !== null)
            return self::$resolved;

        $saved = Cache::rememberForever(self::CACHE_KEY, function () {
            if (!Schema::hasTable('role_permissions'))
                return [];

            return RolePermission::all()->mapWithKeys(fn(RolePermission $row) => [$row->role => $row->permissions ?? []])->all();
        });

        $matrix = [];
        foreach (array_keys(User::ROLE_LABELS) as $role) {
            $matrix[$role] = $role === User::ADMIN
                ? self::keys()
                : (array_key_exists($role, $saved) ? self::normalize($saved[$role]) : self::defaultsFor($role));
        }

        return self::$resolved = $matrix;
    }

    /** ذخیره‌ی دسترسی‌های نقش‌ها؛ نقش ادمین و کلیدهای ناشناخته نادیده گرفته می‌شوند */
    public static function save(array $matrix): void
    {
        foreach ($matrix as $role => $permissions) {
            $role = (int)$role;
            if (!in_array($role, self::editableRoles(), true))
                continue;

            RolePermission::updateOrCreate(['role' => $role], [
                'permissions' => self::normalize((array)$permissions),
                'updated_by' => auth()->id(),
            ]);
        }

        self::flush();
    }

    /** بازگردانی یک نقش به دسترسی‌های پیش‌فرض */
    public static function reset(int $role): void
    {
        RolePermission::where('role', $role)->delete();
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::$resolved = null;
    }

    /** حذف کلیدهای ناشناخته/تکراری و دسترسی‌هایی که پیش‌نیازشان انتخاب نشده (با ترتیب ثابت) */
    public static function normalize(array $permissions): array
    {
        $all = self::all();
        $selected = array_flip(array_filter($permissions, 'is_string'));

        $allowed = function (string $key) use (&$allowed, $all, $selected): bool {
            if (!isset($selected[$key], $all[$key]))
                return false;
            $requires = $all[$key]['requires'] ?? null;

            return !$requires || $allowed($requires);
        };

        return array_values(array_filter(array_keys($all), $allowed));
    }

    /** ساختار کامل برای صفحه‌ی سطوح دسترسی */
    public static function describe(): array
    {
        $customized = RolePermission::pluck('role')->map(fn($role) => (int)$role)->all();

        return [
            'roles' => collect(User::ROLE_LABELS)->map(fn($label, $role) => [
                'value' => $role,
                'label' => $label,
                'locked' => $role === User::ADMIN,
                'customized' => in_array($role, $customized, true),
                'users_count' => User::where('role', $role)->count(),
            ])->values(),
            'groups' => collect(self::GROUPS)->map(fn($group) => [
                'key' => $group['key'],
                'label' => $group['label'],
                'icon' => $group['icon'],
                'permissions' => collect($group['permissions'])->map(fn($definition, $key) => [
                        'key' => $key,
                        'requires' => $definition['requires'] ?? null,
                    ] + $definition)->values(),
            ]),
            'matrix' => (object)self::matrix(),
            'defaults' => (object)collect(array_keys(User::ROLE_LABELS))->mapWithKeys(fn($role) => [$role => self::defaultsFor($role)])->all(),
        ];
    }
}
