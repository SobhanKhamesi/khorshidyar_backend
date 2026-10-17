<?php

namespace App\Models;

use App\Traits\Uuids;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    public $with = ['category', 'persons','source','referrer'];
    public $appends = ['jalali_created','current_automation','fullName', 'gender', 'jalali_birth_date', 'jalali_marriage_date', 'custom_date_1','custom_date','SabtKonande','sum_invoices_price'];

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
    public function getCurrentAutomationAttribute()
    {
        if (count($this->automations()->where("status" , Automation::NEGOTIATION)->get()) > 0)
            return Automation::NEGOTIATION;
        if (count($this->automations()->where("status" , Automation::PRE_INVOICE)->get()) > 0)
            return Automation::PRE_INVOICE;
        return null;
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
        return $this->automations()->where("status" , Automation::SUCCESS)->sum("final_price");
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


    public function getJalaliBirthDateAttribute()
    {
        if (isset($this->birth_date) && $this->birth_date != null) {
            $jalaliDate = new Verta($this->birth_date);
            return $jalaliDate->format('Y/m/d');
        } else {
            return null;
        }
    }

    public function getJalaliMarriageDateAttribute()
    {
        if (isset($this->marriage_date) && $this->marriage_date != null) {
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
        return $this->hasOne(CustomerCategory::class, 'id', 'category_id');
    }

    public function calls()
    {
        return $this->hasMany(Call::class);
    }

    public function automations()
    {
        return $this->hasMany(Automation::class , 'customer_id' , 'id');
    }
    public function installation_services()
    {
        return $this->hasMany(InstallationService::class , 'customer_id' , 'id');
    }
    public function after_sale_services()
    {
        return $this->hasMany(AfterSalesService::class , 'customer_id' , 'id');
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
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function source()
    {
        return $this->hasOne(SourceCategory::class, 'id', 'source_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'customer_id', 'id');
    }

    public function getSabtKonandeAttribute()
    {
        return $this->user->name;
    }
    public function getJalaliCreatedAttribute()
    {
        $jalaliDate = new Verta($this->attributes['created_at']);
        return $jalaliDate->format('Y/m/d ساعت H:i');
    }

}
