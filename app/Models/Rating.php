<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Rating extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';


    protected $fillable = [
        'after_sale_service_id',
        'token',
        'rating',
        'feedback',
        'submitted_at',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'submitted_at' => 'datetime',
    ];

    public $appends = ['is_submitted'];

    public function afterSalesService()
    {
        return $this->belongsTo(AfterSalesService::class, 'after_sale_service_id');
    }

    public function getIsSubmittedAttribute(): bool
    {
        return !is_null($this->submitted_at);
    }

    /**
     * تولید توکن یکتای غیرقابل حدس برای لینک عمومی نظرسنجی.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public static function getOrCreateFor(AfterSalesService $service)
    {
        return DB::transaction(function () use ($service) {
            $survey = self::where('after_sale_service_id', $service->id)->first();

            if ($survey)
                return $survey;

            return self::create([
                'after_sale_service_id' => $service->id,
                'token' => self::generateToken(),
            ]);
        });
    }
}
