<?php

namespace App\Http\Controllers\Api;

use App\Src\Constantes;
use App\Models\AfipVoucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Transformers\AfipVoucherTransformer;

class AfipVoucherController extends Controller
{
    private $company_inscription_id;

    const GET_VOUCHERS = [
        ///// RESPONSABLE INSCRIPTO /////
        [
            'company' => Constantes::INSCRIPCION_RESPONSABLE_INSCRIPTO,
            'customer' => Constantes::INSCRIPCION_RESPONSABLE_INSCRIPTO,
            'vouchers' => Constantes::INSCRIPCION_RESPONSABLE_INSCRIPTO
        ],
        [
            'company' => Constantes::INSCRIPCION_RESPONSABLE_INSCRIPTO,
            'customer' => Constantes::INSCRIPCION_CONSUMIDOR_FINAL,
            'vouchers' => Constantes::INSCRIPCION_CONSUMIDOR_FINAL
        ],
        [
            'company' => Constantes::INSCRIPCION_RESPONSABLE_INSCRIPTO,
            'customer' => Constantes::INSCRIPCION_RESPONSABLE_MONOTRIBUTO,
            'vouchers' => Constantes::INSCRIPCION_CONSUMIDOR_FINAL
        ],
        ///// RESPONSABLE MONOTRIBUTO /////
        [
            'company' => Constantes::INSCRIPCION_RESPONSABLE_MONOTRIBUTO,
            'customer' => Constantes::INSCRIPCION_RESPONSABLE_INSCRIPTO,
            'vouchers' => Constantes::INSCRIPCION_RESPONSABLE_MONOTRIBUTO
        ],
        [
            'company' => Constantes::INSCRIPCION_RESPONSABLE_MONOTRIBUTO,
            'customer' => Constantes::INSCRIPCION_CONSUMIDOR_FINAL,
            'vouchers' => Constantes::INSCRIPCION_RESPONSABLE_MONOTRIBUTO
        ],
        [
            'company' => Constantes::INSCRIPCION_RESPONSABLE_MONOTRIBUTO,
            'customer' => Constantes::INSCRIPCION_RESPONSABLE_MONOTRIBUTO,
            'vouchers' => Constantes::INSCRIPCION_RESPONSABLE_MONOTRIBUTO
        ],
    ];
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $vouchers = AfipVoucher::query();

        if ($request->has('company_inscription_id') && $request->has('customer_inscription_id')) {

            collect(self::GET_VOUCHERS)->map(function ($item) use ($request) {
                if ((int) $request->company_inscription_id === $item['company'] && (int) $request->customer_inscription_id === $item['customer']) {
                    $this->company_inscription_id = (int) $item['vouchers'];
                }
            });

            $vouchers = $vouchers->where('inscription_id', $this->company_inscription_id);
        }

        $vouchers = fractal($vouchers->get(), new AfipVoucherTransformer())->toArray()['data'];

        return response()->json($vouchers, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
