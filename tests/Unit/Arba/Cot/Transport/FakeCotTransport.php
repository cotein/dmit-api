<?php

namespace Tests\Unit\Arba\Cot\Transport;

use App\Src\Arba\Cot\Transport\CotHttpResponse;
use App\Src\Arba\Cot\Transport\CotTransport;

/**
 * Transporte falso: registra lo que se habría enviado y devuelve una respuesta
 * prefijada.
 *
 * Es lo que permite probar el envío completo —armado del archivo, credenciales,
 * persistencia de la respuesta— sin salir a internet y sin depender de que ARBA
 * esté disponible o de tener una CIT real.
 */
final class FakeCotTransport implements CotTransport
{
    /**
     * @var array<int, array{url: string, campos: array<string, string>, archivos: array<string, array{nombre: string, contenido: string}>}>
     */
    public array $llamadas = [];

    public function __construct(private CotHttpResponse $respuesta)
    {
    }

    public static function ok(string $cuerpo): self
    {
        return new self(new CotHttpResponse(status: 200, cuerpo: $cuerpo));
    }

    public static function conStatus(int $status, string $cuerpo = ''): self
    {
        return new self(new CotHttpResponse(status: $status, cuerpo: $cuerpo));
    }

    public static function caido(string $mensaje = 'Operation timed out'): self
    {
        return new self(new CotHttpResponse(status: null, cuerpo: '', errorTransporte: $mensaje));
    }

    public function post(string $url, array $campos, array $archivos = []): CotHttpResponse
    {
        $this->llamadas[] = ['url' => $url, 'campos' => $campos, 'archivos' => $archivos];

        return $this->respuesta;
    }

    public function envio(): bool
    {
        return $this->llamadas !== [];
    }

    /**
     * @return array{url: string, campos: array<string, string>, archivos: array<string, array{nombre: string, contenido: string}>}|null
     */
    public function ultimaLlamada(): ?array
    {
        return $this->llamadas === [] ? null : $this->llamadas[count($this->llamadas) - 1];
    }

    public function contenidoEnviado(): ?string
    {
        return $this->ultimaLlamada()['archivos']['file']['contenido'] ?? null;
    }

    public function nombreArchivoEnviado(): ?string
    {
        return $this->ultimaLlamada()['archivos']['file']['nombre'] ?? null;
    }
}
