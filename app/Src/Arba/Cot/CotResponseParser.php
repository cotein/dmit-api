<?php

namespace App\Src\Arba\Cot;

/**
 * Lee la respuesta XML del servicio de Transporte de Bienes.
 *
 * El instructivo documenta dos respuestas:
 *
 *   <TBCOMPROBANTE>
 *     <cuitEmpresa>30111111118</cuitEmpresa>
 *     <numeroComprobante>123456789</numeroComprobante>
 *     <nombreArchivo>TB_...</nombreArchivo>
 *     <codigoIntegridad>...</codigoIntegridad>
 *     <validacionesRemitos class="list">
 *       <remito>
 *         <numeroUnico>91 R000100000001</numeroUnico>
 *         <procesado>SI</procesado>
 *       </remito>
 *       <remito>
 *         <numeroUnico>...</numeroUnico>
 *         <procesado>NO</procesado>
 *         <errores class="list"><error><codigo>21</codigo><descripcion>...</descripcion></error></errores>
 *       </remito>
 *     </validacionesRemitos>
 *   </TBCOMPROBANTE>
 *
 *   <TBError>
 *     <tipoError>DATO</tipoError>
 *     <codigoError>02</codigoError>
 *     <mensajeError>...</mensajeError>
 *   </TBError>
 *
 * Dos cosas que hace de más a propósito:
 *
 *  - Nunca tira excepción. Una respuesta ilegible tiene que llegar al usuario
 *    como un mensaje entendible y quedar guardada cruda, no reventar en medio
 *    del envío dejando el archivo sin registrar.
 *  - Si la raíz no es ninguna de las dos, busca la respuesta un nivel más
 *    abajo. Un día ARBA puede envolverla (un sobre SOAP, un HTML de su proxy) y
 *    es más barato tolerarlo que descubrirlo en producción.
 */
final class CotResponseParser
{
    /**
     * Cuántos caracteres del cuerpo se guardan en el mensaje de error. El cuerpo
     * completo igual queda en cot_archivos.respuesta.
     */
    private const LARGO_SNIPPET = 300;

    public function parse(string $cuerpo): CotResultado
    {
        $cuerpo = trim($cuerpo);

        if ($cuerpo === '') {
            return CotResultado::error('', 'ARBA devolvió una respuesta vacía.');
        }

        $xml = $this->cargar($cuerpo);

        if ($xml === null) {
            return CotResultado::error(
                '',
                'La respuesta de ARBA no es un XML válido: ' . $this->snippet($cuerpo),
                'ERROR INESPERADO',
                $cuerpo
            );
        }

        $raiz = strtoupper($xml->getName());

        if ($raiz === 'TBCOMPROBANTE') {
            return $this->parseComprobante($xml, $cuerpo);
        }

        if ($raiz === 'TBERROR') {
            return $this->parseError($xml, $cuerpo);
        }

        // La respuesta puede venir envuelta (un sobre SOAP, un proxy). La
        // búsqueda por XPath ignora los namespaces, así que encuentra el
        // comprobante aunque venga como <soap:Body><TBCOMPROBANTE>.
        foreach (['TBCOMPROBANTE' => 'parseComprobante', 'TBError' => 'parseError'] as $nombre => $metodo) {
            $encontrados = @$xml->xpath('//' . $nombre);

            if (! empty($encontrados)) {
                return $this->{$metodo}($encontrados[0], $cuerpo);
            }
        }

        return CotResultado::error(
            '',
            'ARBA devolvió una respuesta con una estructura inesperada (raíz <' . $xml->getName() . '>): ' . $this->snippet($cuerpo),
            'ERROR INESPERADO',
            $cuerpo
        );
    }

