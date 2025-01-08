<?php

namespace App\Src\Traits;

use App\Src\Traits\ImageBase64Trait;

trait CompanyTrait
{
    use ImageBase64Trait;

    private function localidad($company): string
    {
        // Obtener el campo afip_data de la tabla companies
        if (!$company) {
            return "Company not found.";
        }

        $afipData = json_decode($company->afip_data, true);

        // Verificar si el JSON fue decodificado correctamente
        if (json_last_error() === JSON_ERROR_NONE) {
            // Acceder a la propiedad localidad
            $localidad = $afipData['datosGenerales']['domicilioFiscal']['localidad'] ?? null;

            if ($localidad) {
                return $localidad;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    private function address($company): array
    {
        return [
            'state_id' => $company->address->state_id ?? null,
            'city' => $company->address->city ?? '',
            'street' => $company->address->street ?? '',
            'cp' => $company->address->cp ?? '',
            'number' => $company->address->number ?? '',
            'obs' =>  $company->address->obs ?? '',
            'between_streets' => $company->address->between_streets ?? '',
            'addressable_id' => $company->address->addressable_id ?? '',
            'addressable_type' => $company->address->addressable_type ?? '',
            'localidad' => $this->localidad($company),
        ];
    }

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

    private function setCbus($company): array
    {
        if ($company->cbus && $company->cbus->every(function ($cbu) {
            return isset($cbu->id, $cbu->bank->id, $cbu->cbu);
        })) {
            return $company->cbus->transform(function ($cbu, int $key) {
                return [
                    'id' => $cbu->id,
                    'alias' => $cbu->alias,
                    'bank_id' => $cbu->bank->id,
                    'name' => $cbu->bank->name,
                    'cbu' => $cbu->cbu,
                    'ctaCte' => $cbu->cta_cte,
                ];
            })->toArray();
        } else {
            return [];
        }
    }

    public function setMyCompanies($user): array
    {

        return $user->companies->transform(function ($company) {

            $logo = $company->getMedia('logos')->first();

            if ($logo) {
                $logo_base64 = $this->convertImageToBase64($logo);
            } else {
                $logo_base64 = null;
            }

            return [
                'activity_init' => $company->activity_init,
                'address' => $this->address($company),
                'afip_environment' => $company->environment,
                'billing_concept' => $company->billing_concept,
                'created_at' => $company->created_at,
                'cuit' => $company->afip_number,
                'document' => $company->afipDocument->name,
                'fantasy_name' => ($company->fantasy_name) ? strtoupper($company->fantasy_name) : '',
                'id' => $company->id,
                'iibb' => $company->iibb_conv,
                'inscription_id' => $company->afipInscription->id,
                'inscription' => strtoupper($company->afipInscription->name),
                'lastName' => strtoupper($company->last_name),
                'logo_base64' => $logo_base64,
                'name' => strtoupper($company->name),
                'perception_iibb' => $company->percep_iibb,
                'perception_iva' => $company->percep_iva,
                'pto_vta_fe' => $company->pto_vta_fe,
                'pto_vta_recibo' => $company->pto_vta_recibo,
                'pto_vta_remito' => $company->pto_vta_remito,
                'type_company' => $company->afip_type,
                'user_id' => auth()->user()->id,
                'vouchers' => $this->vouchers($company),
                'cbus' => $this->setCbus($company),
                'webSite' => $company->web_site,
                'phone1' => $company->phone1,
                'phone2' => $company->phone2,
                'email' => $company->email,
            ];
        })->toArray();
    }
}
