<?php

namespace App\Models;

use App\Traits\Uuids;
use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutomatonComment extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'comment_automation';

    protected $fillable = [
        'automation_id', 'user_id', 'content'
    ];

    protected $with = ["user"];

    protected $appends = ["jalali_date"];

    public function automation()
    {
        return $this->belongsTo(Automation::class, 'automation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getJalaliDateAttribute()
    {
        $persian_date = new Verta($this->created_at);
        return $persian_date->format('Y/m/d H:i');
    }
}
