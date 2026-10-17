<?php

namespace App\Models;

use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accounting extends Model
{

    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
    protected $table = 'accounting';

    const PRE = 0;
    const PRE_LEAD = 1;
    const PURCHASE = 2;
    const SALES = 3;
    const RETURN_FROM_PURCHASE = 4;
    const RETURN_FROM_SALES = 5;

    protected $fillable = [
        'type',
        'number',
        'date',
        'customer_id',
        'final_price',
        'pay_pos',
        'pos_bank_account_id',
        'pay_cash',
        'description',
        'user_id'
    ];

    public $with = ['customer', 'orders', 'checks', 'posBankAccount'];
    public $appends = ['jalali_date', 'lastStatus', 'sum_metrajh'];

    public function customer()
    {
        return $this->hasOne(Customer::class, 'id', 'customer_id');
    }

    public function posBankAccount()
    {
        return $this->hasOne(BankAccount::class, 'id', 'pos_bank_account_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'invoice_id', 'id');
    }

    public function checks()
    {
        return $this->hasMany(Check::class, 'invoice_id', 'id');
    }

    public function getLastStatusAttribute()
    {
        if ($this->type == self::PRE_LEAD) {
            $preInvoiceStatusList = PreInvoiceStatus::where('invoice_id', $this->id)->latest('created_at')->orderbyDesc('date')->first();
            if ($preInvoiceStatusList) {
                return $preInvoiceStatusList->status;
            } else {
                return 'پیش فاکتور ثبت شده';
            }
        }
        return null;
    }


    public function getTotalAmountAttribute()
    {
        return $this->orders->sum('selling_price') + $this->orders->sum('purchase_price');
    }
    public function getSumMetrajhAttribute()
    {
        return $this->orders->sum('metrajh');
    }

    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->date);
        return $jalaliDate->format('Y/m/d');
    }


    public function getJalaliMonthAttribute()
    {
        $jalaliDate = new Verta($this->date);
        return $jalaliDate->format('%B');
    }


    public function getMonthAttribute()
    {
        $date = new Carbon($this->date);
        return $date->format('m');
    }


}
