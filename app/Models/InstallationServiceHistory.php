<?php

namespace App\Models;

use Hekmatinasser\Verta\Verta;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationServiceHistory extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'installation_service_id',
        'user_id',
        'stage',     // مرحله‌ای که سرویس نصب به آن منتقل شد
        'description',
    ];

    protected $with = ['user:id,name'];

    public $appends = ['jalali_date', 'stage_label'];

    public function installationService(): BelongsTo
    {
        return $this->belongsTo(InstallationService::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getJalaliDateAttribute()
    {
        $jalaliDate = new Verta($this->created_at);
        return $jalaliDate->format('Y/m/d ساعت H:i');
    }

    public function getStageLabelAttribute()
    {
        return InstallationService::STAGE_LABELS[$this->stage] ?? null;
    }
}
