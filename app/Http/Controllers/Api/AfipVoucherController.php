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
        [
            'company' => Constantes::INSCRIPCION_RESPONSABLE_MONOTRIBUTO,
            'customer' => Constantes::INSCRIPCION_IVA_EXENTO,
            'vouchers' => Constantes::INSCRIPCION_RESPONSABLE_MONOTRIBUTO
        ],
    ];

    /**
     * Maneja la solicitud GET para obtener una lista de vouchers.
     *
     * Esta función maneja una solicitud GET a la ruta /api/vouchers. Si se proporcionan
     * 'company_inscription_id' y 'customer_inscription_id' en la solicitud, se filtran los vouchers
     * correspondientes. Si no se encuentra un voucher correspondiente, se devuelve un error 404.
     *
     * @param  \Illuminate\Http\Request  $request  La solicitud HTTP.
     * @return \Illuminate\Http\Response Una respuesta JSON que contiene los vouchers solicitados.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Si no se encuentra un voucher correspondiente.
     */
    public function index(Request $request)
    {

        $vouchers = AfipVoucher::query();

        if ($request->has('company_inscription_id') && $request->has('customer_inscription_id')) {
            $companyInscriptionId = (int) $request->company_inscription_id;
            $customerInscriptionId = (int) $request->customer_inscription_id;

            $voucher = collect(self::GET_VOUCHERS)->firstWhere(function ($item) use ($companyInscriptionId, $customerInscriptionId) {
                return $item['company'] === $companyInscriptionId && $item['customer'] === $customerInscriptionId;
            });

            if ($voucher) {
                $vouchers = $vouchers->where('inscription_id', $voucher['vouchers']);
            } else {
                Log::info('No matching voucher found');
                return response()->json(['error' => 'No matching voucher found'], 404);
            }
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
