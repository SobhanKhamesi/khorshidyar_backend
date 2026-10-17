<?php

namespace App\Models;

use App\Traits\Uuids;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
    protected $table = 'ticket_comment';

    protected $fillable = [
        'ticket_id', 'user_id', 'content'
    ];



    public function invoice()
    {
        return $this->hasOne(Ticket::class, 'id', 'ticket_id');
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
        $jalaliDate = new Verta($this->attributes['created_at']);
        return $jalaliDate->format('Y/m/d ساعت H:i');
    }


}
