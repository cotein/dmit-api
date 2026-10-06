<?php

namespace App\Src\Arba\Cot;

/**
 * Un domicilio (de origen o de destino) con los largos exactos del registro 02
 * del archivo TXT.
 *
 * No se reutiliza App\Models\Address a propósito: la tabla addresses no tiene
 * piso, departamento, barrio ni el campo "comple" con los valores S/N, 1/2,
 * 1/4, BIS que ARBA exige, y su número es un entero mientras que el TXT admite
 * "S/N" vía ese mismo campo.
 */
final class CotAddressData
{
    public function __construct(
        public string $calle = '',
        public string $numero = '',
        public string $comple = '',
        public string $piso = '',
        public string $dto = '',
        public string $barrio = '',
        public string $codigoPostal = '',
        public string $localidad = '',
        public string $provincia = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            calle: trim((string) ($data['calle'] ?? '')),
            numero: trim((string) ($data['numero'] ?? '')),
            comple: trim((string) ($data['comple'] ?? '')),
            piso: trim((string) ($data['piso'] ?? '')),
            dto: trim((string) ($data['dto'] ?? '')),
            barrio: trim((string) ($data['barrio'] ?? '')),
            codigoPostal: trim((string) ($data['codigo_postal'] ?? '')),
            localidad: trim((string) ($data['localidad'] ?? '')),
            provincia: strtoupper(trim((string) ($data['provincia'] ?? ''))),
        );
    }

    public function toArray(): array
    {
        return [
            'calle' => $this->calle,
            'numero' => $this->numero,
            'comple' => $this->comple,
            'piso' => $this->piso,
            'dto' => $this->dto,
            'barrio' => $this->barrio,
            'codigo_postal' => $this->codigoPostal,
            'localidad' => $this->localidad,
            'provincia' => $this->provincia,
        ];
    }

    /**
     * Los 9 valores del bloque de domicilio, en el orden del registro 02.
     *
     * @return string[]
     */
    public function toRegistro(): array
    {
        return [
            $this->calle,
            $this->numero,
            $this->comple,
            $this->piso,
            $this->dto,
            $this->barrio,
            $this->codigoPostal,
            $this->localidad,
            $this->provincia,
        ];
    }
}
