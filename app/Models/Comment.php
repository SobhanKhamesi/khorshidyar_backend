<?php

namespace App\Models;

use App\Traits\Uuids;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
    protected $table = 'comment';

    protected $fillable = [
        'leave_id', 'user_id', 'content'
    ];



    public function invoice()
    {
        return $this->hasOne(Leave::class, 'id', 'leave_id');
    }


    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    protected $appends = [
        'user'
    ];

    public function getUserAttribute()
    {
        return $this->user()->first() ? $this->user()->first()->name : null;
    }


    public function getCreatedAtAttribute($value)
    {
        $persian_date = new Verta($value);
        return $persian_date->format('Y/m/d H:i');
    }


}
