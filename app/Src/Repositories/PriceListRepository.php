<?php

namespace App\Src\Repositories;

use App\Models\PriceList;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class PriceListRepository
{

    public function find(Request $request): Collection
    {
        $priceLists = PriceList::query();

        $priceLists = $priceLists->where('company_id', $request->company_id);

        return $priceLists->get();
    }

    public function store(Request $request): PriceList
    {
        try {
            $priceList = new PriceList();
            $priceList->name = strtoupper($request->newPriceList);
            $priceList->profit_percentage = strtoupper($request->profit_percentage);
            $priceList->company_id = $request->company_id;
            $priceList->user_id = auth()->user()->id;
            $priceList->active = true;
            $priceList->save();

            return $priceList;
        } catch (QueryException $e) {
            $errorCode = $e->errorInfo[1];
            if ($errorCode == 1062) {
                throw new Exception('La lista de precios que intenta ingresar ya se encuentra registrada');
            }
        }
    }

    public function update(Request $request): PriceList
    {
        $updatedData = $request->priceList;

        $priceList = PriceList::find($updatedData['id']);
        $priceList->name = strtoupper($updatedData['name']);
        $priceList->profit_percentage = $updatedData['profit_percentage'];
        $priceList->active = $updatedData['active'];
        $priceList->save();

        return $priceList;
    }
}
