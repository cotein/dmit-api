<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AfipIva;

class AfipIvaController extends Controller
{
    public function index()
    {
        $ivas = AfipIva::all();

        $ivas = $ivas->map(function ($i) {
            return [
                'value' => $i->id,
                'label' => $i->name,
                'code' => $i->code
            ];
        });

        return response()->json($ivas, 200);
    }
}
