<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Src\Constantes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Src\Repositories\CustomerRepository;
use App\Transformers\CustomerTransformer;

class CustomerController extends Controller
{
    protected $customerRepository;

    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $customers = $this->customerRepository->find($request);

        $customers = fractal($customers, new CustomerTransformer())->toArray()['data'];

        return response()->json($customers, 200);
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
        } catch (\Exception $e) {

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
