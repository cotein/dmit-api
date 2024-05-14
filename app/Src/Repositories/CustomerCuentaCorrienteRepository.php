<?php

namespace App\Src\Repositories;

use App\Models\Receipt;
use App\Models\SaleInvoices;
use App\Models\CustomerCuentaCorriente;

use App\Src\Constantes;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerCuentaCorrienteRepository
{
    private function applyOptionalFilters($query, Request $request): void
    {
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('from') && $request->has('to')) {

            $startDate = Carbon::createFromFormat('Y-m-d', $request->from);

            $endDate = Carbon::createFromFormat('Y-m-d', $request->to);

            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $query->join('customers', 'customer_cuenta_corrientes.customer_id', '=', 'customers.id');



        // Ordenar por el campo "name" en la tabla "customers"
        $query->orderBy('customers.name', 'asc');
    }

    public function find(Request $request)
    {
        $query = CustomerCuentaCorriente::query();

        $query->where('customer_cuenta_corrientes.company_id', $request->company_id);
        // Filtrar por compañía
        //$query->where('company_id', $request->company_id);

        // Aplicar filtros opcionales
        $this->applyOptionalFilters($query, $request);

        // Ordenar resultados
        $query->orderBy('number', 'asc');

        /* $query->select('customer_id', 'company_id', 'number',  DB::raw('SUM(sale) as total_sale'), DB::raw('SUM(pay) as total_pay'))
            ->groupBy('customer_id', 'company_id'); */

        return $query->get();
        /* foreach ($customerCuentaCorriente as $cuentaCorriente) {
            echo "Customer ID: " . $cuentaCorriente->customer_id . "\n";
            echo "Total Sale: " . $cuentaCorriente->total_sale . "\n";
            echo "Total Pay: " . $cuentaCorriente->total_pay . "\n";
            echo "\n";
        } */
    }

    public function store($model): void
    {
        $IS_NOTA_CREDITO = collect(Constantes::IS_NOTA_CREDITO);

        $cuentaCorriente = new CustomerCuentaCorriente();
        $cuentaCorriente->customer_id = $model->customer_id;
        $cuentaCorriente->company_id = $model->company_id;
        $cuentaCorriente->cuotaable_type = get_class($model);
        $cuentaCorriente->cuotaable_id = $model->id;

        if ($model instanceof SaleInvoices) {
            if ($IS_NOTA_CREDITO->contains($model->voucher_id)) {
                $cuentaCorriente->sale = $model->items->sum('total') * -1;
            } else {
                $cuentaCorriente->sale = $model->items->sum('total');
            }
        } elseif ($model instanceof Receipt) {
            $cuentaCorriente->pay = $model->total;
        }

        $cuentaCorriente->save();
    }
}
