<?php

namespace App\Src\Traits;

use Carbon\Carbon;
use App\Models\SaleInvoices;
use App\Models\Receipt;

trait PaymentHistoryTrait
{
    private function paymentHistory($model): array
    {
        if ($model instanceof SaleInvoices) {
            return $this->mapReceipts($model->receipts);
        } elseif ($model instanceof Receipt) {
            return $this->mapSaleInvoices($model->saleInvoices);
        }

        return [];
    }

    private function mapReceipts($receipts): array
    {
        return $receipts->map(function ($receipt) {
            return $this->mapPivotData($receipt, $receipt->pivot);
        })->toArray();
    }

    private function mapSaleInvoices($saleInvoices): array
    {
        return $saleInvoices->map(function ($si) {
            return $this->mapPivotData($si, $si->pivot);
        })->toArray();
    }

    private function mapPivotData($model, $pivot): array
    {
        return [
            'id' => $model->id,
            'number' => $model->number, //numero de factura o recibo
            'saldo' => $model->saldo,
            //'pto_vta' => ($model instanceof SaleInvoices) ? $model->company->pto_vta_fe : $model->company->pto_vta_recibo,
            'percentage_payment' => $pivot->percentage_payment,
            'import_payment' => $pivot->import_payment,
            'percentage_paid_history' => $pivot->percentage_paid_history,
            'import_paid_history' => $pivot->import_paid_history,
            'created_at' => Carbon::parse($pivot->created_at)->format('d-m-Y'),
        ];
    }
}
