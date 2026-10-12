<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

/**
 * Un registro de la tabla de parámetros (un <parameterList> de la respuesta).
 *
 * El "id" es la clave del parámetro dentro de su colección: es lo que el manual define
 * como valor válido para el campo al que corresponde la colección (por ejemplo, en
 * SUPA.TIPO_DOCUMENTO el id es el código que va en DocTipo de la factura).
 */
final class ParametroPadron100
{
    /** @var string */
    private $id;

    /** @var string|null */
    private $descripcion;

    /** @var array<string, string> */
    private $atributos;

    /** @var string|null */
    private $campoDescripcion;

    /**
     * @param  array<string, string>  $atributos
     * @param  string|null  $campoDescripcion  Nombre con el que vino la descripción
     *                                         ("decription" o "description"), o null
     *                                         si no vino con ninguno de los dos.
     */
    public function __construct(string $id, ?string $descripcion, array $atributos = [], ?string $campoDescripcion = null)
    {
        $this->id = $id;
        $this->descripcion = $descripcion;
        $this->atributos = $atributos;
        $this->campoDescripcion = $campoDescripcion;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function descripcion(): ?string
    {
        return $this->descripcion;
    }

    /**
     * @return array<string, string>
     */
    public function atributos(): array
    {
        return $this->atributos;
    }

    public function atributo(string $nombre): ?string
    {
        return isset($this->atributos[$nombre]) ? $this->atributos[$nombre] : null;
    }

    /**
     * Con qué nombre llegó la descripción. Sirve para que el informe diga cuál de las
     * dos variantes del manual está usando ARCA hoy, en vez de suponerlo.
     */
    public function campoDescripcion(): ?string
    {
        return $this->campoDescripcion;
    }

    public function tieneDescripcion(): bool
    {
        return $this->campoDescripcion !== null;
    }
}
