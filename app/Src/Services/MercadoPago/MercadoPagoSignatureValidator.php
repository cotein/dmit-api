<?php

namespace App\Src\Services\MercadoPago;

/**
 * Valida la firma de una notificación de Mercado Pago (header x-signature).
 *
 * Mercado Pago firma un "manifest" con HMAC-SHA256 usando la clave secreta del
 * webhook. Sin esta validación, cualquiera que conozca la URL pública puede
 * inventar un pago aprobado y marcar comprobantes como cobrados.
 *
 * El manifest tiene esta forma exacta (el orden y los ';' importan):
 *
 *     id:{data.id};request-id:{x-request-id};ts:{ts};
 *
 * Los campos que no vienen (por ejemplo request-id en pruebas manuales) se
 * omiten del manifest, no se mandan vacíos.
 */
class MercadoPagoSignatureValidator
{
    /**
     * @param  string|null  $signatureHeader  valor de x-signature: "ts=...,v1=..."
     * @param  string|null  $requestId  valor de x-request-id
     * @param  string|null  $dataId  el id del recurso notificado (data.id)
     */
    public function isValid(?string $signatureHeader, ?string $requestId, ?string $dataId): bool
    {
        $secret = config('mercadopago.webhook_secret');

        // Fail-closed: sin secreto configurado no se acepta nada. Perder un
        // webhook lo recupera la conciliación; aceptar uno falsificado, no.
        if (empty($secret) || empty($signatureHeader) || empty($dataId)) {
            return false;
        }

        $partes = $this->parseHeader($signatureHeader);

        if (empty($partes['ts']) || empty($partes['v1'])) {
            return false;
        }

        $manifest = $this->manifest($dataId, $requestId, $partes['ts']);
        $esperada = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($esperada, $partes['v1']);
    }

    /**
     * Arma el manifest tal cual lo firma Mercado Pago.
     */
    public function manifest(string $dataId, ?string $requestId, string $ts): string
    {
        $manifest = 'id:' . $dataId . ';';

        if (! empty($requestId)) {
            $manifest .= 'request-id:' . $requestId . ';';
        }

        return $manifest . 'ts:' . $ts . ';';
    }

    /**
     * @return array{ts: ?string, v1: ?string}
     */
    private function parseHeader(string $header): array
    {
        $valores = [];

        foreach (explode(',', $header) as $par) {
            $trozo = explode('=', trim($par), 2);

            if (count($trozo) === 2) {
                $valores[$trozo[0]] = $trozo[1];
            }
        }

        return ['ts' => $valores['ts'] ?? null, 'v1' => $valores['v1'] ?? null];
    }
}
