<?php

namespace App\Support;

/**
 * تبدیل عدد به حروف فارسی و ارقام لاتین به فارسی (برای متن قرارداد).
 */
class PersianNumber
{
    private const ONES = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
    private const TEENS = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
    private const TENS = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
    private const HUNDREDS = ['', 'صد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
    private const SCALES = ['', 'هزار', 'میلیون', 'میلیارد', 'هزار میلیارد'];

    public static function toWords($number): string
    {
        $number = (int)round((float)$number);
        if ($number === 0)
            return 'صفر';
        if ($number < 0)
            return 'منفی ' . self::toWords(-$number);

        $parts = [];
        $scale = 0;
        while ($number > 0 && $scale < count(self::SCALES)) {
            $chunk = $number % 1000;
            if ($chunk > 0)
                array_unshift($parts, trim(self::chunk($chunk) . ' ' . self::SCALES[$scale]));
            $number = intdiv($number, 1000);
            $scale++;
        }

        return implode(' و ', $parts);
    }

    private static function chunk(int $n): string
    {
        $words = [];
        if ($n >= 100) {
            $words[] = self::HUNDREDS[intdiv($n, 100)];
            $n %= 100;
        }
        if ($n >= 20) {
            $words[] = self::TENS[intdiv($n, 10)];
            $n %= 10;
            if ($n > 0)
                $words[] = self::ONES[$n];
        } elseif ($n >= 10) {
            $words[] = self::TEENS[$n - 10];
        } elseif ($n > 0) {
            $words[] = self::ONES[$n];
        }

        return implode(' و ', $words);
    }

    public static function digits($value): string
    {
        return strtr((string)$value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    // ۱۲۳۴۵۶۷ → ۱,۲۳۴,۵۶۷
    public static function money($value): string
    {
        return self::digits(number_format((float)$value));
    }
}
