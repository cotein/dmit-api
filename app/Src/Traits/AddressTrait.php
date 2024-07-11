<?php

namespace App\Src\Traits;

use App\Models\AfipState;
use App\Models\Company;
use App\Models\Customer;
use App\Models\SaleInvoices;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

trait AddressTrait
{
    /**
     * Returns the address of the model if it has an address relationship.
     *
     * @param Model $data Model with a potential relationship to Address.
     * @return array|null Address information or null if not available.
     */
    function address(Model $data): ?array
    {
        $addressRelation = $this->getAddressRelation($data);

        if ($addressRelation && $addressRelation->exists()) {
            $address = $addressRelation->first();

            return $this->formatAddress($address);
        }

        return null;
    }

    /**
     * Get the address relationship from the model if it exists.
     *
     * @param Model $data Model instance.
     * @return Relation|null Address relationship or null.
     */
    private function getAddressRelation(Model $data): ?Relation
    {

        if ($data instanceof Customer || $data instanceof Company) {
            if ($data->address()->exists()) {
                return $data->address();
            }

            return null;
        }

        return null;
    }

    /**
     * Format the address details into an array.
     *
     * @param Model $address Address model instance.
     * @return array Formatted address information.
     */
    private function formatAddress(Model $address): array
    {
        return [
            'city' => $address->city,
            'street' => $address->street,
            'cp' => $address->cp,
            'state' => AfipState::where('id', $address->state_id)->first()->name,
        ];
    }
}
