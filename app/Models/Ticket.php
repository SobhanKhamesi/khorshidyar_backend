<?php

namespace App\Models;

use App\Traits\Uuids;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    const UNDO = 0;
    const DONE = 1;
    const DOING = 2;

    protected $table = 'ticket';

    protected $fillable = [
        'status',
        'userId',
        'user_id',
        'customer_id',
        'title',
        'descriptionTicket',
        'time',
        'description',
    ];


    public $with = ['comments','user','user1','customer'];
    public $appends = ['jalali_full_date' , 'sabtKonande','anjamDahande','CommentsCount','MarbotBe','commentsCount'];


    public function getJalaliFullDateAttribute()
    {
        $jalaliDate = new Verta($this->attributes['created_at']);
        return $jalaliDate->format('Y/m/d ساعت H:i');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class, 'ticket_id');
    }
    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'ticket_id');
    }
    public function customer()
    {
        return $this->hasOne(Customer::class, 'id', 'customer_id');
    }

    public function user1()
    {
        return $this->hasOne(User::class, 'id', 'userId');
    }

    public function getSabtKonandeAttribute()
    {
        if ($this->user != null)
            return $this->user->name;

    }

    public function getMarbotBeAttribute()
    {
        if ($this->customer != null)
            return $this->customer->name;

    }

    public function getCommentsCountAttribute()
    {
        return $this->comments->count();
    }

    public function getAnjamDahandeAttribute(){

        if ($this->user1 != null)
            return $this->user1->name;
    }





}