    private function parseComprobante(\SimpleXMLElement $xml, string $cuerpo): CotResultado
    {
        $remitos = [];

        foreach ($this->hijos($xml->validacionesRemitos, 'remito') as $remito) {
            $numeroUnico = $this->texto($remito->numeroUnico);

            if ($numeroUnico === null) {
                continue;
            }

            $remitos[$numeroUnico] = new CotResultadoRemito(
                numeroUnico: $numeroUnico,
                procesado: $this->esSi($remito->procesado),
                errores: $this->parseErrores($remito),
                // El instructivo NO documenta un número de COT por remito: la
                // respuesta sólo trae el número de comprobante a nivel archivo.
                // Si alguna vez viene, se captura acá en lugar de perderlo.
                numeroCot: $this->texto($remito->numeroCot) ?? $this->texto($remito->nroRemito),
            );
        }

        return new CotResultado(
            exitoso: true,
            numeroComprobante: $this->texto($xml->numeroComprobante),
            codigoIntegridad: $this->texto($xml->codigoIntegridad),
            nombreArchivo: $this->texto($xml->nombreArchivo),
            cuitEmpresa: $this->texto($xml->cuitEmpresa),
            remitos: $remitos,
            cuerpoCrudo: $cuerpo,
        );
    }

    private function parseError(\SimpleXMLElement $xml, string $cuerpo): CotResultado
    {
        $codigo = $this->texto($xml->codigoError) ?? '';
        $tipo = $this->texto($xml->tipoError);
        $mensaje = $this->texto($xml->mensajeError);

        // El mensaje de ARBA suele alcanzar; el catálogo local es el respaldo
        // para traducir un código que llegue sin descripción.
        if ($mensaje === null && $codigo !== '') {
            $mensaje = CotErrors::descripcion($codigo);
        }

        return CotResultado::error(
            codigo: $codigo,
            mensaje: $mensaje ?? 'ARBA rechazó la transacción sin informar el motivo.',
            tipo: $tipo,
            cuerpo: $cuerpo
        );
    }

    /**
     * @return array<int, array{codigo: string, descripcion: string}>
     */
    private function parseErrores(\SimpleXMLElement $remito): array
    {
        $errores = [];

        foreach ($this->hijos($remito->errores, 'error') as $error) {
            $codigo = $this->texto($error->codigo) ?? '';
            $descripcion = $this->texto($error->descripcion) ?? CotErrors::descripcion($codigo);

            $errores[] = [
                'codigo' => $codigo,
                'descripcion' => $descripcion,
            ];
        }

        return $errores;
    }

    /**
     * Devuelve los hijos de un bloque como arreglo.
     *
     * No se puede hacer `foreach ($xml->bloque->hijo as ...)` directo: cuando el
     * bloque no tiene hijos, SimpleXML emite el warning "foreach() argument must
     * be of type array|object, null given" y Laravel lo convierte en excepción.
     * O sea, un remito sin errores —el caso más común— rompería el parseo.
     *
     * @return \SimpleXMLElement[]
     */
    private function hijos(mixed $nodo, string $nombre): array
    {
        if (! $nodo instanceof \SimpleXMLElement) {
            return [];
        }

        $hijos = $nodo->{$nombre};

        if (! $hijos instanceof \SimpleXMLElement || count($hijos) === 0) {
            return [];
        }

        $lista = [];

        foreach ($hijos as $hijo) {
            $lista[] = $hijo;
        }

        return $lista;
    }

    private function cargar(string $cuerpo): ?\SimpleXMLElement
    {
        $anterior = libxml_use_internal_errors(true);

        // LIBXML_NONET corta cualquier referencia externa del XML: la respuesta
        // viene de afuera y no tiene por qué disparar una conexión.
        $xml = simplexml_load_string($cuerpo, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        return $xml === false ? null : $xml;
    }

    private function texto(mixed $nodo): ?string
    {
        if ($nodo === null) {
            return null;
        }

        $valor = trim((string) $nodo);

        return $valor === '' ? null : $valor;
    }

    /**
     * Los booleanos del instructivo vienen como SI/NO en un campo de un carácter.
     */
    private function esSi(mixed $nodo): bool
    {
        $valor = strtoupper((string) $this->texto($nodo));

        return in_array($valor, ['SI', 'S', '1', 'TRUE'], true);
    }

    private function snippet(string $cuerpo): string
    {
        // Se colapsan los espacios para que un XML de una sola línea no ocupe
        // media pantalla en el mensaje de error.
        $plano = trim(preg_replace('/\s+/', ' ', $cuerpo) ?? $cuerpo);

        if (mb_strlen($plano) <= self::LARGO_SNIPPET) {
            return $plano;
        }

        return mb_substr($plano, 0, self::LARGO_SNIPPET) . '...';
    }
}
