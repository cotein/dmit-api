<?php

namespace App\Src\Repositories;

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class CategoryRepository
{

    public function find(Request $request)
    {
        $categories = Category::query();

        $categories = $categories->where('company_id', $request->company_id);

        if ($request->has('category_id')) {

            $categories = $categories->where('category_id', $request->category_id);
        }

        return $categories->get(['active', 'attributes', 'code', 'id', 'name', 'parent_id', 'slug']);
    }

    public function store(Request $request): Category
    {
        $data = $request['category'];

        $category = new Category();
        $category->code = strtoupper(trim($data['code']));
        $category->name = strtoupper($data['name']);
        $category->slug = Str::slug($data['name']);
        $category->parent_id = $data['parent_id'];
        $category->attributes = $data['attributes'];
        $category->active = true;
        $category->company_id = $data['company_id'];
        $category->user_id = auth()->user()->id;
        $category->save();

        return $category;
    }
}
