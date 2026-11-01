<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * فرم بازدید نوع «ثبت شده از مشتری» (survey_type = 3).
 * همان سوالات فرم اولیه‌ای است که مشتری از طریق لینک پیامک شده تکمیل می‌کند؛ بعداً در سامانه قابل ویرایش است.
 */
class AutomationCustomerSurvey extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'automation_customer_surveys';

    protected $fillable = [
        'automation_id', 'full_name', 'national_code', 'bill_id', 'address', 'available_area',
        'install_place', 'building_age', 'ownership', 'goal', 'property_type', 'surface_material', 'mobile',
    ];

    protected $casts = [
        'surface_material' => 'array',
        'available_area' => 'float',
    ];

    // پاسخ‌ها به‌صورت «عنوان سوال / مقدار» برای نمایش
    protected $appends = ['rows'];

    /**
     * سوالات فرم (همان سوالات فرم پرس‌لاین). کلید هر سوال نام ستون همین جدول است.
     * فرم عمومی مشتری و فرم ویرایش در سامانه هر دو از همین تعریف ساخته می‌شوند.
     */
    public static function questions(): array
    {
        return [
            ['key' => 'full_name', 'type' => 'text', 'title' => 'نام و نام خانوادگی', 'placeholder' => 'نام و نام خانوادگی'],
            ['key' => 'national_code', 'type' => 'national_code', 'title' => 'کدملی خود را وارد کنید', 'label' => 'کد ملی', 'placeholder' => '۰۰۱۲۳۴۵۶۷۸'],
            ['key' => 'bill_id', 'type' => 'digits', 'title' => 'شناسه قبض برق'],
            ['key' => 'address', 'type' => 'textarea', 'title' => 'آدرس محل نصب'],
            ['key' => 'available_area', 'type' => 'number', 'title' => 'فضای در دسترس', 'suffix' => 'متر مربع'],
            ['key' => 'install_place', 'type' => 'single', 'title' => 'مکان نصب',
                'options' => ['پشت بام مسطح', 'سقف شیروانی', 'زمین خالی']],
            ['key' => 'building_age', 'type' => 'single', 'title' => 'سن بنا',
                'options' => ['تا ۵ سال', 'از ۶ تا ۱۰ سال', 'از ۱۱ تا ۱۵ سال', 'از ۱۶ تا ۲۰ سال', 'از ۲۱ تا ۲۵ سال', 'بیش از ۲۶ سال', 'نمیدانم']],
            ['key' => 'ownership', 'type' => 'single', 'title' => 'نوع مالکیت',
                'options' => ['مالک', 'استیجاری', 'مشاع']],
            ['key' => 'goal', 'type' => 'single', 'title' => 'هدف از نصب نیروگاه خورشیدی چیست؟', 'label' => 'هدف از نصب نیروگاه',
                'options' => [
                    'تامین برق مستقل(off_grid)(بدون اتصال به شبکه،با باتری برای تامین انرژی مستقل)',
                    'درآمدزایی از فروش برق(on_grid)(اتصال به شبکه برق و فروش انرژی مازاد به شبکه)',
                    'کسب درآمد+برق اضطراری به هنگام قطعی برق(hybrid)(ترکیب on_grid و باتری برای پایداری بیشتر)',
                ]],
            ['key' => 'property_type', 'type' => 'single', 'title' => 'مشخصات',
                'options' => ['خانه و ویلا', 'آپارتمان', 'تجاری', 'اداری/خدماتی', 'سایر']],
            ['key' => 'surface_material', 'type' => 'multiple', 'max' => 2, 'title' => 'جنس سطح',
                'options' => ['ایزوگام', 'بتن', 'موزاییک', 'فلزی', 'سایر']],
            ['key' => 'mobile', 'type' => 'mobile', 'title' => 'شماره موبایل خود را وارد کنید', 'label' => 'شماره موبایل', 'placeholder' => '۰۹۱۲۳۴۵۶۷۸۹'],
        ];
    }

    public static function keys(): array
    {
        return array_column(self::questions(), 'key');
    }

    public function getRowsAttribute(): array
    {
        $rows = [];
        foreach (self::questions() as $q) {
            $value = $this->getAttribute($q['key']);
            if (is_array($value))
                $value = implode('، ', $value);
            if ($q['key'] === 'available_area' && $value !== null)
                $value = rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
            if (isset($q['suffix']) && $value !== null && $value !== '')
                $value .= ' ' . $q['suffix'];
            $rows[] = ['key' => $q['key'], 'title' => $q['label'] ?? $q['title'], 'value' => $value];
        }

        return $rows;
    }

    public function automation()
    {
        return $this->belongsTo(Automation::class, 'automation_id');
    }
}
