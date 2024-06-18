<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Company;
use App\Src\Constantes;
use Illuminate\Http\Request;
use App\Src\Traits\CompanyTrait;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Src\Repositories\CompanyRepository;

class CompanyController extends Controller
{
    use CompanyTrait;

    private $companyRepository;

    function __construct(CompanyRepository $companyRepository)
    {
        $this->companyRepository = $companyRepository;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {

            $company = $this->companyRepository->store($request);

            $company->users()->sync(auth('api')->user()->id);

            $companies = $this->setMyCompanies(auth()->user());

            return response()->json($companies, 201);
        } catch (\Exception $e) {

            activity(Constantes::ERROR_AL_CREAR_COMPAÑIA)
                ->causedBy(auth('api')->user())
                ->withProperties($request->all())
                ->log($e->getMessage());

            throw new Exception($e->getMessage(), $e->getCode());
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
        try {

            $company = $this->companyRepository->update($request, $id);

            $companies = $this->setMyCompanies(auth()->user());

            return response()->json($companies, 200);
        } catch (\Exception $e) {

            activity(Constantes::ERROR_AL_CREAR_COMPAÑIA)
                ->causedBy(auth('api')->user())
                ->withProperties($request->all())
                ->log($e->getMessage());

            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function logo(Request $request)
    {
        Log::alert('logo');
        Log::alert($request->company);
        Log::alert('logo');
        $c = json_decode($request->input('company'), true);
        $company = Company::find($c['id']);

        if ($request->hasFile('logo')) {
            // Agrega el archivo al modelo
            $company->addMediaFromRequest('logo')->toMediaCollection('logos');
        }

        return response()->json(['message' => 'Logo uploaded successfully'], 200);
    }
}
