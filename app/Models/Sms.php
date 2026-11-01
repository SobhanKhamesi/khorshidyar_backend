<?php

namespace App\Models;

use App\Traits\Uuids;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Morilog\Jalali\Jalalian;

class Sms extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'sms';

    const TYPE_CUSTOMERS = 'customers';
    const TYPE_LEADS = 'leads';
    const TYPE_BOTH = 'both';
    const TYPE_SPECIFIC = 'specific';

    protected $casts = [
        'receivers' => 'array',
    ];


    protected $appends = [
        'jalali_created_at', 'SabtKonande', 'receiver_type_label'
    ];

    public $with = ['customer', 'category', 'user'];

    protected $fillable = [
        'send_to_all',
        'receiver_type',
        'category_id',
        'body',
        'count',
        'receivers',
        'customer_id',
        'user_id'
    ];

    public function getJalaliCreatedAtAttribute()
    {
        $createdDateTime = $this->attributes['created_at'];
        $carbonDateTime = Carbon::parse($createdDateTime, 'UTC')->addHours(3)->addMinutes(30);
        $jalaliDateTime = Jalalian::fromCarbon($carbonDateTime);
        return $jalaliDateTime->format('Y/m/d ساعت H:i');
    }


    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withStats();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category()
    {
        return $this->belongsTo(CustomerCategory::class, 'category_id', 'id');
    }

    public function getSabtKonandeAttribute()
    {
        return $this->user->name ?? null;
    }

    public function getReceiverTypeLabelAttribute()
    {
        return match ($this->receiver_type) {
            self::TYPE_CUSTOMERS => 'مشتریان',
            self::TYPE_LEADS => 'سرنخ‌ها',
            self::TYPE_BOTH => 'مشتریان و سرنخ‌ها',
            self::TYPE_SPECIFIC => 'اشخاص خاص',
            default => 'خودکار',
        };
    }

}
