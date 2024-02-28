<?php

namespace App\Src\Repositories;

use App\Models\AfipDocument;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class CustomerRepository
{
    public function find(Request $request): Collection
    {
        $customers = Customer::query();

        $customers = $customers->where('company_id', $request->company_id)
            ->where('active', true);

        if ($request->has('name')) {
            $customers = $customers->where('name', 'like', "%{$request->name}%")
                ->orWhere('last_name', 'like', "%{$request->name}%")
                ->orWhere('fantasy_name', 'like', "%{$request->name}%");
        }

        /* if ($request->has('isForSelect')) {
            return $customers->get()->map(function ($c) {
                return [
                    'label' => ($c->last_name) ? $c->name . ' ' . $c->last_name,
                    'value' => $c->id
                ];
            })->toArray();
        } */
        return $customers->get();
    }

    public function store(Request $request): Customer
    {

        $afip_number = 0;

        if (array_key_exists('datosGenerales', $request->customer['afip_data'])) {
            $afip_number = (int) $request->customer['afip_data']['datosGenerales']['idPersona'];
        }

        if (array_key_exists('errorConstancia', $request->customer['afip_data'])) {
            $afip_number = (int) $request->customer['afip_data']['errorConstancia']['idPersona'];
        }

        $customer = new Customer();
        $customer->name = strtoupper($request->customer['name']);
        $customer->last_name = strtoupper($request->customer['lastName']);
        $customer->fantasy_name = strtoupper($request->customer['fantasy_name']);
        $customer->afip_number = $afip_number;
        $customer->afip_type = $request->customer['type_customer']; //fisica ó juridica
        $customer->afip_inscription_id = $request->customer['inscription'];
        $customer->afip_data = (int) $request->customer['afip_data'];
        $customer->afip_document_id = AfipDocument::where('afip_code', $request->customer['cuit_id'])->get()->first()->id;
        $customer->company_id = (int) $request->customer['company_id'];
        $customer->user_id = auth('api')->user()->id;
        $customer->save();
        $customer->refresh();

        $address = $request->customer['address'];

        if ($address['city'] != '' && $address['street'] != '' && $address['state_id'] != '') {
            $customer->address()->create($address);
        }

        return $customer;
    }
}
