<?php

namespace App\Src\Repositories;

use Exception;
use App\Models\Customer;
use App\Models\AfipDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $searchTerm = strtoupper($request->name);

        if ($request->has('name')) {

            $customers = $customers->where(function ($query) use ($searchTerm) {
                $query->where('afip_number', 'like', "%{$searchTerm}%")
                ->orWhereRaw("CONCAT_WS(' ', name, last_name) LIKE ?", ["%{$searchTerm}%"]);
            });
        }

        if ($request->has('isActive')) {
            $isActive = $request->isActive;

            switch ($isActive) {
                case 'all':
                    $customers = $customers->where('active', true);
                    break;
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
