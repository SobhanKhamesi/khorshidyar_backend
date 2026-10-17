<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\InstallationService;

class InstallationServiceController extends Controller
{
    public function print(InstallationService $installationService)
    {
        return view('print_install_service', compact('installationService'));
    }
}
