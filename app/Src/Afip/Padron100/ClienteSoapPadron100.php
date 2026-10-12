<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

use SoapClient;
use SoapFault;
use App\Src\Afip\Padron100\Excepciones\Padron100Exception;

/**
 * Cliente real del WS ws_sr_padron_a100.
 *
 * Por qué SOAP a mano y no un SoapClient en modo WSDL: el WSDL del servicio no es
 * público. El manual sólo publica la URL del servicio; las cuatro variantes de
 * "?wsdl" que se probaron (aws y awshomo, con y sin query) dan 404, y el WSDL se baja
 * del WSASS recién al adherir el servicio. Sin WSDL, PHP no puede armar el pedido solo,
 * así que el sobre se construye textual como en el manual y se manda con __doRequest():
 * así el XML que sale es exactamente el del ejemplo, sin que PHP lo "normalice".
 *
 * El SOAPAction va vacío, igual que en el WSDL del otro servicio de padrón del mismo
 * paquete (WS_SR_PADRON_A13.wsdl declara soapAction="" en todas sus operaciones).
 *
 * NADA de lo que entra acá se registra en logs: el pedido lleva el token y la firma del
 * WSAA. Los mensajes de error sólo dicen qué colección se pidió y qué contestó ARCA.
 */
class ClienteSoapPadron100 implements ClientePadron100
{
    /**
     * Id del servicio, textual del manual v2.1 (§2.4). Vive acá porque es la identidad de
     * este cliente; el WSAA lo reusa para pedir el ticket (el ticket se emite POR servicio,
     * así que un token de 'factura' no sirve para el padrón).
     */
    public const SERVICIO = 'ws_sr_padron_a100';

    /** Namespace del servicio, textual del manual (§3.1 y §3.2). */
    public const NAMESPACE_A100 = 'http://a100.soap.ws.server.pucParam.sr/';

    public const PRODUCTION = 'PRODUCTION';

    public const TESTING = 'TESTING';

    /** URLs de conexión del manual v2.1, §2.3. */
    public const URL_PRODUCCION = 'https://aws.afip.gov.ar/sr-parametros/webservices/parameterServiceA100';
    public const URL_TESTING = 'https://awshomo.afip.gov.ar/sr-parametros/webservices/parameterServiceA100';

    /** @var CredencialesPadron100 */
    private $credenciales;

    /** @var ParserPadron100 */
    private $parser;

    /** @var string */
    private $environment;

    /** @var string */
    private $location;

    /** @var int */
    private $timeout;

    /** @var SoapClient|null Cliente de la última llamada, para leer el estado HTTP. */
    private $soap;

    public function __construct(
        CredencialesPadron100 $credenciales,
        string $environment = self::TESTING,
        ?string $location = null,
        ?ParserPadron100 $parser = null,
        int $timeout = 60
    ) {
        $this->credenciales = $credenciales;
        $this->environment = strtoupper($environment);
        $this->location = $location === null ? self::urlDe($this->environment) : $location;
        $this->parser = $parser === null ? new ParserPadron100() : $parser;
        $this->timeout = $timeout;
    }

    public static function urlDe(string $environment): string
    {
        return strtoupper($environment) === self::PRODUCTION ? self::URL_PRODUCCION : self::URL_TESTING;
    }

    public function location(): string
    {
        return $this->location;
    }

    public function traerColeccion(string $collectionName): ColeccionPadron100
    {
        $respuesta = $this->pedir(trim($collectionName));

        // Si ARCA contesta algo que no es el XML del servicio (el HTML de error de su
        // servidor, por ejemplo), el motivo real es el estado HTTP: sin esto el mensaje
        // sería "respuesta vacía" y no se sabría si fue un 500 de ARCA o un 404 de la URL.
        if (trim($respuesta) === '') {
            throw new Padron100Exception(
                "ARCA no devolvió una respuesta para {$collectionName}" . $this->detalleHttp() . '.'
            );
        }

        try {
            return $this->parser->parsear($respuesta, $collectionName);
        } catch (Padron100Exception $e) {
            throw new Padron100Exception($e->getMessage() . $this->detalleHttp(), 0, $e);
        }
    }

