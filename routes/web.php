<?php

use App\Models\User;
use App\Models\SaleInvoices;
use App\Mail\ConfirmEmailMail;
use App\Models\Company;
use App\Models\Receipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {

    $c = Company::find(3);
    dd($c->invoice_comments);
    return 'funciona';
});
