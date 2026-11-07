<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * دسترسی‌های ذخیره شده‌ی یک نقش (فقط از طریق App\Support\Permissions خوانده و ذخیره شود)
 */
class RolePermission extends Model
{
    protected $primaryKey = 'role';
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = ['role', 'permissions', 'updated_by'];

    protected $casts = [
        'role' => 'integer',
        'permissions' => 'array',
    ];
}
