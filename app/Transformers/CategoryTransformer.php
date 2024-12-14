<?php

namespace App\Transformers;

use App\Models\Category;
use League\Fractal\TransformerAbstract;

class CategoryTransformer extends TransformerAbstract
{
    /**
     * List of resources to automatically include
     *
     * @var array
     */
    protected array $defaultIncludes = [
        //
    ];

    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected array $availableIncludes = [
        //
    ];

    public function category_handle($categories)
    {
        $arr = [];

        $categories->map(function ($category) {
            if ($category->parent_id == 0) {

                array_push(
                    $arr,
                    [
                        'id' => $category->id,
                        'parent_id' => $category->parent_id,
                        'name' => $category->name,
                        'code' => $category->code,
                        'status' => $category->status,
                        'children' => []
                    ]
                );
            }
            $category_son(arr, category);
        });

        return arr;
    }
    public function transform_categories($categories)
    {

        $categories->sortBy('parent_id');
        $categories_list = $this->category_handle($categories->toArray());

        /* $sort_categories = collect(categories_list).sortBy('name');

        this.$store.dispatch('set_categories_list', sort_categories.toArray()); */
    }

    public function transform(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,

        ];
    }
}
