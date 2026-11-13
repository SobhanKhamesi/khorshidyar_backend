<?php

namespace App\Support;

use App\Models\AutomationContract;
use App\Models\Customer;
use App\Models\CustomerIntakeForm;
use App\Models\Rating;
use App\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * اعلان‌های سامانه به‌صورت «یادآوری» برای کاربر جاری ساخته می‌شوند تا در لیست یادآوری‌ها و زنگوله منوی بالا نمایش داده شوند:
 *  - فرم اولیه‌ای که مشتری/سرنخ تکمیل کرده و هنوز در کارتابل دیده نشده
 *  - قراردادی که مشتری امضا کرده و هنوز در کارتابل دیده نشده
 *  - نظرسنجی خدمات پس از فروش با رضایت «ضعیف» که دلیل نارضایتی آن هنوز بررسی نشده
 *
 * برای هر کاربر و هر رکورد فقط یک یادآوری ساخته می‌شود (فقط رکوردهایی که کاربر به آن‌ها دسترسی دارد).
 * وقتی رکورد مبدا رسیدگی شد (دیده شد / بررسی شد)، یادآوری آن خودکار «تکمیل شده» می‌شود.
 */
class SystemReminders
{
    public static function sync(): void
    {
        $userId = auth()->id();
        if (!$userId)
            return;

        // [type => [source_id => ['title' => ..., 'description' => ..., 'customer_id' => ...]]]
        $pending = [
            Reminder::TYPE_INTAKE_FORM => self::intakeForms(),
            Reminder::TYPE_CONTRACT_SIGNED => self::signedContracts(),
            Reminder::TYPE_POOR_RATING => self::poorRatings(),
        ];

        $existing = Reminder::where('user_id', $userId)->system()
            ->get(['id', 'type', 'source_id', 'status'])
            ->groupBy(fn($r) => (int)$r->type);

        foreach ($pending as $type => $items) {
            /** @var Collection $mine */
            $mine = ($existing[$type] ?? collect())->keyBy('source_id');

            // رکوردهایی که رسیدگی شده‌اند: یادآوری فعالشان تکمیل می‌شود
            $resolved = $mine->filter(fn($r) => (int)$r->status === Reminder::ACTIVE && !isset($items[$r->source_id]));
            if ($resolved->isNotEmpty())
                Reminder::whereIn('id', $resolved->pluck('id'))->update(['status' => Reminder::COMPLETED]);

            // رکوردهای جدید: یادآوری ساخته می‌شود (اگر قبلاً ساخته/تکمیل/حذف نشده باشد)
            foreach ($items as $sourceId => $item) {
                if ($mine->has($sourceId))
                    continue;

                try {
                    Reminder::create([
                        'title' => Str::limit($item['title'], 250),
                        'description' => $item['description'],
                        'date' => Carbon::now(),
                        'status' => Reminder::ACTIVE,
                        'type' => $type,
                        'source_id' => $sourceId,
                        'customer_id' => $item['customer_id'],
                        'user_id' => $userId,
                    ]);
                } catch (QueryException $e) {
                    // درخواست هم‌زمان دیگری همین یادآوری را ساخته است (کلید یکتا)
                }
            }
        }
    }

    private static function customerKind(?Customer $customer): string
    {
        return match ($customer?->status) {
            Customer::LEAD => 'سرنخ',
            Customer::OPPORTUNITY => 'فرصت',
            default => 'مشتری',
        };
    }

    private static function intakeForms(): array
    {
        // یادآوری فرمی که دیگر در لیست درخواست ها نیست، در sync خودکار «تکمیل شده» می‌شود
        return CustomerIntakeForm::with('customer')
            ->pendingReview()
            ->get()
            ->mapWithKeys(fn(CustomerIntakeForm $form) => [$form->id => [
                'title' => 'فرم اولیه توسط ' . self::customerKind($form->customer) . ' «' . ($form->customer?->name ?? '-') . '» تکمیل شد',
                'description' => 'فرم اولیه تکمیل شده در کارتابل «لیست درخواست ها» منتظر بررسی است.',
                'customer_id' => $form->customer_id,
            ]])
            ->all();
    }

    private static function signedContracts(): array
    {
        return AutomationContract::with('automation.customer')
            ->where('status', AutomationContract::SIGNED)->whereNull('signed_seen_at')
            ->whereHas('automation', fn($q) => Access::scopeAutomations($q))
            ->get()
            ->mapWithKeys(function (AutomationContract $contract) {
                $customer = $contract->automation?->customer;
                $number = $contract->snapshot['fields']['contract_number'] ?? null;

                return [$contract->id => [
                    'title' => 'قرارداد «' . ($customer?->name ?? '-') . '»' . ($number ? ' به شماره ' . $number : '') . ' توسط مشتری امضا شد',
                    'description' => 'امضاکننده: ' . ($contract->signer_name ?? '-') . ' — کارتابل «لیست مذاکره و قرارداد»',
                    'customer_id' => $customer?->id,
                ]];
            })
            ->all();
    }

    private static function poorRatings(): array
    {
        if (!Access::can('after_sales.review_ratings'))
            return [];

        return Rating::with('afterSalesService.customer')
            ->needsReview()
            ->get()
            ->mapWithKeys(function (Rating $rating) {
                $customer = $rating->afterSalesService?->customer;

                return [$rating->id => [
                    'title' => 'نظرسنجی خدمات پس از فروش «' . ($customer?->name ?? '-') . '» با رضایت «ضعیف» ثبت شد',
                    'description' => 'امتیاز: ' . $rating->rating . ' از ۵'
                        . ($rating->feedback ? ' — نظر مشتری: ' . $rating->feedback : '')
                        . "\nدلیل نارضایتی را بررسی و نتیجه را ثبت کنید.",
                    'customer_id' => $customer?->id,
                ]];
            })
            ->all();
    }
}
