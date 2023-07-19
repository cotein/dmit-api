<?php

use App\Models\User;
use App\Models\Company;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

Route::middleware(['cors'])->group(function () {
    Route::get('/', function () {
        Permission::create(['name' => 'FACTURAR']);
        Permission::create(['name' => 'CREAR COMPAÑIA']);
        Permission::create(['name' => 'ELIMINAR FACTURA']);
        Permission::create(['name' => 'CREAR USUARIO']);
        Role::create(['name' => 'USUARIO']);

        /* $invoiceFactoryClass = InvoiceFactory::createInvoice(1, 1, 1);
        $invoiceFactory = new $invoiceFactoryClass;
        $items = [];
        dd($invoiceFactory->createInvoice()->processItems()); */
        //ésto devuelve un array que luego se lo paso al WSFEV1
        return view('welcome');
    });
});
