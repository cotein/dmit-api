<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use App\Src\Afip\Padron100\Excepciones\Padron100Exception;

/**
 * Interpreta la respuesta de getParameterCollectionByName.
 *
 * Dos rarezas del manual que este parser trata a propósito:
 *
 * 1) El campo con la descripción está escrito de DOS formas en el mismo manual v2.1:
 *    - §3.2.2 (esquema de la respuesta) y §4.2 (tabla de tipos de datos): <description>
 *    - §3.2.3 (ejemplo de respuesta, que es una respuesta real transcripta): <decription>
 *    El WSDL no es público (sólo se obtiene del WSASS al adherir el servicio), así que no
 *    hay forma de saber cuál es el correcto antes de ver una respuesta real. Por eso se
 *    leen LOS DOS nombres y se registra con cuál vino; si no viene ninguno, el registro
 *    se guarda igual (el id y los atributos sirven) y se agrega un aviso.
 *
 * 2) El ejemplo del manual pide collectionName = SUPA.TIPO_TELEFONO y la respuesta vuelve
 *    con <name>SUPA.E_PROVINCIA</name>. Por eso acá se registra el <name> que ARCA
 *    informó y se compara contra el pedido: si no coinciden, queda en los avisos de la
 *    colección (y el comando lo muestra). Nunca se asume que es la colección pedida.
 *
 * La búsqueda de nodos es por local-name(), sin prefijo ni namespace fijos: la respuesta
 * usa http://a100.soap.ws.server.pucParam.sr/ pero los prefijos (ns2, a100, soapenv) los
 * elige el servidor, y atarse a uno hace que el parseo se rompa sin motivo.
 */
final class ParserPadron100
{
    /** Nombre tal como aparece en el ejemplo de respuesta del manual. */
    public const CAMPO_DESCRIPCION_EJEMPLO = 'decription';

    /** Nombre tal como aparece en el esquema y en la tabla de tipos de datos del manual. */
    public const CAMPO_DESCRIPCION_ESQUEMA = 'description';

    /** @var array<int, string> Orden de lectura: primero el del ejemplo, después el del esquema. */
    public const CAMPOS_DESCRIPCION = [self::CAMPO_DESCRIPCION_EJEMPLO, self::CAMPO_DESCRIPCION_ESQUEMA];

    /**
     * Tope de avisos que se acumulan por colección: en T_CALLE o E_ACTIVIDAD_RUBRO un
     * problema de formato podría generar miles de líneas iguales y no aporta nada más
     * que la primera decena.
     */
    public const MAX_AVISOS = 50;

    public function parsear(string $xml, string $coleccionSolicitada): ColeccionPadron100
    {
        $documento = $this->documento($xml, $coleccionSolicitada);

        $this->cortarSiEsFault($documento, $coleccionSolicitada);

        $retorno = $this->buscar($documento, 'parameterCollectionReturn');

        if ($retorno === null) {
            throw new Padron100Exception(
                "La respuesta de ARCA para {$coleccionSolicitada} no trae <parameterCollectionReturn>."
            );
        }

        $coleccion = $this->hijo($retorno, 'parameterCollection');

        if ($coleccion === null) {
            throw new Padron100Exception(
                "La respuesta de ARCA para {$coleccionSolicitada} no trae <parameterCollection>."
            );
        }

        $avisos = [];
        $parametros = [];

        foreach ($this->hijos($coleccion, 'parameterList') as $indice => $lista) {
            $parametro = $this->parametro($lista, $indice, $avisos);

            if ($parametro !== null) {
                $parametros[] = $parametro;
            }
        }

        $metadata = $this->metadata($retorno);

        return new ColeccionPadron100(
            $coleccionSolicitada,
            $this->texto($this->hijo($coleccion, 'name')),
            $parametros,
            $metadata,
            $avisos
        );
    }

    /**
     * @param  array<int, string>  $avisos
     */
    private function parametro(DOMElement $lista, int $indice, array &$avisos): ?ParametroPadron100
    {
        $id = $this->texto($this->hijo($lista, 'id'));

        if ($id === '') {
            $this->avisar($avisos, 'parameterList #' . ($indice + 1) . ' sin <id>: se descarta, el id es la clave del registro.');

            return null;
        }

        $descripcion = null;
        $campo = null;
        $presente = null;

        foreach (self::CAMPOS_DESCRIPCION as $candidato) {
            $nodo = $this->hijo($lista, $candidato);

            if ($nodo === null) {
                continue;
            }

            if ($presente === null) {
                $presente = $candidato;
            }

            $valor = $this->texto($nodo);

            if ($valor !== '') {
                $descripcion = $valor;
                $campo = $candidato;

                break;
            }
        }

        if ($campo === null) {
            $this->avisar(
                $avisos,
                $presente === null
                    ? "id {$id} sin descripción: no vino ni <" . self::CAMPO_DESCRIPCION_EJEMPLO . '> ni <'
                        . self::CAMPO_DESCRIPCION_ESQUEMA . '> (se guarda igual).'
                    : "id {$id}: <{$presente}> vino vacío (se guarda igual)."
            );
        }

        return new ParametroPadron100($id, $descripcion, $this->atributos($lista, $id, $avisos), $campo);
    }

