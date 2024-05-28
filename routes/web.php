<?php

use Carbon\Carbon;
use App\Models\Company;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Customer;
use App\Models\AfipVoucher;
use App\Models\SaleInvoices;
use Cotein\ApiAfip\Afip\WS\WSFEV1;
use Cotein\ApiAfip\Models\AfipToken;
use Illuminate\Support\Facades\Route;
use App\Models\CustomerCuentaCorriente;
use App\Models\User;
use App\Src\Helpers\Afip;
use Cotein\ApiAfip\AfipWebService as ApiAfipAfipWebService;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Facades\LogBatch;
use Cotein\ApiAfip\AfipWebService;

Route::get('/', function () {
    return 'funciona';
});
