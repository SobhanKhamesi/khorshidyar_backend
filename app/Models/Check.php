<?php

namespace App\Models;

use App\Traits\Uuids;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Check extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    const PAYABLE = 0;
    const RECEIVABLE = 1;

    const PENDING = 0;
    const PASSED = 1;
    const RETURNED = 2;

    protected $fillable = [
        'type',
        'status',
        'amount',
        'date',
        'series',
        'serial',
        'bank_id',
        'invoice_id',
        'customer_id',
        'bank_account_id',
        'description',
        'user_id'
    ];


    public $appends = ['jalali_date'];
    public $with = ['customer', 'bankAccount', 'bank'];


    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->date);
        return $jalaliDate->format('Y/m/d');
    }



    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withStats();
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }


    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }



}
