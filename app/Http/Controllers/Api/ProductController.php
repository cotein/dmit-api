<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Src\Repositories\ProductRepository;
use App\Transformers\ProductListTransformer;
use App\Transformers\ProductTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{

    protected $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->has('dashboard')) {
            $totalProducts = $this->productRepository->find($request);
            return response()->json($totalProducts, 200);
        }

        if ($request->has('list')) {
            $listProducts = $this->productRepository->find($request);

            $data = fractal($listProducts, new ProductListTransformer())->toArray()['data'];

            $pagination = [
                'total' => $listProducts->total(),
                'per_page' => $listProducts->perPage(),
                'current_page' => $listProducts->currentPage(),
                'last_page' => $listProducts->lastPage(),
                'from' => $listProducts->firstItem(),
                'to' => $listProducts->lastItem()
            ];

            $response = [
                'pagination' => $pagination,
                'data' => $data
            ];

            return response()->json($response, 200);
        }

        $products = $this->productRepository->find($request);

        $products = fractal($products, new ProductTransformer())->toArray()['data'];

        return response()->json($products, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product.name' => 'required',
        ]);

        $product = $this->productRepository->store($request);

        $product = fractal($product, new ProductTransformer())->toArray()['data'];

        return response()->json($product, 201);
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
        $product = $this->productRepository->update($request);

        $product = fractal($product, new ProductTransformer())->toArray()['data'];

        return response()->json($product, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    public function img(Request $request)
    {
        Log::alert('imggggggggggggggg');
        Log::alert($request->all());
        Log::alert('imggggggggggggggg');
    }
}
