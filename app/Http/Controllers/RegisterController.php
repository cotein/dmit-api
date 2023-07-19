<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\RegisterUserFormRequest;
use Cotein\ApiAfip\Facades\AfipWebService;

class RegisterController extends Controller
{

    public function register(RegisterUserFormRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = User::create([
                'name' => $request->user['name'],
                'last_name' => $request->user['lastName'],
                'email' => $request->user['email'],
                'password' => Hash::make($request->user['password'])
            ], 201);

            $user->sendEmailVerificationNotification();

            return response()->json([
                'name' => $request->name,
                'message' => 'Usuario creado satisfactoriamente',

            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function checkCuit()
    {

        $wspuc13 = AfipWebService::findWebService('padron', 'production', null);

        return response()->json($wspuc13, 200);
    }
}
