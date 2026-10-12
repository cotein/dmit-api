<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

use Cotein\ApiAfip\Afip\WS\WebService;

/**
 * Ticket de acceso (WSAA) para ws_sr_padron_a100.
 *
 * NO arma un WSAA nuevo: extiende el mismo WebService del paquete cotein/api-afip que usa
 * el facturador electrónico para 'factura', 'padron' y 'WSFECRED'. El padre hace todo el
 * circuito — TRA con el nombre del servicio, firma PKCS#7 con el certificado de la
 * empresa, loginCms contra el WSAA, guardado del TA.xml y persistencia del token en
 * afip_tokens (clave ws + environment + company_id) — y acá sólo se le pasa el servicio.
 *
 * El servicio es lo único que cambia, y es obligatorio que cambie: el WSAA emite el
 * ticket POR SERVICIO, así que un token de 'factura' no sirve para el padrón. El nombre
 * sale del propio cliente (ClienteSoapPadron100::SERVICIO), que es donde vive la
 * identidad del web service.
 *
 * El CUIT representado es el del emisor (el que pide el token): ARCA exige que sea el
 * mismo que solicitó el ticket o uno presente en sus 'relations'.
 */
class AutenticacionPadron100 extends WebService implements CredencialesPadron100
{
    /** Id del servicio, textual del manual v2.1 (§2.4): así viaja también en el TRA. */
    public const SERVICE = ClienteSoapPadron100::SERVICIO;

    /**
     * @param  string  $environment  PRODUCTION | TESTING
     * @param  string|null  $cuitRepresentada  CUIT del emisor; si es null, el padre usa
     *                                         WS_AFIP_CUIT_REPRESENTADA del .env
     */
    public function __construct(string $environment, ?string $cuitRepresentada = null, ?int $companyId = null, ?int $userId = null)
    {
        parent::__construct(self::SERVICE, $environment, $cuitRepresentada, $companyId, $userId);
    }

    /**
     * El padre declara connect() abstracto porque sus otros hijos abren el SOAP ahí.
     * Acá no corresponde: esta clase pide el ticket y nada más; el cliente SOAP lo arma
     * ClienteSoapPadron100, así el WSAA queda en un solo archivo.
     */
    public function connect(): void
    {
    }

    public function token(): string
    {
        return (string) $this->token;
    }

    public function sign(): string
    {
        return (string) $this->sign;
    }

    public function cuitRepresentada(): string
    {
        return (string) $this->cuitRepresentada;
    }
}
