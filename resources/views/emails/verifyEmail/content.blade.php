@extends('emails.partials.layout')

@section('title', 'Activá tu cuenta')

@section('content')
    <p style="margin:0 0 14px;">Hola{{ ($user->name ?? '') ? ' ' . $user->name : '' }},</p>

    <p style="margin:0 0 18px;">
        Falta un paso para que puedas empezar a facturar con DMIT: confirmá que este correo es tuyo.
    </p>

    <p style="margin:0 0 18px;text-align:center;">
        <a href="{{ $url }}"
            style="display:inline-block;padding:14px 26px;background:#1aa19c;color:#ffffff;font-size:16px;font-weight:600;text-decoration:none;border-radius:60px;">
            Activar mi cuenta
        </a>
    </p>

    <p style="margin:0 0 14px;">
        El enlace vence en 1 hora. Si el botón no funciona, copiá y pegá esta dirección en el navegador:
    </p>

    <p style="margin:0 0 14px;word-break:break-all;color:#4b5563;font-size:13px;">{{ $url }}</p>

    <p style="margin:0;color:#6b7280;font-size:13px;">
        Si no creaste una cuenta en DMIT, ignorá este correo.
    </p>
@endsection
