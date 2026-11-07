<?php

namespace App\Support;

use App\Models\AfterSalesService;
use App\Models\Automation;
use App\Models\Customer;
use App\Models\InstallationService;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * قوانین دسترسی کل سامانه (یک جا برای همه‌ی کنترلرها).
 *
 * دسترسی هر نقش از App\Support\Permissions خوانده می‌شود و توسط ادمین در صفحه‌ی «سطوح دسترسی» قابل ویرایش است.
 * ادمین همیشه همه‌ی دسترسی‌ها را دارد؛ مدیریت کاربران و خود سطوح دسترسی فقط برای ادمین است.
 *
 *  فروش       : sales.access ← فقط داده‌های خودش (ثبت کرده یا کارشناس فنی آن بوده)، sales.view_all ← همه
 *  سرویس نصب  : installation.view_assigned ← ارجاع شده برای نصب/بررسی به خودش، installation.view_all ← همه
 *  پس از فروش : after_sales.view_assigned ← مسئول آن یا دارای نوبت، after_sales.view_all ← همه
 *  امور اداری : یادآوری، قرار ملاقات، چک، گزارش روزانه و ارجاع ← فقط مال خودش، personal.view_all ← همه
 */
class Access
{
    public static function user(): ?User
    {
        return auth()->user();
    }

    public static function role(): ?int
    {
        $user = self::user();
        return $user ? (int)$user->role : null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === User::ADMIN;
    }

    /** آیا نقش کاربر جاری این دسترسی را دارد؟ (کلیدهای App\Support\Permissions) */
    public static function can(string $permission): bool
    {
        return Permissions::roleHas(self::role(), $permission);
    }

    /** دسترسی‌های کاربر جاری (برای ارسال به فرانت) */
    public static function permissions(): array
    {
        return Permissions::forRole(self::role());
    }

    public static function isSalesManager(): bool
    {
        return self::role() === User::MODIR_FOROSH;
    }

    public static function isInstaller(): bool
    {
        return self::role() === User::NASSAB;
    }

    public static function isTechnicalExpert(): bool
    {
        return self::role() === User::KARSHENAS_FANI;
    }

    // دسترسی به بخش فروش (سرنخ، فرصت، مشتری، معرف، اتوماسیون فروش)
    public static function canUseSales(): bool
    {
        return self::can('sales.access');
    }

    // همه‌ی داده‌های فروش را می‌بیند (نه فقط مال خودش)
    public static function seesAllSales(): bool
    {
        return self::can('sales.view_all');
    }

    // مراحل مدیریتی فروش (پیش فاکتور ← مذاکره، تایید قرارداد، فروش موفق، بازگردانی و اصلاحیه/ابطال فروش موفق)
    public static function canManageSalesStages(): bool
    {
        return self::can('sales.manage_stages');
    }

    // ================= scope ها =================

    /** مشتری/سرنخ/فرصت: ثبت کرده یا در اتوماسیونی از آن ثبت کننده/کارشناس فنی بوده */
    public static function scopeCustomers(Builder $query): Builder
    {
        if (self::seesAllSales())
            return $query;
        if (!self::canUseSales())
            return $query->whereRaw('1 = 0');

        $id = auth()->id();
        $table = $query->getModel()->getTable();

        return $query->where(fn($q) => $q->where("$table.user_id", $id)
            ->orWhereIn("$table.id", Automation::withoutEagerLoads()->select('customer_id')
                ->where(fn($a) => $a->where('user_id', $id)->orWhere('expert_id', $id))));
    }

    /** اتوماسیون فروش: ثبت کننده یا کارشناس فنی آن */
    public static function scopeAutomations(Builder $query): Builder
    {
        if (self::seesAllSales())
            return $query;
        if (!self::canUseSales())
            return $query->whereRaw('1 = 0');

        $id = auth()->id();
        $table = $query->getModel()->getTable();

        return $query->where(fn($q) => $q->where("$table.user_id", $id)->orWhere("$table.expert_id", $id));
    }

