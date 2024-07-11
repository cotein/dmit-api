<?php

namespace App\Src\Traits;

trait CategoryTrait
{

    public function categoriesHandler($categories)
    {
        $arr = [];

        foreach ($categories as $category) {
            if ($category['parent_id'] === null) {
                array_push($arr, [
                    'id' => $category['id'],
                    'parent_id' => $category['parent_id'],
                    'name' => $category['name'],
                    'code' => $category['code'],
                    'children' => [],
                ]);
            }

            $this->categoriesChildren($arr, $category);
        }
    }

    public function categoriesChildren($arr, $cat)
    {
        foreach ($arr as $category) {
            if ($category['id'] === $cat['parent_id']) {
                array_push($category['children'], [
                    'id' => $category['id'],
                    'parent_id' => $category['parent_id'],
                    'name' => $category['name'],
                    'code' => $category['code'],
                    'children' => [],
                ]);
            } else {
                dd($category, $cat, 'pp');

                $this->categoriesChildren($category['children'], $cat);
            }
        }
    }
}
