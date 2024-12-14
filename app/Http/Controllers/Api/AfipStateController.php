<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AfipState;
use App\Transformers\AfipStateTransformer;

class AfipStateController extends Controller
{
    public function index()
    {
        $states = AfipState::all('id', 'name');

        $states = fractal($states, new AfipStateTransformer())->toArray()['data'];

        return response()->json($states, 200);
    }
}
