<?php

namespace App\Src\Arba\Cot\Transport;

use App\Src\Arba\Cot\CotErrors;
use Illuminate\Support\Facades\Log;

/**
 * Implementación con cURL del transporte hacia ARBA.
 *
 * Es la misma tecnología que ya usa el módulo de ARBA del sistema (la consulta
 * de alícuotas de IIBB), así que no agrega dependencias nuevas.
 *
 * Tres decisiones que no son obvias:
 *
 * 1. El archivo se manda con CURLStringFile (PHP 8.1+), no con CURLFile: evita
 *    escribir el TXT en disco. El archivo de remitos se arma en memoria y no
 *    tiene por qué quedar tirado en /tmp, con datos de clientes, esperando que
 *    alguien lo limpie.
 *
 * 2. Los redireccionamientos se siguen CON CURL_REDIR_POST_ALL. El endpoint de
 *    testing es http y el de producción https, así que un redirect es esperable;
 *    sin POSTREDIR, cURL reenvía el redirect como GET y ARBA recibe un pedido sin
 *    archivo. El error que devuelve en ese caso no dice nada del redirect y se
 *    pierde media hora buscando el problema.
 *
 * 3. La verificación del certificado NO se apaga. El módulo de ARBA que ya
 *    existía la desactiva (CURLOPT_SSL_VERIFYPEER => 0) y acá eso sería peor:
 *    por esta conexión viaja la clave de la CIT en texto plano.
 */
final class CurlCotTransport implements CotTransport
{
    public function __construct(
        private ?int $timeout = null,
        private ?int $connectTimeout = null,
        private ?bool $verifySsl = null,
    ) {
        $this->timeout = $timeout ?? (int) config('cot.timeout', 60);
        $this->connectTimeout = $connectTimeout ?? (int) config('cot.connect_timeout', 15);
        $this->verifySsl = $verifySsl ?? (bool) config('cot.verify_ssl', true);
    }

    public function post(string $url, array $campos, array $archivos = []): CotHttpResponse
    {
        $inicio = microtime(true);
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $this->armarCuerpo($campos, $archivos),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            // Sin esto, un redirect convierte el POST en GET y ARBA recibe un
            // pedido sin el archivo de remitos.
            CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => 'dmit-api-cot/1.0',
        ]);

        $cuerpo = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $errno = curl_errno($curl);

        curl_close($curl);

        $duracion = round(microtime(true) - $inicio, 3);

        if ($cuerpo === false || $errno !== 0) {
            Log::warning('COT: falló la llamada a ARBA', [
                'url' => $url,
                'curl_errno' => $errno,
                'error' => $error,
                'segundos' => $duracion,
            ]);

            return new CotHttpResponse(
                status: $status ?: null,
                cuerpo: '',
                errorTransporte: $error !== '' ? $error : 'Error de cURL sin detalle (errno ' . $errno . ')',
            );
        }

        Log::info('COT: respuesta de ARBA', [
            'url' => $url,
            'http_status' => $status,
            'segundos' => $duracion,
            'bytes' => strlen((string) $cuerpo),
        ]);

        return new CotHttpResponse(
            status: $status,
            cuerpo: (string) $cuerpo,
        );
    }

    /**
     * @param  array<string, string>  $campos
     * @param  array<string, array{nombre: string, contenido: string}>  $archivos
     * @return array<string, mixed>
     */
    private function armarCuerpo(array $campos, array $archivos): array
    {
        $cuerpo = $campos;

        foreach ($archivos as $campo => $archivo) {
            $cuerpo[$campo] = new \CURLStringFile($archivo['contenido'], $archivo['nombre'], 'text/plain');
        }

        return $cuerpo;
    }

    /**
     * Traduce el HTTP status a algo legible. No se usa para decidir el
     * resultado —de eso se ocupa CotResponseParser— pero sirve para el mensaje
     * que ve el usuario cuando la respuesta no es un XML de ARBA.
     */
    public static function descripcionHttp(?int $status): string
    {
        return match ($status) {
            200 => 'OK',
            400 => 'Pedido incorrecto',
            401, 403 => 'Credenciales de la CIT rechazadas o usuario no habilitado',
            404 => 'Endpoint inexistente (¿cambió la URL del servicio?)',
            500, 502, 503, 504 => 'Servicio de ARBA no disponible',
            null => 'Sin respuesta',
            default => 'HTTP ' . $status,
        };
    }

    /**
     * Atajo para el error de autenticación, que es el más frecuente al empezar:
     * el código 02 del catálogo.
     */
    public static function descripcionErrorAutenticacion(): string
    {
        return CotErrors::descripcion('02');
    }
}
