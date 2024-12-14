<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class RegisterUserFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'user.email' => 'required|email|unique:users,email',
            'user.name' => 'required',
            'user.lastName' => 'required',
            'user.password' => 'required|min:8',
            'user.password_confirmation' => 'required|same:user.password'
        ];
    }

    public function messages(): array
    {
        return [
            'user.email.required' => 'El campo email es requerido',
            'user.email.unique' => 'Este email ya se encuentra registrado',
            'user.name.required' => 'El campo nombre es requerido',
            'user.lastName.required' => 'El campo Apellido es requerido',
            'user.password.required' => 'El campo contraseña es requerido',
            'user.password.min' => 'La contraseña debe contener como mínimo 8 carateres',
            'user.password_confirmation.required' => 'El campo confirmar contraseña es requerido',
            'user.password_confirmation.same' => 'La confirmación de contraseña debe coincidir con la contraseña',
        ];
    }
}
