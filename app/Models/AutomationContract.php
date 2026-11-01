<?php

namespace App\Models;

use App\Support\PersianNumber;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * قرارداد طراحی، تأمین، نصب و راه‌اندازی نیروگاه خورشیدی برای یک اتوماسیون فروش.
 * پیش‌نویس توسط کاربران ویرایش می‌شود؛ پس از تایید ادمین متن نهایی (snapshot) ثابت و لینک برای مشتری ارسال می‌شود
 * و مشتری قرارداد را به‌صورت دیجیتال امضا می‌کند.
 */
class AutomationContract extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'automation_contracts';

    const DRAFT = 0;     // پیش‌نویس / نیازمند تایید
    const APPROVED = 1;  // تایید ادمین و ارسال برای مشتری
    const SIGNED = 2;    // امضا شده توسط مشتری

    protected $fillable = [
        'automation_id', 'token', 'status', 'fields', 'snapshot', 'approved_by', 'approved_at', 'sms_sent', 'sent_at',
        'viewed_at', 'signer_name', 'signature', 'signer_ip', 'signed_at', 'signed_seen_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'fields' => 'array',
        'snapshot' => 'array',
        'sms_sent' => 'boolean',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
        'signed_seen_at' => 'datetime',
    ];

    protected $hidden = ['token', 'signature', 'snapshot', 'fields'];

    protected $appends = ['status_label'];

    // فیلدهای قابل ویرایش قرارداد (کلید => برچسب)؛ بقیه اطلاعات از اتوماسیون و مشتری خوانده می‌شود
    public static function fieldLabels(): array
    {
        return [
            'contract_number' => 'شماره قرارداد',
            'contract_date' => 'تاریخ قرارداد',
            'project_address' => 'نشانی پروژه',
            'plant_capacity' => 'ظرفیت نیروگاه',
            'plant_type' => 'نوع نیروگاه',
            'cheque_issuer' => 'صادرکننده چک‌ها',
            'late_penalty_percent' => 'وجه التزام تأخیر پرداخت (درصد روزانه)',
            'late_penalty_daily' => 'وجه التزام تأخیر پرداخت (تومان روزانه)',
            'execution_duration' => 'مدت اجرای پروژه',
            'warranty_panel' => 'مدت گارانتی پنل',
            'warranty_inverter' => 'مدت گارانتی اینورتر/سانورتر',
            'warranty_battery' => 'مدت گارانتی باتری',
            'warranty_structure' => 'مدت گارانتی سازه و تجهیزات حفاظتی',
            'termination_grace_days' => 'مهلت فسخ پس از اعلام کتبی (روز)',
            'arbiter' => 'داور مرضی‌الطرفین',
        ];
    }

    // ستون‌های هر ردیف «ماده ۳ - مشخصات فنی و اقلام»
    const TECH_ITEM_KEYS = ['product_id', 'title', 'count', 'spec', 'brand', 'warranty'];

    public static function defaultFields(Automation $automation): array
    {
        $fields = array_fill_keys(array_keys(self::fieldLabels()), null);
        $fields['contract_number'] = $automation->invoice_number;
        $fields['contract_date'] = (new Verta())->format('Y/m/d');
        $fields['project_address'] = $automation->customerSurvey?->address ?: $automation->customer?->address;
        $fields['tech_items'] = [];

        return $fields;
    }

    /**
     * فیلدهای ذخیره شده با پیش‌فرض‌ها تکمیل می‌شوند.
     * اقلام ماده ۳ همیشه با محصولات پیش فاکتور هم‌گام است: محصول جدید اضافه، محصول حذف شده برداشته و
     * تعداد به‌روز می‌شود؛ مشخصات وارد شده (توان، برند، گارانتی) و آیتم‌های دستی حفظ می‌شوند.
     */
    public function resolvedFields(): array
    {
        $saved = $this->fields ?? [];
        $values = array_filter(\Illuminate\Support\Arr::except($saved, ['tech_items']), fn($v) => $v !== null && $v !== '');
        $fields = array_merge(self::defaultFields($this->automation), $values);
        $fields['tech_items'] = $this->syncTechItems($saved['tech_items'] ?? []);

        return $fields;
    }

    private function syncTechItems(array $savedItems): array
    {
        $orders = $this->automation->orders()->with('product')->get();
        $savedByProduct = collect($savedItems)->filter(fn($i) => !empty($i['product_id']))->keyBy('product_id');

        $items = [];
        foreach ($orders as $order) {
            $saved = $savedByProduct->get($order->product_id, []);
            $items[] = [
                'product_id' => $order->product_id,
                'title' => $saved['title'] ?? $order->product?->name,
                'count' => (float)$order->count,
                'spec' => $saved['spec'] ?? $order->product?->technical,
                'brand' => $saved['brand'] ?? null,
                'warranty' => $saved['warranty'] ?? null,
            ];
        }

        // آیتم‌هایی که دستی به ماده ۳ اضافه شده‌اند
        foreach ($savedItems as $item) {
            if (empty($item['product_id']))
                $items[] = array_merge(array_fill_keys(self::TECH_ITEM_KEYS, null), $item);
        }

        return $items;
    }

    // پاک‌سازی اقلام ارسالی ماده ۳؛ ردیف بدون عنوان حذف می‌شود
    public static function sanitizeTechItems($items): array
    {
        return collect(is_array($items) ? $items : [])
            ->filter(fn($i) => is_array($i))
            ->map(fn($i) => collect($i)->only(self::TECH_ITEM_KEYS)
                ->map(fn($v) => is_string($v) ? trim($v) : $v)
                ->all())
            ->filter(fn($i) => !empty($i['title']) || !empty($i['product_id']))
            ->values()
            ->all();
    }

    /**
     * داده کامل قرارداد (فیلدهای دستی + اطلاعات مشتری، اقلام، مبلغ و پرداخت‌ها از اتوماسیون).
     * در لحظه تایید ادمین در snapshot ذخیره می‌شود تا متن امضا شده بعداً تغییر نکند.
     */
    public function buildData(): array
    {
        $automation = $this->automation()->with(['customer', 'orders.product', 'payments', 'cheques'])->first();
        $customer = $automation->customer;
        $total = (float)$automation->final_price;
        $prepayment = (float)$automation->payments->sum('amount');
        $jalali = fn($date) => $date ? (new Verta($date))->format('Y/m/d') : null;

        return [
            'template_version' => 1,
            'fields' => $this->resolvedFields(),
            'employer' => [
                'name' => $customer?->fullName,
                'national_code' => $customer?->national_code,
                'phone' => $customer?->contact_number,
                'address' => $customer?->address,
            ],
            'items' => $automation->orders->map(fn($o) => [
                'title' => $o->product?->name,
                'count' => (float)$o->count,
                'price' => (float)$o->price,
                'total' => (float)$o->count * (float)$o->price,
            ])->values()->all(),
            'total' => $total,
            'total_words' => PersianNumber::toWords($total) . ' تومان',
            'prepayment' => $prepayment,
            'remaining' => max($total - $prepayment, 0),
            'payments' => $automation->payments->map(fn($p) => [
                'method' => $p->method,
                'amount' => (float)$p->amount,
                'date' => $jalali($p->date),
            ])->values()->all(),
            'cheques' => $automation->cheques->map(fn($c) => [
                'bank' => $c->bank_name,
                'number' => $c->number,
                'due_date' => $jalali($c->due_date),
                'amount' => (float)$c->amount,
            ])->values()->all(),
            'cheques_total' => (float)$automation->cheques->sum('amount'),
        ];
    }

    // تغییر قرارداد یا فاکتور پس از تایید (و پیش از امضا) نیازمند بازبینی و تایید مجدد ادمین است
    public function invalidateApproval(): void
    {
        if ($this->status === self::APPROVED)
            $this->update(['status' => self::DRAFT, 'snapshot' => null]);
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(40);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public function link(): ?string
    {
        return $this->token ? rtrim(config('services.frontend_url'), '/') . '/contract/' . $this->token : null;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::APPROVED => 'تایید و ارسال شده برای مشتری',
            self::SIGNED => 'امضا شده توسط مشتری',
            default => 'پیش‌نویس (در انتظار تایید ادمین)',
        };
    }

    public function automation()
    {
        return $this->belongsTo(Automation::class, 'automation_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
