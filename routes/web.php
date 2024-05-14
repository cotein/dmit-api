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

    $c = SaleInvoices::select('company_id', 'voucher_id')
        ->selectRaw('SUM(sale_invoice_items.total) as total_sum')
        ->groupBy('company_id', 'voucher_id')
        ->get();

    dd($c);
});
