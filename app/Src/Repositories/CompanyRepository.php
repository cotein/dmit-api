<?php

namespace App\Src\Repositories;

use Carbon\Carbon;
use App\Models\Company;
use App\Src\Constantes;
use App\Src\Helpers\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyRepository
{

    private function saveCompany(array $data, Company $company = null): Company
    {
        /* DB::beginTransaction();

        try { */
        if (!$company) {
            $company = new Company();
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

        if (isset($data['perception_iibb']) && !is_null($data['perception_iibb'])) {
            $company->percep_iibb = $data['perception_iibb'];
        }

        if (isset($data['perception_iva']) && !is_null($data['perception_iva'])) {
            $company->percep_iibb = $data['perception_iva'];
        }

        $company->iibb_conv = $data['iibb'];
        $company->percep_iva = $data['perception_iva'];
        $company->pto_vta_fe = (int) $data['pto_vta_fe'];
        $company->pto_vta_remito = (int) $data['pto_vta_remito'];
        $company->pto_vta_recibo = (int) $data['pto_vta_recibo'];
        $company->billing_concept = (int) $data['billing_concept'];
        $company->environment = $data['afip_environment'];
        $company->activity_init = Carbon::parse($data['activity_init'])->format('Y-m-d');

        $company->save();
        $company->refresh();

        if (isset($data['address'])) {
            if ($data['address']['addressable_id'] && $data['address']['addressable_type'] && $data['address']['addressable_id'] != '' && $data['address']['addressable_type'] != '') {
                $company->address()->update($data['address']);
            } else {

                $company->address()->create($data['address']);
            }
        }

        DB::commit();

        return $company;
        /* } catch (\Exception $e) {
            DB::rollBack();


            throw $e;
        } */
    }

    public function find(Request $request)
    {
    }

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
