<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SourceSms extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'sms_categories';

    protected $fillable = [
        'title',
        'description',
        'user_id'
    ];

    // جانگهدار لینک در متن قالب پیامک؛ کاربر می‌تواند جای آن را در متن تنظیم کند
    const LINK_PLACEHOLDER = '{link}';

    /**
     * متن قالب پیامک با عنوان داده شده را برمی‌گرداند و {link} را با لینک جایگزین می‌کند.
     * اگر قالب {link} نداشته باشد، لینک (با پیشوند اختیاری) به انتهای متن اضافه می‌شود.
     */
    public static function renderWithLink(string $title, string $default, string $link, string $fallbackPrefix = ''): string
    {
        $template = self::where('title', $title)->value('description') ?: $default;

        if (str_contains($template, self::LINK_PLACEHOLDER))
            return str_replace(self::LINK_PLACEHOLDER, $link, $template);

        return rtrim($template) . "\n\n" . $fallbackPrefix . $link;
    }

}
