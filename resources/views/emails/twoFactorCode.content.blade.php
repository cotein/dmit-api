@extends('emails.partials.layout')

@section('title', 'Tu código de verificación')

@section('content')
    <p style="margin:0 0 14px;">Hola{{ $user->name ? ' ' . $user->name : '' }},</p>

    <p style="margin:0 0 14px;">
        Alguien está entrando a tu cuenta de DMIT y el doble factor pide el código. Este es:
    </p>

    <div
        style="margin:0 0 18px;padding:18px 0;background:#f3f4f6;border-radius:12px;text-align:center;font-size:34px;font-weight:700;letter-spacing:10px;color:#0f172a;">
        {{ $code }}
    </div>

    <p style="margin:0 0 14px;">Vence en 15 minutos y sirve una sola vez.</p>

    <p style="margin:0;color:#6b7280;font-size:13px;">
        Si no estás intentando entrar vos, cambiá tu contraseña cuanto antes: alguien tiene tu usuario y tu contraseña.
    </p>
@endsection
