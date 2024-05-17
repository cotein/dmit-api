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
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Facades\LogBatch;

Route::get('/', function () {
    /* $p = new WSFEV1('testing', 20227339730, 1, 1);
    dd($p->FEParamGetTiposIva()); */
    $path = '/home/coto/Github/AfipCertificates/DIMA_TESTING.crt';
    $c = file_get_contents($path);
    dd($c);
});
