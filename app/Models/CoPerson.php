<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoPerson extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = "co_persons";

    protected $fillable = [
        'customer_id',
        'gender',
        'name',
        'contact_number',
        'position',
        'disc',
        'whatsapp',
        'email',
        'user_id'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

}
