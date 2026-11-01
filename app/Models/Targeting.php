<?php

namespace App\Models;

use App\Traits\Uuids;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Targeting extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'targeting';

    protected $fillable = [
        'user',
        'month',
        'calls',
        'sales',
        'start_date',
        'end_date',
        'description',
        'user_id'
    ];


    public $with = ['user'];
    public $appends = ['jalali_start_date', 'jalali_end_date', 'status'];


    public function getJalaliStartDateAttribute()
    {
        $jalaliDate = new Verta($this->start_date);
        return $jalaliDate->format('Y/m/d');
    }

    public function getJalaliEndDateAttribute()
    {
        $jalaliDate = new Verta($this->end_date);
        return $jalaliDate->format('Y/m/d');
    }

    public function getStatusAttribute()
    {
        // ستون user در این جدول شناسه کاربر است (نه رابطه)
        $userId = $this->getAttributes()['user'] ?? null;

        $calls = Call::where('user_id', $userId)->whereBetween('date', [$this->start_date, $this->end_date])->count() >= $this->calls;

        $salesSum = Automation::where('user_id', $userId)
            ->where('status', Automation::SUCCESS)
            ->whereBetween('created_at', [$this->start_date, $this->end_date])
            ->sum('final_price');

        $sales = $salesSum >= $this->sales;

        if ($this->end_date < Carbon::now()) {
            return [$calls && $sales, 0];
        } else {
            return [$calls && $sales, 1];
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user');
    }


}