    /**
     * @param  array<int, string>  $avisos
     * @return array<string, string>
     */
    private function atributos(DOMElement $lista, string $id, array &$avisos): array
    {
        $atributos = [];

        foreach ($this->hijos($lista, 'attributeList') as $atributo) {
            $nombre = $this->texto($this->hijo($atributo, 'name'));

            if ($nombre === '') {
                $this->avisar($avisos, "id {$id}: attributeList sin <name>, se ignora.");

                continue;
            }

            $atributos[$nombre] = $this->texto($this->hijo($atributo, 'value'));
        }

        // Orden estable: el mismo contenido tiene que dar el mismo JSON en cada corrida,
        // si no la comparación contra lo guardado ve cambios donde no los hay.
        ksort($atributos);

        return $atributos;
    }

    /**
     * @return array<string, string>
     */
    private function metadata(DOMElement $retorno): array
    {
        $metadata = $this->hijo($retorno, 'metadata');

        if ($metadata === null) {
            return [];
        }

        return array_filter([
            'fechaHora' => $this->texto($this->hijo($metadata, 'fechaHora')),
            'servidor' => $this->texto($this->hijo($metadata, 'servidor')),
        ], static function (string $valor): bool {
            return $valor !== '';
        });
    }

    private function documento(string $xml, string $coleccionSolicitada): DOMDocument
    {
        if (trim($xml) === '') {
            throw new Padron100Exception("ARCA devolvió una respuesta vacía para {$coleccionSolicitada}.");
        }

        $documento = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $cargado = $documento->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);
        $errores = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        if ($cargado === false) {
            $detalle = isset($errores[0]) ? trim($errores[0]->message) : 'sin detalle';

            throw new Padron100Exception(
                "La respuesta de ARCA para {$coleccionSolicitada} no es un XML válido ({$detalle})."
            );
        }

        return $documento;
    }

    private function cortarSiEsFault(DOMDocument $documento, string $coleccionSolicitada): void
    {
        $xpath = new DOMXPath($documento);

        if ($xpath->query('//*[local-name()="Fault"]')->length === 0) {
            return;
        }

        $mensaje = $this->texto($xpath->query('//*[local-name()="faultstring"]')->item(0));

        if ($mensaje === '') {
            $mensaje = $this->texto($xpath->query('//*[local-name()="Reason"]//*[local-name()="Text"]')->item(0));
        }

        if ($mensaje === '') {
            $mensaje = $this->texto($xpath->query('//*[local-name()="faultcode"]')->item(0));
        }

        throw new Padron100Exception(
            "ARCA rechazó la consulta de {$coleccionSolicitada}: " . ($mensaje !== '' ? $mensaje : 'SOAP Fault sin detalle')
        );
    }

    private function buscar(DOMDocument $documento, string $nombreLocal): ?DOMElement
    {
        $nodo = (new DOMXPath($documento))->query('//*[local-name()="' . $nombreLocal . '"]')->item(0);

        return $nodo instanceof DOMElement ? $nodo : null;
    }

    private function hijo(DOMNode $contexto, string $nombreLocal): ?DOMElement
    {
        foreach ($contexto->childNodes as $nodo) {
            if ($nodo instanceof DOMElement && $nodo->localName === $nombreLocal) {
                return $nodo;
            }
        }

        return null;
    }

    /**
     * @return array<int, DOMElement>
     */
    private function hijos(DOMNode $contexto, string $nombreLocal): array
    {
        $hijos = [];

        foreach ($contexto->childNodes as $nodo) {
            if ($nodo instanceof DOMElement && $nodo->localName === $nombreLocal) {
                $hijos[] = $nodo;
            }
        }

        return $hijos;
    }

    private function texto(?DOMNode $nodo): string
    {
        return $nodo === null ? '' : trim($nodo->textContent);
    }

    /**
     * @param  array<int, string>  $avisos
     */
    private function avisar(array &$avisos, string $mensaje): void
    {
        if (count($avisos) < self::MAX_AVISOS) {
            $avisos[] = $mensaje;
        } elseif (count($avisos) === self::MAX_AVISOS) {
            $avisos[] = 'Se omitieron avisos repetidos del mismo tipo (tope de ' . self::MAX_AVISOS . ').';
        }
    }
}
