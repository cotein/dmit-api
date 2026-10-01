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

    private function setCompanyData($user): array
    {
        $company = $user->companies()->first();

        return [
            'id' => $company ? $company->id : null,
            'cuit' => $company ? $company->afip_number : null,
            'inscription' => $company ? $company->afip_inscription_id : null,
            'document' => $company ? $company->afip_document_id : null,
            'environment' => $company ? $company->environment : null,
            'ptoVtaFe' => $company ? $company->pto_vta_fe : null
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
            'name' => strtoupper($user->name),
            'email' => $user->email,
            'isActive' => $user->isActive(),
            'user_level' => optional($user->userType)->level,
            'companies' => $user->listMyCompanies(),
            'avatar' => ($user->getMedia('avatar')->first()) ? $user->getMedia('avatar')->first()->getFullUrl() : '/src/assets/img/avatar/chat-auth.png'
        ];
    }
}
