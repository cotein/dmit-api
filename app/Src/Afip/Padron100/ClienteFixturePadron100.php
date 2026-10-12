<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

use App\Src\Afip\Padron100\Excepciones\Padron100Exception;

/**
 * Cliente de prueba: devuelve respuestas guardadas en disco.
 *
 * Por qué: el servicio no se puede llamar sin certificado (y el WSDL no es público), así
 * que la sincronización y el mapeo se prueban con archivos XML con la forma de una
 * respuesta real. Cada archivo se llama como la colección que se pide
 * (<collectionName>.xml) y su contenido es la respuesta completa (sobre SOAP incluido).
 *
 * Ojo: acá el archivo lo elige el nombre PEDIDO, pero el <name> que va adentro es el que
 * manda: si no coinciden, el parseo lo detecta y lo informa (es el caso del ejemplo del
 * manual, que pide SUPA.TIPO_TELEFONO y devuelve SUPA.E_PROVINCIA).
 */
final class ClienteFixturePadron100 implements ClientePadron100
{
    /** @var string */
    private $directorio;

    /** @var ParserPadron100 */
    private $parser;

    public function __construct(string $directorio, ?ParserPadron100 $parser = null)
    {
        $this->directorio = rtrim($directorio, DIRECTORY_SEPARATOR);
        $this->parser = $parser === null ? new ParserPadron100() : $parser;
    }

    public function traerColeccion(string $collectionName): ColeccionPadron100
    {
        $archivo = $this->archivoDe($collectionName);

        $xml = file_get_contents($archivo);

        if ($xml === false) {
            throw new Padron100Exception("No se pudo leer el fixture {$archivo}.");
        }

        return $this->parser->parsear($xml, $collectionName);
    }

    /**
     * @return array<int, string> Colecciones que tienen fixture disponible
     */
    public function disponibles(): array
    {
        $archivos = glob($this->directorio . DIRECTORY_SEPARATOR . '*.xml');

        if ($archivos === false) {
            return [];
        }

        return array_map(static function (string $archivo): string {
            return basename($archivo, '.xml');
        }, $archivos);
    }

    private function archivoDe(string $collectionName): string
    {
        $archivo = $this->directorio . DIRECTORY_SEPARATOR . $collectionName . '.xml';

        if (is_file($archivo)) {
            return $archivo;
        }

        $disponibles = $this->disponibles();

        throw new Padron100Exception(
            "No hay fixture para {$collectionName} en {$this->directorio}."
            . ($disponibles === [] ? ' El directorio no tiene ningún .xml.' : ' Hay: ' . implode(', ', $disponibles))
        );
    }
}
