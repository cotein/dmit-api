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
use App\Src\Traits\AddressTrait;
use Cotein\ApiAfip\AfipWebService as ApiAfipAfipWebService;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Facades\LogBatch;
use Cotein\ApiAfip\AfipWebService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

Route::get('/', function () {
    $c = SaleInvoices::find(1);
    dd($c->company instanceof Company);
    return 'funciona';
});
