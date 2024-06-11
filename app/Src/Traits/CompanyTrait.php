<?php

namespace App\Src\Traits;

trait CompanyTrait
{
    public function vouchers($company): array
    {
        $vouchers = $company->afip_vouchers->transform(function ($voucher, int $key) {
            return [
                'id' => $voucher->id,
                'value' => $voucher->id,
                'afip_code' => $voucher->afip_code,
                'name' => $voucher->name,
            ];
        });

        return $vouchers->toArray();
    }

    public function setMyCompanies($user): array
    {

        return $user->companies->transform(function ($company) {
            return [
                'activity_init' => $company->activity_init,
                'address' => $company->address,
                'billing_concept' => $company->billing_concept,
                'created_at' => $company->created_at,
                'cuit' => $company->afip_number,
                'document' => $company->afipDocument->name,
                'afip_environment' => $company->environment,
                'fantasy_name' => ($company->fantasy_name) ? strtoupper($company->fantasy_name) : '',
                'id' => $company->id,
                'iibb' => $company->iibb_conv,
                'inscription_id' => $company->afipInscription->id,
                'inscription' => strtoupper($company->afipInscription->name),
                'lastName' => strtoupper($company->last_name),
                'name' => strtoupper($company->name),
                'perception_iibb' => $company->percep_iibb,
                'perception_iva' => $company->percep_iva,
                'pto_vta_fe' => $company->pto_vta_fe,
                'pto_vta_recibo' => $company->pto_vta_recibo,
                'pto_vta_remito' => $company->pto_vta_remito,
                'type_company' => $company->afip_type,
                'user_id' => auth()->user()->id,
                'vouchers' => $this->vouchers($company)
            ];
        })->toArray();
    }
}
