@extends('emails.partials.layout')

@section('title', 'Restablecer tu contraseña')

@section('content')
    <p style="margin:0 0 14px;">Hola{{ $user->name ? ' ' . $user->name : '' }},</p>

    <p style="margin:0 0 18px;">
        Pediste restablecer la contraseña de tu cuenta de DMIT. Entrá a este enlace para elegir una nueva:
    </p>

    <p style="margin:0 0 18px;text-align:center;">
        <a href="{{ $url }}"
            style="display:inline-block;padding:14px 26px;background:#1aa19c;color:#ffffff;font-size:16px;font-weight:600;text-decoration:none;border-radius:60px;">
            Restablecer contraseña
        </a>
    </p>

    <p style="margin:0 0 14px;">
        El enlace sirve una sola vez y vence en 10 minutos. Si el botón no funciona, copiá y pegá esta dirección
        en el navegador:
    </p>

    <p style="margin:0 0 14px;word-break:break-all;color:#4b5563;font-size:13px;">{{ $url }}</p>

    <p style="margin:0;color:#6b7280;font-size:13px;">
        Si no pediste vos el cambio, ignorá este correo: tu contraseña sigue siendo la misma.
    </p>
@endsection
