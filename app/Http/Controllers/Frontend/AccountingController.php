<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Automation;

class AccountingController extends Controller
{
    public function print(Automation $accounting)
    {
        return view('print', compact('accounting'));
    }
}
