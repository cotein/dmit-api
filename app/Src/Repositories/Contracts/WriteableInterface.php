<?php

namespace App\Src\Repositories\Contracts;

use Illuminate\Http\Request;

interface WriteableInterface
{
    public function create(Request $request);
    public function update(Request $request);
}
