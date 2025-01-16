<?php

namespace App\Src\Repositories;

use Exception;
use App\Models\AfipDocument;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerRepository
{
    public function find(Request $request)
    {
        $customers = Customer::query();

        $customers = $customers->where('company_id', (int) $request->company_id);

        if ($request->has('dashboard')) {
            return $customers->count();
        }

        if ($customers->count() === 0) {
            return null;
        }

        if ($request->has('name')) {
            $customers = $customers->where(function ($query) use ($request) {
                $query->whereRaw("CONCAT(name, ' ', last_name) LIKE ?", "%{$request->name}%");
            });
            /* $customers = $customers->where(function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->name}%")
                    ->orWhere('last_name', 'like', "%{$request->name}%")
                    ->orWhere('fantasy_name', 'like', "%{$request->name}%");
            }); */
        }

        if ($request->has('isActive')) {
            $isActive = $request->isActive;

            switch ($isActive) {
                case 'active':
                    $customers = $customers->where('active', true);
                    break;
                case 'inactive':
                    $customers = $customers->where('active', false);
                    break;
            }
        }

        $customers->orderByRaw("TRIM(CONCAT(COALESCE(name, ''), ' ', COALESCE(last_name, '')))");

        return $customers->paginate($request->itemsPerPage);
    }

    public function store(Request $request): Customer
    {

        $afipData = $request->customer['afip_data'];
        $afipNumber = (int) ($afipData['datosGenerales']['idPersona'] ?? $afipData['errorConstancia']['idPersona'] ?? 0);
        $companyId = (int) $request->customer['company_id'];

        $customer = Customer::firstOrNew([
            'afip_number' => $afipNumber,
            'company_id' => $companyId,
        ]);

        if ($customer->exists) {
            throw new Exception('Ya se encuentra registrado un cliente con la misma CUIT.');
        }

        $customer->fill([
            'name' => strtoupper($request->customer['name']),
            'last_name' => strtoupper($request->customer['lastName']),
            'fantasy_name' => strtoupper($request->customer['fantasy_name']),
            'afip_type' => $request->customer['type_customer'], //fisica ó juridica
            'afip_inscription_id' => $request->customer['inscription'],
            'afip_data' => (int) $request->customer['afip_data'],
            'afip_document_id' => AfipDocument::where('afip_code', $request->customer['cuit_id'])->firstOrFail()->id,
            'user_id' => auth('api')->user()->id,
        ]);

        $customer->save();
        $customer->refresh();

        $address = $request->customer['address'];

        if (!empty($address['city']) && !empty($address['street']) && !empty($address['state_id'])) {
            $customer->address()->create($address);
        }

        return $customer;
    }
}
