<?php

namespace App\Http\Controllers;

use App\Transformers\UserTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(): Response
    {
        $data = request()->validate([
            'email' => 'email|required',
            'password' => 'required'
        ]);

        if (!auth()->attempt($data)) {
            return response(['message' => 'Credenciales incorrectas'], 400);
        }

        if (!auth()->user()->isActive()) {
            return response(['message' => 'Usuario no activado'], 400);
        }

        $token = auth()->user()->createToken('API Token')->accessToken;

        $user = fractal(auth()->user(), new UserTransformer())->toArray()['data'];

        return response(['user' => $user, 'token' => $token]);
    }
}
