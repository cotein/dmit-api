<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

/**
 * Lo que el WSAA ya emitió para este servicio: token, firma y CUIT representado.
 *
 * Existe para que el armado del pedido SOAP (lo único que se puede verificar sin
 * certificado) se pueda probar con credenciales inventadas, sin pasar por el WSAA.
 * En producción lo implementa AutenticacionPadron100, que reutiliza el WSAA del
 * facturador.
 */
interface CredencialesPadron100
{
    public function token(): string;

    public function sign(): string;

    public function cuitRepresentada(): string;
}
