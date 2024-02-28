<?php

namespace App\Http\Controllers\Api;

use App\Src\Constantes;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Src\Repositories\CompanyRepository;
use App\Src\Traits\CompanyTrait;
use Exception;

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
    public function update(Request $request)
    {
        $company = $this->companyRepository->update($request);

        return response()->json($company, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
