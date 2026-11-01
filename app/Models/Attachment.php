<?php

namespace App\Models;

use App\Traits\Uuids;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'attachments';
    protected $primaryKey = 'id';

    protected $fillable = [
        'ticket_id', 'user_id', 'file', 'description'
    ];


    protected $appends = [
        'file_address','jalali_date'
    ];

    protected $with = [
        'ticket'
    ];


    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public function getFileAddressAttribute()
    {
        return env('APP_URL') . '/attach/' . $this->attributes['file'];
    }


    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->attributes['created_at']);
        return $jalaliDate->format('Y/m/d');
    }

}
