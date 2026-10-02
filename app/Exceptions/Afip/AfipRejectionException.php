<?php

declare(strict_types=1);

namespace App\Exceptions\Afip;

use App\Src\Afip\ArcaResponse;

/**
 * ARCA no autorizó el comprobante. Lleva la respuesta completa para que el usuario vea,
 * siempre, qué contestó ARCA (código y mensaje de cada error u observación).
 */
class AfipRejectionException extends AfipException
{
    protected int $status = 422;

    private ArcaResponse $arcaResponse;

    public function __construct(ArcaResponse $arcaResponse)
    {
        $this->arcaResponse = $arcaResponse;

        parent::__construct($arcaResponse->summary(), 422);
    }

    public function arcaResponse(): ArcaResponse
    {
        return $this->arcaResponse;
    }

    /**
     * @return array<string, mixed>
     */
    public function arca(): array
    {
        return $this->arcaResponse->toArray();
    }
}
