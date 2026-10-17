<?php

namespace App\Models;

use App\Traits\Uuids;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreInvoiceStatus extends Model
{


    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';


    protected $table = "pre_invoice_status";

    protected $fillable = [
        'invoice_id',
        'date',
        'status',
        'reject_reason',
        'description',
        'user_id'
    ];


    public $appends = ['jalali_date'];

    public function invoice()
    {
        return $this->hasOne(Accounting::class);
    }

    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->date);
        return $jalaliDate->format('Y/m/d');
    }


}
