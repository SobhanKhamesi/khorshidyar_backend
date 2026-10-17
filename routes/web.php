<?php

use App\Http\Controllers\Frontend\InstallationServiceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\AccountingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('print/{accounting}', [AccountingController::class, 'print']);
Route::get('print_install_service/{installation_service}', [InstallationServiceController::class, 'print']);
