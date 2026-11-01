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
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // نام نویسنده با همین رابطه eager load می‌شود؛ رابطه user به خاطر هم‌نامی با فیلد خروجی user استفاده نمی‌شود
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->select('id', 'name');
    }

    protected $with = ['author'];

    protected $hidden = ['author'];

    protected $appends = [
        'user'
    ];

    public function getUserAttribute()
    {
        return $this->author?->name;
    }


    public function getCreatedAtAttribute($value)
    {
        $jalaliDate = new Verta($this->attributes['created_at']);
        return $jalaliDate->format('Y/m/d ساعت H:i');
    }


}
