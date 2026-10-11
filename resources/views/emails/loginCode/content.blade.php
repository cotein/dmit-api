@extends('emails.partials.layout')

@section('title', 'Tu código para entrar')

@section('content')
    <p style="margin:0 0 14px;">Hola{{ $user->name ? ' ' . $user->name : '' }},</p>

    <p style="margin:0 0 14px;">Pediste entrar a DMIT con un código en vez de la contraseña. Este es tu código:</p>

    <div
        style="margin:0 0 18px;padding:18px 0;background:#f3f4f6;border-radius:12px;text-align:center;font-size:34px;font-weight:700;letter-spacing:10px;color:#0f172a;">
        {{ $code }}
    </div>

    <p style="margin:0 0 14px;">Vence en 15 minutos y sirve una sola vez.</p>

    <p style="margin:0;color:#6b7280;font-size:13px;">
        Si no pediste vos este código, ignorá el correo: tu contraseña y tu cuenta siguen igual.
    </p>
@endsection
