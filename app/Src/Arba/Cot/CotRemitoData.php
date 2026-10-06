<?php

namespace App\Src\Arba\Cot;

/**
 * Un remito: el registro 02 del archivo TXT, con los largos y el orden exactos
 * del diseño de ARBA.
 *
 * El orden de las propiedades es el orden de los campos en el archivo. Está
 * escrito así a propósito: cuando ARBA rechaza un archivo el error viene sin
 * número de línea, y tener el DTO espejado contra el instructivo es lo que
 * permite ubicar el campo culpable sin contar pipes a mano.
 *
 * Contrato de uso: primero se valida (CotValidator) y después se arma el archivo
 * (CotFileBuilder). El builder asume datos válidos y no vuelve a validar.
 */
final class CotRemitoData
{
    public const GENERADOR_EMISOR = 'E';

    public const GENERADOR_DESTINATARIO = 'D';

    public const TIPOS_RECORRIDO = ['U', 'R', 'M', ''];

    public const TIPOS_DOCUMENTO = ['DNI', 'LC', 'LE', 'PAS', 'CI'];

    public const COMPLE_VALIDOS = ['', 'S/N', '1/2', '1/4', 'BIS'];

    public function __construct(
        public string $codigoUnico = '',
        public \DateTimeInterface|string $fechaEmision = '',
        public \DateTimeInterface|string $fechaSalidaTransporte = '',
        public string $horaSalidaTransporte = '',
        public string $sujetoGenerador = self::GENERADOR_EMISOR,
        public bool $destinatarioConsumidorFinal = false,
        public string $destinatarioTipoDocumento = '',
        public string $destinatarioDocumento = '',
        public string $destinatarioCuit = '',
        public string $destinatarioRazonSocial = '',
        public bool $destinatarioTenedor = false,
        public CotAddressData $destino = new CotAddressData(),
        public string $propioDestinoDomicilioCodigo = '',
        public bool $entregaDomicilioOrigen = true,
        public string $origenCuit = '',
        public string $origenRazonSocial = '',
        public bool $emisorTenedor = false,
        public CotAddressData $origen = new CotAddressData(),
        public string $transportistaCuit = '',
        public string $tipoRecorrido = '',
        public string $recorridoLocalidad = '',
        public string $recorridoCalle = '',
        public string $recorridoRuta = '',
        public string $patenteVehiculo = '',
        public string $patenteAcoplado = '',
        public bool $productoNoTermDev = false,
        public float|string $importe = 0,
        /** @var CotItemData[] */
        public array $items = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $items = [];

        foreach ($data['items'] ?? [] as $item) {
            $items[] = $item instanceof CotItemData ? $item : CotItemData::fromArray((array) $item);
        }

        return new self(
            codigoUnico: trim((string) ($data['codigo_unico'] ?? '')),
            fechaEmision: self::fecha($data['fecha_emision'] ?? ''),
            fechaSalidaTransporte: self::fecha($data['fecha_salida_transporte'] ?? ''),
            horaSalidaTransporte: trim((string) ($data['hora_salida_transporte'] ?? '')),
            sujetoGenerador: strtoupper(trim((string) ($data['sujeto_generador'] ?? self::GENERADOR_EMISOR))),
            destinatarioConsumidorFinal: (bool) ($data['destinatario_consumidor_final'] ?? false),
            destinatarioTipoDocumento: strtoupper(trim((string) ($data['destinatario_tipo_documento'] ?? ''))),
            destinatarioDocumento: trim((string) ($data['destinatario_documento'] ?? '')),
            destinatarioCuit: preg_replace('/\D/', '', (string) ($data['destinatario_cuit'] ?? '')),
            destinatarioRazonSocial: trim((string) ($data['destinatario_razon_social'] ?? '')),
            destinatarioTenedor: (bool) ($data['destinatario_tenedor'] ?? false),
            destino: CotAddressData::fromArray((array) ($data['destino'] ?? [])),
            propioDestinoDomicilioCodigo: trim((string) ($data['propio_destino_domicilio_codigo'] ?? '')),
            entregaDomicilioOrigen: (bool) ($data['entrega_domicilio_origen'] ?? true),
            origenCuit: preg_replace('/\D/', '', (string) ($data['origen_cuit'] ?? '')),
            origenRazonSocial: trim((string) ($data['origen_razon_social'] ?? '')),
            emisorTenedor: (bool) ($data['emisor_tenedor'] ?? false),
            origen: CotAddressData::fromArray((array) ($data['origen'] ?? [])),
            transportistaCuit: preg_replace('/\D/', '', (string) ($data['transportista_cuit'] ?? '')),
            tipoRecorrido: strtoupper(trim((string) ($data['tipo_recorrido'] ?? ''))),
            recorridoLocalidad: trim((string) ($data['recorrido_localidad'] ?? '')),
            recorridoCalle: trim((string) ($data['recorrido_calle'] ?? '')),
            recorridoRuta: trim((string) ($data['recorrido_ruta'] ?? '')),
            patenteVehiculo: strtoupper(trim((string) ($data['patente_vehiculo'] ?? ''))),
            patenteAcoplado: strtoupper(trim((string) ($data['patente_acoplado'] ?? ''))),
            productoNoTermDev: (bool) ($data['producto_no_term_dev'] ?? false),
            importe: $data['importe'] ?? 0,
            items: $items,
        );
    }

