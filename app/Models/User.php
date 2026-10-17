<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laratrust\Traits\HasRolesAndPermissions;
class User extends Authenticatable
{

    use HasFactory, HasApiTokens, Notifiable,HasRolesAndPermissions;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
    public $timestamps = false;

    const ADMIN = 0;
    const KARSHENAS_FANI = 1;
    const NASSAB = 2;
    const KARSHENAS_FOROSH = 3;
    const MODIR_FOROSH = 3;


    const ACTIVE = 0;
    const INACTIVE = 1;

    protected $fillable = [
        'name', 'username', 'password', 'status', 'role', 'profile', 'emza'
    ];

    protected $hidden = [
        'password'
    ];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }
}