    /** سرویس نصب: همه، یا ارجاع شده برای نصب (غیر پیش‌نویس) یا بررسی فنی به خودش */
    public static function scopeInstallations(Builder $query): Builder
    {
        if (self::can('installation.view_all'))
            return $query;
        if (!self::can('installation.view_assigned'))
            return $query->whereRaw('1 = 0');

        $id = auth()->id();

        return $query->where(fn($q) => $q
            ->where(fn($w) => $w->whereHas('activeAppointments', fn($a) => $a->where('user_id', $id))
                ->where('stage', '!=', InstallationService::STAGE_DRAFT))
            ->orWhere('technical_expert_id', $id));
    }

    /** خدمات پس از فروش: همه، یا مسئول آن / دارای نوبت در آن */
    public static function scopeAfterSales(Builder $query): Builder
    {
        if (self::can('after_sales.view_all'))
            return $query;
        if (!self::can('after_sales.view_assigned'))
            return $query->whereRaw('1 = 0');

        $id = auth()->id();

        return $query->where(fn($q) => $q->where('responsible_user_id', $id)
            ->orWhereHas('installerAppointments', fn($a) => $a->where('user_id', $id)));
    }

    /** امور اداری (یادآوری، قرار ملاقات، چک، گزارش روزانه و ...): همه یا فقط مال خودش */
    public static function scopeOwned(Builder $query, string $column = 'user_id'): Builder
    {
        return self::can('personal.view_all') ? $query : $query->where($query->getModel()->getTable() . '.' . $column, auth()->id());
    }

    // ================= بررسی یک رکورد =================

    public static function canSeeCustomer(?Customer $customer): bool
    {
        return $customer && self::scopeCustomers(Customer::withoutEagerLoads()->whereKey($customer->getKey()))->exists();
    }

    public static function canSeeAutomation(?Automation $automation): bool
    {
        return $automation && self::scopeAutomations(Automation::withoutEagerLoads()->whereKey($automation->getKey()))->exists();
    }

    public static function canSeeInstallation(?InstallationService $service): bool
    {
        return $service && self::scopeInstallations(InstallationService::withoutEagerLoads()->whereKey($service->getKey()))->exists();
    }

    public static function canSeeAfterSale(?AfterSalesService $service): bool
    {
        return $service && self::scopeAfterSales(AfterSalesService::withoutEagerLoads()->whereKey($service->getKey()))->exists();
    }

    /** ویرایش امور اداری: فقط صاحب آن (یا ادمین) */
    public static function owns($model, string $column = 'user_id'): bool
    {
        return self::isAdmin() || ($model && (string)$model->{$column} === (string)auth()->id());
    }

    /** مشاهده‌ی امور اداری: صاحب آن یا دارای دسترسی مشاهده‌ی همه */
    public static function canSeeOwned($model, string $column = 'user_id'): bool
    {
        return self::can('personal.view_all') || self::owns($model, $column);
    }

    // ================= توقف درخواست =================

    public static function deny(string $message = 'دسترسی به این بخش برای شما مجاز نیست')
    {
        abort(403, $message);
    }

    public static function ensure(bool $allowed, string $message = 'دسترسی به این بخش برای شما مجاز نیست'): void
    {
        if (!$allowed)
            self::deny($message);
    }

    /** فقط ادمین (مدیریت کاربران و سطوح دسترسی) */
    public static function ensureAdmin(): void
    {
        self::ensure(self::isAdmin(), 'این عملیات فقط برای مدیر مجاز است');
    }

    public static function ensureCan(string $permission, string $message = 'شما دسترسی لازم برای این عملیات را ندارید'): void
    {
        self::ensure(self::can($permission), $message);
    }

    public static function ensureSales(): void
    {
        self::ensure(self::canUseSales());
    }

    public static function ensureCustomer(?Customer $customer): void
    {
        self::ensure(self::canSeeCustomer($customer));
    }

    public static function ensureAutomation(?Automation $automation): void
    {
        self::ensure(self::canSeeAutomation($automation));
    }

    public static function ensureInstallation(?InstallationService $service): void
    {
        self::ensure(self::canSeeInstallation($service));
    }

    public static function ensureAfterSale(?AfterSalesService $service): void
    {
        self::ensure(self::canSeeAfterSale($service));
    }

    public static function ensureOwns($model, string $column = 'user_id'): void
    {
        self::ensure(self::owns($model, $column));
    }

    public static function ensureCanSeeOwned($model, string $column = 'user_id'): void
    {
        self::ensure(self::canSeeOwned($model, $column));
    }
}
