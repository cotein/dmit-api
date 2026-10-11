@extends('emails.partials.layout')

@section('title', 'Comprobante')

@section('content')
    {{-- El cuerpo lo escribe el usuario en el editor del facturador: va tal cual. --}}
    {!! $htmlBody !!}
@endsection
