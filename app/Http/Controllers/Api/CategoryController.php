<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Src\Constantes;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Src\Repositories\CategoryRepository;
use App\Transformers\CategoryTransformer;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    protected $categoryRepository;

    function __construct(CategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $categories = $this->categoryRepository->find($request);

        return response()->json($categories, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {

            $category = $this->categoryRepository->store($request);

            return response()->json($category, 201);
        } catch (\Exception $e) {

            activity(Constantes::ERROR_AL_CREAR_CATEGORIA)
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
