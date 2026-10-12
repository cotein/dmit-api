<?php

namespace App\Src\Services\MercadoPago;

use RuntimeException;

/**
 * Error al hablar con Mercado Pago. Lleva el status HTTP de MP cuando existe
 * (401 = credencial inválida, 400 = payload rechazado, etc.), porque de eso
 * depende qué hace el que llama: reintentar, renovar el token o avisar al usuario.
 */
class MercadoPagoException extends RuntimeException
{
}
