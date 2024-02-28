<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Src\Repositories\PriceListRepository;
use App\Transformers\PriceListTransformer;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    protected $priceListRepository;

    public function __construct(PriceListRepository $priceListRepository)
    {
        $this->priceListRepository = $priceListRepository;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $priceLists = $this->priceListRepository->find($request);

        $priceLists = fractal($priceLists, new PriceListTransformer())->toArray()['data'];

        return response()->json($priceLists, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $newPriceList = $this->priceListRepository->store($request);

        return response()->json($newPriceList, 201);
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
        $priceList = $this->priceListRepository->update($request);

        return response()->json($priceList, 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
