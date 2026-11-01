<?php

namespace App\Models;

use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstallerAppointment extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    //type
    const TYPE_PANEL = 1;
    const TYPE_WIRING = 2;
    const TYPE_COMMISSIONING = 3;
    const TYPE_AFTER_SALE = 4;

    //status
    const RESERVED = 1;
    const DONE = 2;
    const CANCELED = 3;

    protected $fillable = [
        'user_id',
        'customer_id',
        'automation_id',
        'installation_service_id',
        'after_sale_service_id',
        'type',
        'date',
        'start_time',
        'end_time',
        'status',
        'description',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    protected $appends = ['jalali_date'];

    protected $with = ['installer', 'customer'];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_PANEL => 'نصب پنل',
            self::TYPE_WIRING => 'نصب سیم‌کشی',
            self::TYPE_COMMISSIONING => 'نصب و راه‌اندازی',
            self::TYPE_AFTER_SALE => 'خدمات پس از فروش',
        ];
    }

    public static function typeKeys(): array
    {
        return [
            self::TYPE_PANEL => 'panel',
            self::TYPE_WIRING => 'wiring',
            self::TYPE_COMMISSIONING => 'commissioning',
            self::TYPE_AFTER_SALE => 'after_sale',
        ];
    }

    public function getTypeLabelAttribute()
    {
        return self::typeLabels()[$this->type] ?? null;
    }

    public function getTypeKeyAttribute()
    {
        return self::typeKeys()[$this->type] ?? null;
    }

    public function getJalaliDateAttribute()
    {
        if ($this->date != null) {
            $jalaliDate = new Verta($this->date);
            return $jalaliDate->format('Y/m/d');
        }

        return null;
    }

    public function installer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withStats();
    }

    public function automation()
    {
        return $this->belongsTo(Automation::class);
    }

    public function installationService()
    {
        return $this->belongsTo(InstallationService::class);
    }

    public function afterSalesService()
    {
        return $this->belongsTo(AfterSalesService::class, 'after_sale_service_id');
    }

    public function scopeForInstaller($query, $installerId)
    {
        return $query->where('user_id', $installerId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', '!=', self::CANCELED);
    }
}
