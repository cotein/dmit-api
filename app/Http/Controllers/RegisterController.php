<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Src\Constantes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Cotein\ApiAfip\Facades\AfipWebService;
use App\Http\Requests\RegisterUserFormRequest;

class RegisterController extends Controller
{

    public function register(RegisterUserFormRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = new User;

            $user->name = strtoupper($request->user['name']);
            $user->last_name = strtoupper($request->user['lastName']);
            $user->email = $request->user['email'];
            $user->password = Hash::make($request->user['password']);
            if ($request->user['email'] === 'diego.barrueta@gmail.com' || $request->user['email'] === 'marcelo.j.callo@gmail.com' || $request->user['email'] === 'marcelo.callao@piamondsa.com.ar') {
                $user->type_user_id = Constantes::USER_ROOT;
            } else {
                $user->type_user_id = Constantes::USER_ADMIN;
            }

            $user->save();

            $user->sendEmailVerificationNotification();

            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->log('Usuario creado');

            DB::commit();

            return response()->json([
                'name' => $request->name,
                'message' => 'Usuario creado satisfactoriamente',

            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->withProperties(['exception' => $e])
                ->log('Error al crear usuario');

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
