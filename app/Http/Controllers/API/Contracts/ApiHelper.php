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


    function sortArray(&$array, $subfield)
    {
        $sortArray = array();
        foreach ($array as $key => $row) {
            $sortArray[$key] = $row[$subfield];
        }

        array_multisort($sortArray, SORT_DESC, $array);
    }

}
