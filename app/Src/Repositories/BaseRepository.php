<?php

namespace App\Src\Repositories;

use Illuminate\Http\Request;

class BaseRepository
{
    public function pagination($paginate, $transformedData)
    {
        return [
            /*   'current_page' => $paginate->currentPage(),
            'data' => $paginate->
            'first_page_url' => $paginate->
            'from' => $paginate->
            'last_page' => $paginate->
            'last_page_url' => $paginate->
            'links' => $paginate->
            'next_page_url' => $paginate->
            'path' => $paginate->
            'per_page' => $paginate->
            'prev_page_url' => $paginate->
            'to' => $paginate->
            'total' => $paginate-> */];
    }
}
