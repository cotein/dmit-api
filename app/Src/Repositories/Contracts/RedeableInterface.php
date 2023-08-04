<?php

namespace App\Src\Repositories\Contracts;

use Illuminate\Http\Request;

interface RedeableInterface
{
    public function get(Request $request);
}
