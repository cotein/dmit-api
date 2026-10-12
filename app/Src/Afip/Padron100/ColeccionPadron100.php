<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

/**
 * Respuesta completa de getParameterCollectionByName, ya interpretada.
 *
 * Guarda POR SEPARADO la colección que se pidió y la que ARCA dice haber devuelto: el
 * ejemplo del manual pide SUPA.TIPO_TELEFONO y responde con SUPA.E_PROVINCIA, así que
 * dar por sentado que son la misma cosa es el camino directo a cargar un catálogo con
 * los datos de otro. nombreCoincide() es lo que permite avisar en vez de suponer.
 */
final class ColeccionPadron100
{
    /** @var string */
    private $solicitada;

    /** @var string */
    private $informada;

    /** @var array<int, ParametroPadron100> */
    private $parametros;

    /** @var array<string, string> */
    private $metadata;

    /** @var array<int, string> */
    private $avisos;

    /**
     * @param  array<int, ParametroPadron100>  $parametros
     * @param  array<string, string>  $metadata  fechaHora y servidor que informa ARCA
     * @param  array<int, string>  $avisos  Cosas que el parseo no pudo interpretar y
     *                                      que hay que mostrar, nunca tragarse.
     */
    public function __construct(
        string $solicitada,
        string $informada,
        array $parametros,
        array $metadata = [],
        array $avisos = []
    ) {
        $this->solicitada = $solicitada;
        $this->informada = $informada;
        $this->parametros = $parametros;
        $this->metadata = $metadata;
        $this->avisos = $avisos;
    }

    public function solicitada(): string
    {
        return $this->solicitada;
    }

    public function informada(): string
    {
        return $this->informada;
    }

    /**
     * @return array<int, ParametroPadron100>
     */
    public function parametros(): array
    {
        return $this->parametros;
    }

    public function cantidad(): int
    {
        return count($this->parametros);
    }

    /**
     * @return array<string, string>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<int, string>
     */
    public function avisos(): array
    {
        return $this->avisos;
    }

    public function nombreCoincide(): bool
    {
        return $this->informada !== '' && $this->informada === $this->solicitada;
    }
}
