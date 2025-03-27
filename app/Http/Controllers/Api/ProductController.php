<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Transformers\ProductTransformer;
use App\Src\Repositories\ProductRepository;
use App\Transformers\ProductListTransformer;

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

    public function store(Request $request): JsonResponse
    {

        try {
            $product = $this->productRepository->store($request);

            $transformedProduct = fractal($product, new ProductTransformer())->toArray();


            return response()->json($transformedProduct['data'], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->validator->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
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


}