    /**
     * Fecha de emisión normalizada a AAAAMMDD, o null si no es válida.
     * La validez la reporta CotValidator; acá no se lanza excepción para que el
     * error se pueda mostrar junto al resto en lugar de cortar el armado.
     */
    public function fechaEmisionNormalizada(): ?string
    {
        return $this->normalizarFecha($this->fechaEmision);
    }

    public function fechaSalidaNormalizada(): ?string
    {
        return $this->normalizarFecha($this->fechaSalidaTransporte);
    }

    /**
     * Los 43 valores del registro 02, sin el tipo de registro.
     *
     * @return string[]
     */
    public function toRegistro(): array
    {
        return [
            (string) $this->fechaEmisionNormalizada(),
            $this->codigoUnico,
            (string) $this->fechaSalidaNormalizada(),
            $this->horaSalidaTransporte,
            $this->sujetoGenerador,
            self::binario($this->destinatarioConsumidorFinal),
            $this->destinatarioTipoDocumento,
            $this->destinatarioDocumento,
            $this->destinatarioCuit,
            $this->destinatarioRazonSocial,
            self::binario($this->destinatarioTenedor),
            ...$this->destino->toRegistro(),
            $this->propioDestinoDomicilioCodigo,
            $this->entregaDomicilioOrigen ? 'SI' : 'NO',
            $this->origenCuit,
            $this->origenRazonSocial,
            self::binario($this->emisorTenedor),
            ...$this->origen->toRegistro(),
            $this->transportistaCuit,
            $this->tipoRecorrido,
            $this->recorridoLocalidad,
            $this->recorridoCalle,
            $this->recorridoRuta,
            $this->patenteVehiculo,
            $this->patenteAcoplado,
            self::binario($this->productoNoTermDev),
            CotNumber::decimal($this->importe),
        ];
    }

    public static function binario(bool $value): string
    {
        return $value ? '1' : '0';
    }

    /**
     * Delegado en CotFileName a propósito: el DTO y el nombre del archivo no
     * pueden interpretar la misma fecha de dos maneras distintas.
     */
    private function normalizarFecha(\DateTimeInterface|string $fecha): ?string
    {
        if ($fecha === '') {
            return null;
        }

        try {
            return CotFileName::normalizeFecha($fecha);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * Los modelos devuelven Carbon; si se lo castea a string queda
     * "Y-m-d H:i:s". Se conserva el objeto para no depender de ese formato.
     */
    private static function fecha(mixed $valor): \DateTimeInterface|string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor;
        }

        return trim((string) $valor);
    }
}
