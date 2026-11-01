<?php

namespace App\Http\Controllers\API\Contracts;

use App\Http\Controllers\Controller;

class ApiHelper extends Controller
{

    public function getRealPrice($input)
    {
        $input = str_replace(",", "", $input);
        $input = str_replace(" تومان", "", $input);
        return str_replace(" ریال", "", $input);
    }

    public function getRealText($request, $input)
    {
        if ($request->has($input) && $request->input($input) != 'null' && $request->input($input) != 'undefined') {
            return $request->input($input);
        } else {
            return null;
        }
    }

    public function getRealTextRare($request, $input)
    {
        if (isset($request[$input]) && $request[$input] != 'null' && $request[$input] != 'undefined') {
            return $request[$input];
        } else {
            return null;
        }
    }

    public function getRealCount($request, $input)
    {
        if ($request->has($input) && $request->input($input) != 'null' && $request->input($input) != 'undefined' && $request->input($input) > 0) {
            return $request->input($input);
        } else {
            return 0;
        }
    }


    /**
     * پاک‌سازی و اعتبارسنجی درصد معرف ارسالی.
     * فرانت در FormData مقادیر خالی را به‌صورت رشته‌های "null"/"undefined" می‌فرستد؛ این‌ها null در نظر گرفته می‌شوند.
     * خروجی: عدد صحیح ۰ تا ۱۰۰ یا null (یعنی مقداری ارسال نشده است).
     */
    public function referrerPercent($request): ?int
    {
        $value = $this->getRealText($request, 'referrer_percent');
        $value = is_string($value) ? trim($value) : $value;
        $request->merge(['referrer_percent' => ($value === '' ? null : $value)]);

        $request->validate([
            'referrer_percent' => 'nullable|integer|between:0,100',
        ], [
            'referrer_percent.integer' => 'درصد معرف باید عدد صحیح باشد',
            'referrer_percent.between' => 'درصد معرف باید بین ۰ تا ۱۰۰ باشد',
        ]);

        return $request->input('referrer_percent') === null ? null : (int)$request->input('referrer_percent');
    }

    function sortArray(&$array, $subfield)
    {
        $sortArray = array();
        foreach ($array as $key => $row) {
            $sortArray[$key] = $row[$subfield];
        }

        array_multisort($sortArray, SORT_DESC, $array);
    }

}
