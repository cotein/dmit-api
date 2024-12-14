<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Transformers\UserTransformer;
use Illuminate\Support\Collection;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $user = fractal(auth('api')->user(), new UserTransformer())->toArray()['data'];

        return response()->json($user, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function uploadAvatar()
    {
        $company = json_decode(request()->company);

        if (request()->avatar) {

            $user = request()->user();

            $user->clearMediaCollection('avatar');

            $user->addMedia(request()->avatar)
                ->withCustomProperties(['company_id' => $company->id, 'user_id' => auth()->user()->id])
                ->toMediaCollection('avatar');

            return response()->json($user->getMedia('avatar')->first()->getFullUrl(), 201);
        }

        throw new \Exception('Hubo un error al intentar subir su avatart', 431);
    }
}
