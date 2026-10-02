<?php

declare(strict_types=1);

namespace App\Exceptions\Afip;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base de las excepciones de ARCA.
 *
 * Define render() a propósito: Laravel lo usa para armar la respuesta, así el mensaje de ARCA
 * llega al frontend tal cual. Sin esto, en producción (APP_DEBUG=false) una excepción genérica
 * se convierte en {"message":"Server Error"} y el motivo real no lo ve nadie.
 */
class AfipException extends Exception
{
    protected int $status = 422;

    public function status(): int
    {
        return $this->status;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'arca' => $this->arca(),
        ], $this->status);
    }

    /**
     * Respuesta cruda de ARCA, cuando la hay.
     *
     * @return array<string, mixed>
     */
    public function arca(): array
    {
        return [];
    }
}
