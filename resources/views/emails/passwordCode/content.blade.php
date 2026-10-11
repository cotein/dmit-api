@extends('emails.partials.layout')

@section('title', 'Tu código para restablecer la contraseña')

@section('content')
    <p style="margin:0 0 14px;">Hola{{ $user->name ? ' ' . $user->name : '' }},</p>

    <p style="margin:0 0 14px;">Pediste restablecer la contraseña de tu cuenta de DMIT. Este es tu código:</p>

    <div
        style="margin:0 0 18px;padding:18px 0;background:#f3f4f6;border-radius:12px;text-align:center;font-size:34px;font-weight:700;letter-spacing:10px;color:#0f172a;">
        {{ $code }}
    </div>

    <p style="margin:0 0 14px;">
        Ingresalo en la pantalla de restablecimiento junto con tu contraseña nueva.
        <strong>Vence en 15 minutos</strong> y sirve una sola vez.
    </p>

    <p style="margin:0;color:#6b7280;font-size:13px;">
        Si no pediste vos el cambio, ignorá este correo: tu contraseña sigue siendo la misma
        y el código deja de servir solo.
    </p>
@endsection
