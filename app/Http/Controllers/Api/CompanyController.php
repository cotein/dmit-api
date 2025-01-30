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
use Illuminate\Support\Facades\Storage;

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

            $company->users()->sync(auth()->user()->id);

            $companies = $this->setMyCompanies(auth()->user());

            Storage::disk('public')->makeDirectory('companies/' . $company->afip_number);

            return response()->json($companies, 201);
        } catch (\Exception $e) {

            activity(Constantes::ERROR_AL_CREAR_COMPAÑIA)
                ->causedBy(auth()->user())
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
        //try {

        $company = $this->companyRepository->update($request, $id);

        $companies = $this->setMyCompanies(auth()->user());

        return response()->json($companies, 200);
        /* } catch (\Exception $e) {

            activity(Constantes::ERROR_AL_CREAR_COMPAÑIA)
                ->causedBy(auth('api')->user())
                ->withProperties($request->all())
                ->log($e->getMessage());

            throw new Exception($e->getMessage(), $e->getCode());
        } */
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
        $company = Company::find($request->company);

        if (!$company->getMedia('logos')->isEmpty()) {
            $company->clearMediaCollection('logos');
        }

        if ($request->hasFile('file_0')) {
            $fileName = 'logo'; // El nombre del archivo será algo como "logo.png"
            // Elimina el logo existente si hay alguno
            $company->clearMediaCollection('logos');

            // Agrega el nuevo archivo al modelo, especificando el directorio y el nombre del archivo
            $company->addMediaFromRequest('file_0')
                ->usingFileName($fileName . '.' . $request->file_0->getClientOriginalExtension()) // Asegura que la extensión del archivo se mantenga
                ->toMediaCollection('logos');

            $company->refresh();
        }

        $companies = $this->setMyCompanies(auth()->user());

        return response()->json($companies, 201);
    }
}
