<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Src\Constantes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Transformers\CustomerTransformer;
use App\Src\Repositories\CustomerRepository;
use App\Src\Repositories\CustomerCuentaCorrienteRepository;
use App\Transformers\CustomerCuentaCorrienteTransformer;

class CustomerController extends Controller
{
    protected $customerRepository;

    protected $customerCuentaCorrienteRepository;

    public function __construct(CustomerRepository $customerRepository, CustomerCuentaCorrienteRepository $customerCuentaCorrienteRepository)
    {
        $this->customerRepository = $customerRepository;
        $this->customerCuentaCorrienteRepository = $customerCuentaCorrienteRepository;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        if ($request->has('is_customer_cuenta_corriente')) {
            $cc = $this->customerCuentaCorrienteRepository->find($request);
            $ccc = fractal($cc, new CustomerCuentaCorrienteTransformer())->toArray()['data'];
            return response()->json($ccc, 200);
        }

        $customers = $this->customerRepository->find($request);

        $data = fractal($customers, new CustomerTransformer())->toArray()['data'];

        $pagination = [
            'total' => $customers->total(),
            'per_page' => $customers->perPage(),
            'current_page' => $customers->currentPage(),
            'last_page' => $customers->lastPage(),
            'from' => $customers->firstItem(),
            'to' => $customers->lastItem()
        ];

        $response = [
            'pagination' => $pagination,
            'data' => $data
        ];

        return response()->json($response, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {

            $customer = $this->customerRepository->store($request);

            $customer = fractal($customer, new CustomerTransformer())->toArray()['data'];

            return response()->json($customer, 201);
        } catch (Exception $e) {

            activity(Constantes::ERROR_AL_CREAR_CLIENTE)
                ->causedBy(auth('api')->user())
                ->withProperties($request->all())
                ->log($e->getMessage());

            throw new Exception($e->getMessage(), 431);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
