<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Src\Constantes;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Events\RegisteredUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Transformers\UserTransformer;
use Carbon\Carbon;

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

        $token = auth()->user()->createToken('API DMIT')->accessToken;

        $user = fractal(auth()->user(), new UserTransformer())->toArray()['data'];

        return response(['user' => $user, 'token' => $token]);
    }

    private function createUserFromRequest()
    {
        $user = new User;
        $user->name = strtoupper(request()->given_name);
        $user->last_name = strtoupper(request()->family_name);
        $user->email = request()->email;
        $user->type_user_id = Constantes::USER_ADMIN;
        $user->google_id = request()->sub;
        $user->active = true;
        $user->email_verified_at = Carbon::now();
        $user->save();

        event(new RegisteredUser($user));

        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->log('Usuario creado');

        return $user;
    }

    private function generateTokenForUser($user)
    {
        Auth::login($user);

        if (is_null($user->google_id)) {
            $user->google_id = request()->sub;
            $user->save();
        }

        $tokenResult = $user->createToken('API DMIT');
        $token = $tokenResult->accessToken;
        $expiresAt = $tokenResult->token->expires_at;

        $userToken = [
            'access_token' => $token,
            'expires_in' => $expiresAt->diffInSeconds(Carbon::now()),
            'refresh_token' => $tokenResult->token->id, // Assuming you have a refresh token mechanism
            'token_type' => 'Bearer',
        ];

        $userData = fractal($user, new UserTransformer())->toArray()['data'];

        return response(['user' => $userData, 'userToken' => $userToken]);
    }

    public function googleLogin(): Response
    {
        $data = request()->validate([
            'email' => 'email|required',
            'sub' => 'required',
            'family_name' => 'required',
            'given_name' => 'required',
        ]);

        $user = User::where('email', request()->email)->first();

        if ($user) {
            return $this->generateTokenForUser($user);
        } else {
            $user = $this->createUserFromRequest();
            return $this->generateTokenForUser($user);
        }
    }
}
