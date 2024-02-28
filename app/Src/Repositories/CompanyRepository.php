<?php

namespace App\Src\Repositories;

use App\Models\Company;
use App\Src\Constantes;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CompanyRepository
{

    public function find(Request $request)
    {
    }
    public function store(Request $request): Company
    {
        $company = new Company();
        $company->name = strtoupper($request->company['name']);
        $company->last_name = strtoupper($request->company['lastName']);
        $company->fantasy_name = strtoupper($request->company['fantasy_name']);
        $company->afip_number = $request->company['cuit'];
        $company->afip_type = $request->company['type_company'];
        $company->afip_inscription_id = $request->company['inscription'];
        $company->afip_data = json_encode($request->company['afip_data']);
        $company->afip_document_id = Constantes::CUIT_ID;
        $company->percep_iibb = $request->company['perception_iibb'];
        $company->iibb_conv = $request->company['iibb'];
        $company->percep_iva = $request->company['perception_iva'];
        $company->pto_vta_fe = (int) $request->company['pto_vta_fe'];
        $company->pto_vta_remito = (int) $request->company['pto_vta_remito'];
        $company->pto_vta_recibo = (int) $request->company['pto_vta_recibo'];
        $company->billing_concept = (int) $request->company['billing_concept'];
        $company->environment = $request->company['afip_environment'];
        $company->activity_init = Carbon::parse($request->company['activity_init'])->format('Y-m-d');

        $company->save();
        $company->refresh();

        $address = $request->company['address'];

        $company->address()->create($address);

        return $company;
    }

    public function update(Request $request): Company
    {
        $company = Company::find((int) $request->company_id);

        if ($request->has('pto_vta_fe')) {
            $company->pto_vta_fe = $request->pto_vta_fe;
            $company->save();
            $company->refresh();
        }

        return $company;
    }
}
