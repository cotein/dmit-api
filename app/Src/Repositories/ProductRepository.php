<?php

namespace App\Src\Repositories;

use App\Models\AfipIva;
use Exception;
use App\Models\Product;
use App\Src\Constantes;
use App\Models\PriceList;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\CategoryProduct;
use App\Models\PriceListProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductRepository
{

    public function find(Request $request)
    {
        $products = Product::query();

        $products = $products->where('company_id', $request->company_id);

        if ($request->has('dashboard')) {
            return $products->count();
        }

        if ($request->has('list')) {
            return $products->paginate($request->per_page);
        }

        if ($request->has('name')) {
            $products = $products->where('name', 'like', "%{$request->name}%");
        }

        return $products->get();
    }

    /**
     * Store a new product.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Models\Product
     */
    public function store(Request $request): Product
    {
        DB::beginTransaction();

        try {
            // Extract data from the request
            $prod = $request['product'];
            $company_id = $request['company_id'];
            $pics = $request['product']['pictures'];
            $price_list = $prod['price_list'];
            $categories = $prod['category'];

            $product = Product::where('company_id', $company_id)
                ->where(function ($query) use ($prod) {
                    $query->where('code', strtoupper($prod['code']))
                        ->orWhere('name', strtoupper($prod['name']));
                })->first();

            if ($product) {
                if ($product->code === strtoupper($prod['code'])) {
                    throw new Exception('Ya se encuentra registrado un producto con este código.');
                }
                if ($product->name === strtoupper($prod['name'])) {
                    throw new Exception('Ya se encuentra registrado un producto con ese nombre.');
                }
            } else {
                $product = new Product();
            }

            $seePriceOnTheWeb = false;

            if (array_key_exists('see_price_on_the_web', $prod)) {
                // El parámetro existe en el array
                $seePriceOnTheWeb = $prod['see_price_on_the_web'];
            } else {
                if (array_key_exists('view_price', $prod)) {
                    // El parámetro existe en el array
                    $seePriceOnTheWeb = $prod['view_price'];
                } else {
                    // El parámetro no existe en el array
                    $seePriceOnTheWeb = false; // O algún valor por defecto
                }
            }
            // Create a new product
            $product = Product::create([
                'company_id' => $company_id,
                'name' => strtoupper($prod['name']),
                'code' => strtoupper($prod['code']),
                'sub_title' => '',
                'description' => '',
                'iva_id' => $prod['iva'],
                'money_id' => Constantes::PESOS,
                'priority_id' => $prod['priority'],
                'published_meli' => 0,
                'published_here' => $prod['published_here'],
                'slug' => Str::slug($prod['name']),
                'critical_stock' => $prod['critical_stock'],
                'apply_discount' => $prod['apply_discount'],
                'apply_discount_amount' => $prod['apply_discount_amount'],
                'apply_discount_percentage' => $prod['apply_discount_percentage'],
                'see_price_on_the_web' => $seePriceOnTheWeb,
            ]);

            // Create a new stock history for the product
            $product->stock_history()->create([
                'product_id' => $product->id,
                'quantity' => $prod['quantity'],
                'motive' => Constantes::CREA_PRODUCTO,
                'company_id' => $company_id,
                'user_id' => auth()->user()->id
            ]);

            // Create a new price list product for each price list
            collect($price_list)->each(function ($pl) use ($prod, $product) {
                $pList = PriceList::find($pl);
                PriceListProduct::create([
                    'pricelist_id' => $pList->id,
                    'product_id' => $product->id,
                    'price' => (float) $prod['cost'] + ((float) $prod['cost'] * $pList->profit_percentage / Constantes::CIENXCIEN),
                    'profit_percentage' => (float) $pList->profit_percentage,
                    'profit_rate' => ((float) $prod['cost'] * $pList->profit_percentage / Constantes::CIENXCIEN),
                ]);
            });

            // Create a new category product for each category
            collect($categories)->flatten()->each(function ($category) use ($product) {
                CategoryProduct::create([
                    'category_id' => $category,
                    'product_id' => $product->id,
                ]);
            });

            if (collect($pics)->isNotEmpty()) {

                collect($pics)->map(function ($pic) use ($product) {
                    $base64Image = str_replace('data:image/png;base64,', '', $pic['thumbUrl']);

                    $product->addMediaFromBase64($base64Image)
                        ->withCustomProperties(['company_id' => auth()->user()->companies->first()->id, 'user_id' => auth()->user()->id])
                        ->toMediaCollection('products');
                });
            }

            DB::commit();
            // Return the newly created product
            return $product;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear el producto: ' . $e->getMessage());
            throw $e; // Lanza la excepción en lugar de manejarla
        }
    }

    public function update(Request $request): Product
    {
        DB::beginTransaction();

        // try {
        // Extract data from the request
        $prod = $request['product'];
        $company_id = $request['company_id'];
        $pics = array_key_exists('pictures', $request['product']) ? $request['product']['pictures'] : [];
        $price_list = $prod['price_list'];
        $categories = $prod['category'];

        $product = Product::find($prod['id']);

        if (!$product) {
            throw new Exception('El producto no se encuentra en la base de datos.');
        }

        $seePriceOnTheWeb = false;

        if (array_key_exists('see_price_on_the_web', $prod)) {
            // El parámetro existe en el array
            $seePriceOnTheWeb = $prod['see_price_on_the_web'];
        } else {
            if (array_key_exists('view_price', $prod)) {
                // El parámetro existe en el array
                $seePriceOnTheWeb = $prod['view_price'];
            } else {
                // El parámetro no existe en el array
                $seePriceOnTheWeb = false; // O algún valor por defecto
            }
        }

        $product->name = strtoupper($prod['name']);
        $product->sub_title = '';
        $product->description = '';
        $product->iva_id = $prod['iva'];
        $product->money_id = Constantes::PESOS;
        $product->priority_id = $prod['priority'];
        $product->published_meli = 0;
        $product->published_here = $prod['published_here'];
        $product->slug = Str::slug($prod['name']);
        $product->critical_stock = $prod['critical_stock'];
        $product->apply_discount = $prod['apply_discount'];
        $product->apply_discount_amount = $prod['apply_discount_amount'];
        $product->apply_discount_percentage = $prod['apply_discount_percentage'];
        $product->see_price_on_the_web = $seePriceOnTheWeb;

        $product->save();

        // Create a new stock history for the product
        /* $product->stock_history()->update([
                'product_id' => $product->id,
                'quantity' => $prod['quantity'],
                'motive' => 'ACTUALIZA PRODUCTO',
                'company_id' => $company_id,
                'user_id' => auth()->user()->id
            ]); */
        foreach ($product->stock_history as $stock) {
            $stock->update([
                'product_id' => $product->id,
                'quantity' => $prod['quantity'],
                'motive' => 'ACTUALIZA PRODUCTO',
                'company_id' => $company_id,
                'user_id' => auth()->user()->id,
            ]);
        }

        if (collect($price_list)->isNotEmpty()) {

            collect($price_list)->each(function ($pl) use ($prod, $product) {
                $pList = PriceList::find($pl);

                $pivotRecord = PriceListProduct::where('product_id', $product->id)
                    ->where('pricelist_id', $pList->id)
                    ->first();

                $pivotRecord->update([
                    'price' => (float) $prod['cost'] + ((float) $prod['cost'] * $pList->profit_percentage / Constantes::CIENXCIEN),
                    'profit_percentage' => (float) $pList->profit_percentage,
                    'profit_rate' => ((float) $prod['cost'] * $pList->profit_percentage / Constantes::CIENXCIEN),
                ]);
            });
        }

        if (collect($categories)->isNotEmpty()) {

            CategoryProduct::where('product_id', $product->id)->delete();

            // Create a new category product for each category
            collect($categories)->flatten()->each(function ($category) use ($product) {
                CategoryProduct::create([
                    'category_id' => $category,
                    'product_id' => $product->id,
                ]);
            });
        }

        if (collect($pics)->isNotEmpty()) {

            collect($pics)->map(function ($pic) use ($product) {
                $base64Image = str_replace('data:image/png;base64,', '', $pic['thumbUrl']);

                $product->clearMediaCollection('products');

                $product->addMediaFromBase64($base64Image)
                    ->withCustomProperties(['company_id' => auth()->user()->companies->first()->id, 'user_id' => auth()->user()->id])
                    ->toMediaCollection('products');
            });
        }

        DB::commit();
        // Return the newly created product
        return $product;
        /* } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al editar el producto: ' . $e->getMessage());
            return response()->json(['error' => 'Error al editar el producto'], 500);
        } */
    }
}
