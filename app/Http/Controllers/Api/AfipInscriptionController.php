<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AfipInscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AfipInscriptionController extends Controller
{
    public function index()
    {
        $inscriptions = AfipInscription::all('id', 'name');

        $inscriptions = $inscriptions->map(function ($i) {
            return [
                'value' => $i->id,
                'label' => $i->name
            ];
        });

        return response()->json($inscriptions, 200);
    }
}
