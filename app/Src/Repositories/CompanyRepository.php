<?php

namespace App\Src\Repositories;

use Carbon\Carbon;
use App\Models\Cbu;
use App\Models\Company;
use App\Src\Constantes;
use Illuminate\Http\Request;
use App\Src\Helpers\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyRepository
{
    private function saveCbu($data, Company $company): void
    {
        $cbu = new Cbu();
        $cbu->company_id = $company->id;
        $cbu->bank_id = $data['bank_id'];
        $cbu->cbu = $data['cbu'];
        $cbu->alias = $data['alias'];
        $cbu->cta_cte = $data['ctaCte'];
        $cbu->save();
        $cbu->refresh();
    }

    private function saveCompany(array $data, Company $company = null): Company
    {

        DB::beginTransaction();

        try {
            if (!$company) {
                $company = new Company();
            }

            // Check if afip_number is unique
            $existingCompany = Company::where('afip_number', $data['cuit'])->first();

            if ($existingCompany && (!$company || $existingCompany->id !== $company->id)) {
                throw new \Exception("La compañía con CUIT {$data['cuit']} ya existe", 400);
            }

            $company->name = strtoupper($data['name']);
            $company->last_name = strtoupper($data['lastName']);
            $company->fantasy_name = strtoupper($data['fantasy_name']);
            $company->afip_number = $data['cuit'];
            $company->afip_type = $data['type_company'];

            if (is_int($data['inscription'])) {

                $company->afip_inscription_id = $data['inscription'];
            }

            if (isset($data['afip_data']) && !is_null($data['afip_data'])) {
                $company->afip_data =  json_encode($data['afip_data']);
            }

            $company->afip_document_id = Constantes::CUIT_ID;

            /* if (isset($data['perception_iibb']) && !is_null($data['perception_iibb'])) {
                $company->percep_iibb = $data['perception_iibb'];
            } */
            if (array_key_exists('perception_iibb', $data)) {
                //throw new \Exception("Error Processing Request " . $data['perception_iibb'], $data['perception_iibb']);

                $company->percep_iibb = $data['perception_iibb'];
            }
            if (array_key_exists('perception_iva', $data)) {
                //throw new \Exception("Error Processing Request " . $data['perception_iva'], $data['perception_iva']);

                $company->percep_iva = $data['perception_iva'];
            }
            /* if (isset($data['perception_iva']) && !is_null($data['perception_iva'])) {
                $company->percep_iibb = $data['perception_iva'];
            } */

            $company->iibb_conv = $data['iibb'];
            //$company->percep_iva = $data['perception_iva'];
            $company->pto_vta_fe = (int) $data['pto_vta_fe'];
            $company->pto_vta_remito = (int) $data['pto_vta_remito'];
            $company->pto_vta_recibo = (int) $data['pto_vta_recibo'];
            $company->billing_concept = (int) $data['billing_concept'];
            $company->environment = $data['afip_environment'];
            $company->activity_init = Carbon::parse($data['activity_init'])->format('Y-m-d');

            if (!empty($data['phone1'])) {
                $company->phone1 = $data['phone1'];
            }

            if (!empty($data['phone2'])) {
                $company->phone2 = $data['phone2'];
            }

            if (!empty($data['email'])) {
                $company->email = $data['email'];
            }

            if (!empty($data['webSite'])) {
                $company->web_site = $data['webSite'];
            }

            $company->save();
            $company->refresh();

            if (isset($data['address'])) {
                if ($data['address']['addressable_id'] && $data['address']['addressable_type'] && $data['address']['addressable_id'] != '' && $data['address']['addressable_type'] != '') {
                    $company->address()->update($data['address']);
                } else {

                    $company->address()->create($data['address']);
                }
            }

            $cbus = collect($data['cbus']);

            /* if ($cbus->isNotEmpty()) {

                $cbus->each(function ($cbuData) use ($company) {
                    // Datos para la búsqueda o creación del registro
                    $searchData = [
                        'cbu' => $cbuData['cbu'],
                        'company_id' => $company->id
                    ];

                    // Datos para la actualización o creación del registro
                    $updateData = [
                        'bank_id' => $cbuData['bank_id'],
                        'alias' => strtoupper($cbuData['alias']),
                        'cta_cte' => strtoupper($cbuData['ctaCte'])
                    ];

                    // Actualiza o crea el registro en la base de datos
                    $cbu = Cbu::updateOrCreate($searchData, $updateData);
                });
            } */
            if ($cbus->isNotEmpty()) {
                // Obtener las cuentas corrientes actuales de la empresa
                $existingCbus = Cbu::where('company_id', $company->id)->get();

                // Crear una lista de las cuentas corrientes enviadas desde el frontend
                $sentCbus = $cbus->pluck('cbu')->toArray();

                // Actualizar o crear las cuentas corrientes enviadas
                $cbus->each(function ($cbuData) use ($company) {
                    // Datos para la búsqueda o creación del registro
                    $searchData = [
                        'cbu' => $cbuData['cbu'],
                        'company_id' => $company->id
                    ];

                    // Datos para la actualización o creación del registro
                    $updateData = [
                        'bank_id' => $cbuData['bank_id'],
                        'alias' => strtoupper($cbuData['alias']),
                        'cta_cte' => strtoupper($cbuData['ctaCte'])
                    ];

                    // Actualiza o crea el registro en la base de datos
                    $cbu = Cbu::updateOrCreate($searchData, $updateData);
                });

                // Eliminar las cuentas corrientes que no se enviaron
                $existingCbus->each(function ($existingCbu) use ($sentCbus) {
                    if (!in_array($existingCbu->cbu, $sentCbus)) {
                        $existingCbu->delete();
                    }
                });
            }
            DB::commit();

            // Recargar la compañía para asegurarse de que todos los datos estén actualizados
            $company->refresh();

            return $company;
        } catch (\Exception $e) {
            DB::rollBack();


            throw $e;
        }
    }

    public function saveLogo(Request $request) {}
    public function find(Request $request) {}

    public function store(Request $request): Company
    {
        return $this->saveCompany($request->company);
    }

    public function update(Request $request, $id): Company
    {
        $company = Company::find((int) $id);

        if (!$company) {
            throw new \Exception('La compañia no existe');
        }

        if ($request->has('pto_vta_fe')) {

            $company->pto_vta_fe = $request->pto_vta_fe;
            $company->save();
            $company->refresh();
        } else {

            $company = $this->saveCompany($request->company, $company);
        }

        return $company;
    }
}
