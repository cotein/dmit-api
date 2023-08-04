<?php

namespace App\Src\Repositories\Contracts;

use Illuminate\Http\Request;

interface RemovableInterface
{
    public function remove(Request $request);
}
