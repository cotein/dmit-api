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
            $user = new User;

            $user->name = strtoupper($request->user['name']);
            $user->last_name = strtoupper($request->user['lastName']);
            $user->email = $request->user['email'];
            $user->password = Hash::make($request->user['password']);

            $user->save();

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
