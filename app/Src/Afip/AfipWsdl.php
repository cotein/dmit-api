<?php

declare(strict_types=1);

namespace App\Src\Afip;

use App\Exceptions\Afip\AfipWsdlOutdatedException;
use Cotein\ApiAfip\Afip\WS\WS_CONST;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza los WSDL oficiales de ARCA con los que trae el paquete cotein/api-afip.
 *
 * Por qué existe: el paquete 1.3.3 (la última publicada) trae el WSDL de PRODUCCIÓN viejo, sin
 * <CondicionIVAReceptorId>, el campo que la RG 5616 (v4.0 del manual WSFE, 17/03/2025) hace
 * obligatorio. El SoapClient de PHP trabaja en modo WSDL y descarta EN SILENCIO todo campo que
 * no esté declarado en el esquema: el dato nunca sale de la aplicación y ARCA contesta 10245
 * ("el campo Condición Frente al IVA del receptor resultará obligatorio"). El error apunta a
 * ARCA, pero la culpa es del WSDL local.
 *
 * Los WSDL de resources/afip/wsdl son los que publica ARCA hoy (descargados de
 * https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL y de su equivalente de homologación).
 */
final class AfipWsdl
{
    /** Campo obligatorio desde la RG 5616. */
    public const REQUIRED_FIELD = 'CondicionIVAReceptorId';

    public const PRODUCTION = 'PRODUCTION';

    public const TESTING = 'TESTING';

    /** Entornos que se sincronizan por defecto. */
    public const ENVIRONMENTS = [self::PRODUCTION, self::TESTING];

    /**
     * Normaliza el entorno igual que el paquete (WebService::__construct hace strtoupper).
     */
    public static function normalize(string $environment): string
    {
        return strtoupper(trim($environment));
    }

    /**
     * WSDL oficial que viaja con la aplicación.
     */
    public static function sourcePath(string $environment): string
    {
        return resource_path('afip/wsdl/WSFE_' . strtolower(self::normalize($environment)) . '.wsdl');
    }

    /**
     * WSDL que realmente usa el paquete (misma constante que resuelve WS_CONST::getWSDL()).
     */
    public static function targetPath(string $environment): string
    {
        return self::normalize($environment) === self::PRODUCTION
            ? WS_CONST::WSFE_PRODUCTION
            : WS_CONST::WSFE_TESTING;
    }

    /**
     * Indica si el archivo declara el campo de la RG 5616. Si no lo declara, PHP lo descarta.
     */
    public static function declaresReceptorIvaCondition(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        return strpos((string) file_get_contents($path), 'name="' . self::REQUIRED_FIELD . '"') !== false;
    }

    public static function isUpToDate(string $environment): bool
    {
        return self::declaresReceptorIvaCondition(self::targetPath($environment));
    }

    /**
     * Copia los WSDL oficiales sobre los del paquete cuando haga falta.
     *
     * @param  array<int, string>  $environments
     * @return array<int, array<string, string>>
     */
    public static function sync(array $environments = self::ENVIRONMENTS, bool $force = false): array
    {
        $resultado = [];

        foreach ($environments as $environment) {
            $environment = self::normalize($environment);

            $source = self::sourcePath($environment);
            $target = self::targetPath($environment);

            $informe = self::syncOne($environment, $source, $target, $force);

            $resultado[] = [
                'environment' => $environment,
                'source' => $source,
                'target' => $target,
                'status' => $informe['status'],
                'detail' => $informe['detail'],
            ];
        }

        return $resultado;
    }

    /**
     * @return array<string, string>
     */
    private static function syncOne(string $environment, string $source, string $target, bool $force): array
    {
        if (! is_file($source)) {
            return [
                'status' => 'error',
                'detail' => 'Falta el WSDL oficial en ' . $source,
            ];
        }

        if (! $force && self::isUpToDate($environment)) {
            return [
                'status' => 'sin cambios',
                'detail' => 'El WSDL del paquete ya declara ' . self::REQUIRED_FIELD,
            ];
        }

        $directorio = dirname($target);

        if (! is_dir($directorio) || ! is_writable($directorio)) {
            return [
                'status' => 'error',
                'detail' => 'No se puede escribir en ' . $directorio . ' (permisos)',
            ];
        }

        if (@copy($source, $target) === false) {
            return [
                'status' => 'error',
                'detail' => 'No se pudo copiar sobre ' . $target,
            ];
        }

        // El paquete lee el archivo en cada request (WSFEV1::connect apaga el cache de WSDL de PHP).
        clearstatcache(true, $target);

        return [
            'status' => 'actualizado',
            'detail' => 'WSDL de ARCA copiado sobre el del paquete',
        ];
    }

    /**
     * Deja el WSDL listo para usar y, si no puede, corta la emisión con un mensaje claro.
     *
     * Se llama antes de crear el cliente SOAP. Si el WSDL del paquete quedó viejo:
     *  1. intenta copiarlo desde el que viaja con la aplicación (el contenedor monta el código
     *     del host, así que el composer install de la imagen no alcanza para esto);
     *  2. si no tiene permisos de escritura, lanza la excepción explicando qué comando correr.
     *
     * Es preferible esto a mandar el comprobante y que ARCA conteste 10245 sin que se entienda
     * que el campo nunca salió de la aplicación.
     *
     * @throws AfipWsdlOutdatedException
     */
    public static function ensureUpToDate(string $environment): void
    {
        if (self::isUpToDate($environment)) {
            return;
        }

        $informe = self::sync([$environment], true)[0] ?? [];

        if (self::isUpToDate($environment)) {
            Log::warning('WSDL de ARCA desactualizado y corregido automáticamente', $informe);

            return;
        }

        $target = self::targetPath($environment);

        Log::error('WSDL de ARCA desactualizado: falta ' . self::REQUIRED_FIELD . ' en ' . $target, $informe);

        throw new AfipWsdlOutdatedException(self::normalize($environment), $target);
    }
}
