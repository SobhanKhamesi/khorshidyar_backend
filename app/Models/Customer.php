<?php

namespace App\Models;

use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    //status
    const LEAD = 0;
    const OPPORTUNITY = 1;
    //وضعیت آیتم اگر از حالت فرصت به مشتری تغییر پیدا کند
    const TO = 2;
    const CUSTOMER = 3;


    //type
    const MALE = 0;
    const FEMALE = 1;
    const JURIDICAL = 2;

    //just for frontend
    const NATURAL = 3;


    protected $casts = [
        'status' => 'integer',
        'type' => 'integer',
        'type_call' => 'integer',
        'lead_status' => 'integer',
    ];

    protected $fillable = [
        'contact_number',
        'contact_number1',
        'contact_number2',
        'national_code',
        'postal_code',
        'address',
        'landline_number',
        'status',
        'type',
        'name',
        'city',
        'province',
        'birth_date',
        'marriage_date',
        'email',
        'eitaa',
        'whatsapp',
        'telegram',
        'instagram',
        'website',
        'job',
        'disc',
        'job_address',
        'category_id',
        'description',
        'lead_status',
        'reject_reason',
        'user_id',
        'source_id',
        'referrer_id',
        'referrer_percent',
    ];

    public $with = ['category', 'persons', 'source', 'referrer'];
    public $appends = ['jalali_created', 'current_automation', 'fullName', 'gender', 'jalali_birth_date', 'jalali_marriage_date', 'custom_date_1', 'custom_date', 'SabtKonande', 'sum_invoices_price'];

    // مقادیر کمکی که توسط scopeWithStats محاسبه می‌شوند و نباید در خروجی بیایند
    protected $hidden = ['current_automation_status', 'success_invoices_sum'];

    /**
     * محاسبه فیلدهای appends در همان کوئری اصلی (به جای چند کوئری جدا برای هر مشتری).
     * شرط‌ها عمداً بدون binding نوشته شده‌اند تا در کنار pluck/select/exists هم امن باشند.
     */
    public function scopeWithStats(Builder $query): Builder
    {
        return $query
            ->with('user')
            ->withMax(['automations as current_automation_status' => fn($q) => $q->whereRaw(
                'automation.status in (' . Automation::PRE_INVOICE . ',' . Automation::NEGOTIATION . ')'
            )], 'status')
            ->withSum(['automations as success_invoices_sum' => fn($q) => $q->whereRaw(
                'automation.status = ' . Automation::SUCCESS
            )], 'final_price');
    }

    public function getCustomDate1Attribute()
    {
        $jalaliDate = new Verta($this->created_at);
        return $jalaliDate->format('%B Y');
    }

    public function getCustomDateAttribute()
    {
        $jalaliDate = new Verta($this->created_at);
        return $jalaliDate->format('Y/m/d');
    }

    // اگر مذاکره باز داشته باشد NEGOTIATION، اگر پیش فاکتور باز داشته باشد PRE_INVOICE، در غیر این صورت null
    public function getCurrentAutomationAttribute()
    {
        $status = array_key_exists('current_automation_status', $this->attributes)
            ? $this->attributes['current_automation_status']
            : $this->automations()->whereIn('status', [Automation::PRE_INVOICE, Automation::NEGOTIATION])->max('status');

        return $status === null ? null : (int)$status;
    }

    public function getGenderAttribute()
    {
        if ($this->type == self::MALE || $this->type == self::FEMALE) {
            return $this->type;
        } else {
            return null;
        }
    }

    public function getSumInvoicesPriceAttribute()
    {
        if (array_key_exists('success_invoices_sum', $this->attributes))
            return $this->attributes['success_invoices_sum'] ?? 0;

        return $this->automations()->where('status', Automation::SUCCESS)->sum('final_price');
    }

    public function getFullNameAttribute()
    {
        return match ($this->type) {
            self::MALE => 'آقای ' . $this->name,
            self::FEMALE => 'خانم ' . $this->name,
            self::JURIDICAL => 'شرکت ' . $this->name,
            default => $this->name,
        };
    }

    // نام با پیشوند آقای/خانم برای متن پیامک‌ها (اشخاص حقوقی بدون پیشوند)
    public function getGreetingNameAttribute()
    {
        return match ($this->type) {
            self::MALE => 'آقای ' . $this->name,
            self::FEMALE => 'خانم ' . $this->name,
            default => (string)$this->name,
        };
    }

    public function getJalaliBirthDateAttribute()
    {
        if ($this->birth_date != null) {
            $jalaliDate = new Verta($this->birth_date);
            return $jalaliDate->format('Y/m/d');
        } else {
            return null;
        }
    }

    public function getJalaliMarriageDateAttribute()
    {
        if ($this->marriage_date != null) {
            $jalaliDate = new Verta($this->marriage_date);
            return $jalaliDate->format('Y/m/d');
        } else {
            return null;
        }
    }

    public function referrer()
    {
        return $this->belongsTo(Referrer::class);
    }

    public function category()
    {
        return $this->belongsTo(CustomerCategory::class, 'category_id');
    }

    public function calls()
    {
        return $this->hasMany(Call::class);
    }

    public function automations()
    {
        return $this->hasMany(Automation::class, 'customer_id', 'id');
    }

    public function installation_services()
    {
        return $this->hasMany(InstallationService::class, 'customer_id', 'id');
    }

    public function after_sale_services()
    {
        return $this->hasMany(AfterSalesService::class, 'customer_id', 'id');
    }

    public function persons()
    {
        return $this->hasMany(CoPerson::class);
    }

    public function sms()
    {
        return $this->hasMany(Sms::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function source()
    {
        return $this->belongsTo(SourceCategory::class, 'source_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'customer_id', 'id');
    }

    public function getSabtKonandeAttribute()
    {
        return $this->user?->name;
    }

    public function getJalaliCreatedAttribute()
    {
        $jalaliDate = new Verta($this->attributes['created_at']);
        return $jalaliDate->format('Y/m/d ساعت H:i');
    }

    /**
     * حذف مشتری/سرنخ به همراه داده‌های وابسته، به‌صورت اتمیک و با کوئری‌های گروهی.
     */
    public function deleteWithRelations(): bool
    {
        return DB::transaction(function () {
            $automationIds = $this->automations()->pluck('id');
            if ($automationIds->isNotEmpty()) {
                Order::whereIn('automation_id', $automationIds)->delete();
                AutomatonComment::whereIn('automation_id', $automationIds)->delete();
                AutomationContractItem::whereIn('automation_id', $automationIds)->delete();
                AutomationPayment::whereIn('automation_id', $automationIds)->delete();
                AutomationCheque::whereIn('automation_id', $automationIds)->delete();
                AutomationCustomerSurvey::whereIn('automation_id', $automationIds)->delete();

                $homeSurveyIds = AutomationHomeSurvey::whereIn('automation_id', $automationIds)->pluck('id');
                AutomationHomeGasCooler::whereIn('home_survey_id', $homeSurveyIds)->delete();
                AutomationHomeSurvey::whereIn('id', $homeSurveyIds)->delete();

                $industrialSurveyIds = AutomationIndustrialSurvey::whereIn('automation_id', $automationIds)->pluck('id');
                AutomationIndustrialMainConsumer::whereIn('industrial_survey_id', $industrialSurveyIds)->delete();
                AutomationIndustrialSurvey::whereIn('id', $industrialSurveyIds)->delete();

                Automation::whereIn('id', $automationIds)->delete();
            }

            $ticketIds = $this->tickets()->pluck('id');
            if ($ticketIds->isNotEmpty()) {
                TicketComment::whereIn('ticket_id', $ticketIds)->delete();
                Attachment::whereIn('ticket_id', $ticketIds)->delete();
                Ticket::whereIn('id', $ticketIds)->delete();
            }

            CustomerIntakeForm::where('customer_id', $this->id)->delete();
            $this->calls()->delete();
            $this->persons()->delete();
            $this->sms()->delete();

            return (bool)$this->delete();
        });
    }

}
