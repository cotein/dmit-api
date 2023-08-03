<?php

namespace App\Transformers;

use App\Models\User;
use League\Fractal\TransformerAbstract;

class UserTransformer extends TransformerAbstract
{
    /**
     * List of resources to automatically include
     *
     * @var array
     */
    protected array $defaultIncludes = [
        //
    ];

    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected array $availableIncludes = [
        //
    ];

    private function userHasCompany($user): bool
    {

        return ($user->company)
            ? true
            : false;
    }

    private function setCompanyData($user): array
    {

        return [
            'id' => $user->company->id,
            'cuit' => $user->company->afip_number,
            'inscription' => $user->company->afip_inscription_id,
            'document' => $user->company->afip_document_id,
            'environment' => $user->company->environment,
            'ptoVtaFe' => $user->company->pto_vta_fe
        ];
    }
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(User $user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'company' => ($this->userHasCompany($user)) ? $this->setCompanyData($user) : false,
        ];
    }
}
