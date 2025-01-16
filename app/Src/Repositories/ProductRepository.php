<?php

namespace App\Src\Repositories;

use Exception;
use App\Models\CategoryProduct;
use App\Models\PriceList;
use App\Models\PriceListProduct;
use App\Models\Product;
use App\Src\Constantes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        if ($request->has('name')) {
            $products = $products->where('name', 'like', "%{$request->name}%");
        }

        return $products->get();
    }

    /* public function store(Request $request): Product
    {
        $prod = $request['product'];
        $company_id = $request['company_id'];
        $pics = $request['product']['pictures'];
        $price_list = $prod['price_list'];
        $categories = $prod['category'];
        $product = new Product();

        $product->company_id = $company_id;
        $product->name = strtoupper($prod['name']);
        $product->code = strtoupper($prod['code']);
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
        $product->see_price_on_the_web = $prod['view_price'];
        $product->save();

        $product->stock_history()->create([
            'product_id' => $product->id,
            'quantity' => $prod['quantity'],
            'motive' => Constantes::CREA_PRODUCTO,
            'company_id' => $company_id,
            'user_id' => auth()->user()->id
        ]);

        collect($price_list)->map(function ($pl) use ($company_id, $prod, $product) {

            $pList = PriceList::find($pl);

            $priceListProduct = new PriceListProduct();
            $priceListProduct->pricelist_id = $pList->id;
            $priceListProduct->product_id = $product->id;
            $priceListProduct->price = (float) $prod['cost'] + ((float) $prod['cost'] * $pList->profit_percentage / Constantes::CIENXCIEN);
            $priceListProduct->profit_percentage = (float) $pList->profit_percentage;
            $priceListProduct->profit_rate = ((float) $prod['cost'] * $pList->profit_percentage / Constantes::CIENXCIEN);
            $priceListProduct->save();

            unset($pList);
            unset($priceListProduct);
        });

        collect($categories)->map(function ($groupCategory) use ($product) {

            collect($groupCategory)->map(function ($category) use ($product) {
                $categoryProduct = new CategoryProduct();
                $categoryProduct->category_id = $category;
                $categoryProduct->product_id = $product->id;
                $categoryProduct->save();

                unset($categoryProduct);
            });
        });

        collect($pics)->map(function ($photo) use ($product) {
            $base64File = $photo['thumbUrl'];

            // Decodifica el archivo base64 en un archivo temporal
            $fileData = base64_decode($base64File);
            $tempFilePath = tempnam(sys_get_temp_dir(), 'base64file');
            file_put_contents($tempFilePath, $fileData);

            // Asigna el archivo al modelo Media
            $product->addMedia($tempFilePath)->toMediaCollection('products');
        });
        // Obtén el archivo base64 enviado por el cliente

        return $product;
    } */

    /**
     * Store a new product.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Models\Product
     */
    public function store(Request $request): Product
    {
        // Extract data from the request
        $prod = $request['product'];
        $company_id = $request['company_id'];
        $pics = $request['product']['pictures'];
        $price_list = $prod['price_list'];
        $categories = $prod['category'];

        $product = Product::firstOrNew([
            'code' => strtoupper($prod['code']),
            'company_id' => $company_id
        ]);

        if ($product->exists) {
            throw new Exception('Ya se encuentra registrado un producto con este código.');
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
            'see_price_on_the_web' => $prod['view_price'],
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

        // Add each picture to the product's media collection
        collect($pics)->each(function ($photo) use ($product) {
            $base64File = $photo['thumbUrl'];
            $fileData = base64_decode($base64File);
            $tempFilePath = tempnam(sys_get_temp_dir(), 'base64file');
            file_put_contents($tempFilePath, $fileData);
            $product->addMedia($tempFilePath)->toMediaCollection('products');
        });

        // Return the newly created product
        return $product;
    }
}