    /**
     * Verificación de disponibilidad del servicio (manual §3.1). No lleva token ni firma:
     * es el único método que se puede invocar sin certificado.
     *
     * @return array<string, string> appserver, authserver, dbserver (OK o ERROR)
     */
    public function dummy(): array
    {
        $respuesta = $this->enviar(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:a100="' . self::NAMESPACE_A100 . '">'
            . '<soapenv:Header/>'
            . '<soapenv:Body><a100:dummy/></soapenv:Body>'
            . '</soapenv:Envelope>'
        );

        $estado = [];

        foreach (['appserver', 'authserver', 'dbserver'] as $servidor) {
            if (preg_match('~<' . $servidor . '>([^<]*)</' . $servidor . '>~', $respuesta, $coincidencias) === 1) {
                $estado[$servidor] = trim($coincidencias[1]);
            }
        }

        if ($estado === []) {
            throw new Padron100Exception('El dummy del servicio de padrón no devolvió el estado de sus servidores.');
        }

        return $estado;
    }

    /**
     * Arma el sobre de getParameterCollectionByName tal como el ejemplo del manual.
     *
     * Es público a propósito: es la única parte del cliente que se puede verificar sin
     * certificado (que salgan los cuatro campos y que no se cuele nada del certificado).
     */
    public function armarPedido(string $collectionName): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:a100="' . self::NAMESPACE_A100 . '">'
            . '<soapenv:Header/>'
            . '<soapenv:Body>'
            . '<a100:getParameterCollectionByName>'
            . '<token>' . $this->escapar($this->credenciales->token()) . '</token>'
            . '<sign>' . $this->escapar($this->credenciales->sign()) . '</sign>'
            . '<cuitRepresentada>' . $this->escapar($this->credenciales->cuitRepresentada()) . '</cuitRepresentada>'
            . '<collectionName>' . $this->escapar($collectionName) . '</collectionName>'
            . '</a100:getParameterCollectionByName>'
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';
    }

    private function pedir(string $collectionName): string
    {
        if ($collectionName === '') {
            throw new Padron100Exception('Hay que indicar el nombre de la colección (Anexo 5.1 del manual).');
        }

        // Un nombre fuera del Anexo 5.1 no se corta acá (ARCA puede sumar colecciones sin
        // que toquemos el código), pero el sincronizador lo deja anotado en el informe:
        // un nombre mal escrito devuelve error del servicio.

        if (trim($this->credenciales->token()) === '' || trim($this->credenciales->sign()) === '') {
            throw new Padron100Exception(
                'No hay ticket de acceso del WSAA para ' . self::SERVICIO
                . ': revisá el certificado y la clave privada del emisor.'
            );
        }

        return $this->enviar($this->armarPedido($collectionName));
    }

    /**
     * Envía el sobre y devuelve el XML de respuesta.
     */
    private function enviar(string $sobre): string
    {
        try {
            $this->soap = new SoapClient(null, [
                'location' => $this->location,
                'uri' => self::NAMESPACE_A100,
                // SOAP 1.1 porque los ejemplos del manual (§3.1.1 y §3.2.1) traen el sobre
                // con http://schemas.xmlsoap.org/soap/envelope/, que es SOAP 1.1, y la
                // respuesta del manual también. El sobre se arma a mano, así que la versión
                // de acá es la que va en los encabezados: tiene que coincidir con el cuerpo.
                'soap_version' => SOAP_1_1,
                'connection_timeout' => $this->timeout,
                'exceptions' => true,
                // trace sólo para poder leer los encabezados de la respuesta en un error.
                // El pedido (que lleva el token) no se lee ni se registra nunca.
                'trace' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'stream_context' => stream_context_create([
                    'http' => ['timeout' => $this->timeout],
                ]),
            ]);

            // Sin SOAPAction, igual que el WSDL del padrón A13 del paquete.
            return (string) $this->soap->__doRequest($sobre, $this->location, '', SOAP_1_1);
        } catch (SoapFault $e) {
            throw new Padron100Exception(
                'No se pudo hablar con el servicio de padrón (' . $this->environment . '): ' . $e->getMessage()
            );
        }
    }

    /**
     * Primera línea de la respuesta HTTP ("HTTP/1.1 500 Internal Server Error"), si se
     * pudo leer. Sólo encabezados: acá no entra nada del pedido.
     */
    private function detalleHttp(): string
    {
        if (! $this->soap instanceof SoapClient) {
            return '';
        }

        $encabezados = (string) $this->soap->__getLastResponseHeaders();

        if (trim($encabezados) === '') {
            return '';
        }

        $primera = strtok($encabezados, "\r\n");

        return $primera === false || $primera === '' ? '' : ' (respuesta HTTP: ' . $primera . ')';
    }

    private function escapar(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
