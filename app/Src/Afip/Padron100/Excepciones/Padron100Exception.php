<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100\Excepciones;

use RuntimeException;

/**
 * Falla al hablar con ws_sr_padron_a100 (o al leer lo que devolvió).
 *
 * Se usa una excepción propia para poder decir en el mensaje qué colección se estaba
 * pidiendo y qué contestó ARCA. Nunca lleva el token, la firma ni nada del certificado:
 * el pedido completo no se arma acá y no se registra en ningún log.
 */
class Padron100Exception extends RuntimeException
{
}
