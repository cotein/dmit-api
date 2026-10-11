@extends('emails.partials.layout')

@section('title', 'Tu contraseña cambió')

@section('content')
    <p style="margin:0 0 14px;">Hola{{ $user->name ? ' ' . $user->name : '' }},</p>

    <p style="margin:0 0 14px;">
        La contraseña de tu cuenta de DMIT se cambió correctamente
        el {{ $changedAt->format('d/m/Y') }} a las {{ $changedAt->format('H:i') }} hs.
    </p>

    <p style="margin:0 0 14px;">
        Por seguridad <strong>cerramos todas las sesiones que estaban abiertas</strong>:
        si tenías el sistema abierto en otro dispositivo, ahí te va a pedir la contraseña nueva.
    </p>

    <p style="margin:0;color:#6b7280;font-size:13px;">
        Si no fuiste vos, escribinos por WhatsApp apenas lo veas: alguien podría tener acceso a tu correo.
    </p>
@endsection
