<?php

namespace App\Src\Arba\Cot;

/**
 * Valida un remito contra las reglas del diseño de archivo TXT de ARBA antes de
 * presentarlo.
 *
 * Para qué sirve teniendo el validador de ARBA del otro lado: cuando el archivo
 * se rechaza, ARBA devuelve el error por remito pero sin número de línea, y un
 * archivo con diez remitos obliga a revisarlos a mano. Acá los errores se
 * detectan antes de gastar el envío y vienen atados al campo que los produjo.
 *
 * Devuelve un mapa campo => mensaje. Las claves son los nombres de los campos
 * del TXT en snake_case (para poder señalarlos en un formulario) y, cuando la
 * regla cruza varios campos, una clave propia: 'provincias', 'patentes',
 * 'items', 'importe'.
 *
 * Lo que NO se puede validar acá: el error 60 ("HORA_SALIDA_TRANSPORTE supera
 * los 30 minutos desde la partida del transporte"). Depende del momento real de
 * la partida, así que lo evalúa ARBA al recibir el archivo. Ver
 * MINUTOS_MAXIMOS_DESDE_PARTIDA.
 */
final class CotValidator
{
    /**
     * El nomenclador se inyecta para que esta clase no dependa de la base de
     * datos: en producción va NomencladorArba y en los tests
     * NomencladorEnMemoria. Sin esto, cada test del generador del TXT tendría
     * que levantar MySQL.
     */
    public function __construct(private CotNomenclador $nomenclador)
    {
    }

    /**
     * El instructivo exige tipo y número de documento, o CUIT, y razón social,
     * cuando el destinatario es consumidor final y el importe llega a este piso.
     */
    public const IMPORTE_CONSUMIDOR_FINAL_MINIMO = 5000;

    public const PROVINCIA_BUENOS_AIRES = 'B';

    public const DIAS_ANTICIPACION_MAXIMA = 30;

    public const DIAS_ANTICIPACION_MINIMA = -1;

    /**
     * Ventana que aplica ARBA sobre la hora de salida (error 60). No se valida
     * acá porque depende del momento de la partida; queda documentada para que
     * la UI pueda avisar antes de presentar el archivo.
     */
    public const MINUTOS_MAXIMOS_DESDE_PARTIDA = 30;

    /**
     * Largo máximo de cada campo del registro 02 y del registro 03.
     */
    public const LARGOS = [        'codigo_unico' => 16,
        'destinatario_tipo_documento' => 3,
        'destinatario_documento' => 11,
        'destinatario_cuit' => 11,
        'destinatario_razon_social' => 50,
        'propio_destino_domicilio_codigo' => 20,
        'origen_cuit' => 11,
        'origen_razon_social' => 50,
        'transportista_cuit' => 11,
        'recorrido_localidad' => 50,
        'recorrido_calle' => 40,
        'recorrido_ruta' => 40,
        'patente_vehiculo' => 7,
        'patente_acoplado' => 7,
    ];

    public const LARGOS_DOMICILIO = [
        'calle' => 40,
        'numero' => 5,
        'comple' => 5,
        'piso' => 3,
        'dto' => 4,
        'barrio' => 30,
        'codigo_postal' => 8,
        'localidad' => 50,
        'provincia' => 1,
    ];

    public const LARGOS_ITEM = [
        'codigo_unico_producto' => 6,
        'arba_codigo_unidad_medida' => 1,
        'propio_codigo_producto' => 25,
        'propio_descripcion_producto' => 40,
        'propio_descripcion_unidad_medida' => 20,
    ];

    /**
     * @return array<string, string> vacío cuando el remito es presentable
     */
    public function validate(string $cuitEmpresa, CotRemitoData $remito, ?\DateTimeImmutable $hoy = null): array
    {
        $hoy = $hoy ?? new \DateTimeImmutable('today');
        $empresa = preg_replace('/\D/', '', $cuitEmpresa);
        $errores = [];

        $this->validarSujetoGenerador($remito, $empresa, $errores);
        $this->validarCodigoUnico($remito, $errores);
        $this->validarFechas($remito, $hoy, $errores);
        $this->validarDestinatario($remito, $errores);
        $this->validarDomicilios($remito, $errores);
        $this->validarProvincias($remito, $errores);
        $this->validarTransporte($remito, $empresa, $errores);
        $this->validarImporte($remito, $errores);
        $this->validarItems($remito, $errores);
        $this->validarLargos($remito, $errores);

        return $errores;
    }

    public function isValid(string $cuitEmpresa, CotRemitoData $remito, ?\DateTimeImmutable $hoy = null): bool
    {
        return $this->validate($cuitEmpresa, $remito, $hoy) === [];
    }

    /**
     * Rutina estándar de dígito verificador de CUIT (módulo 11).
     */
    public static function isValidCuit(?string $cuit): bool
    {
        $cuit = preg_replace('/\D/', '', (string) $cuit);

        if (strlen($cuit) !== 11) {
            return false;
        }

        $multiplicadores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $acumulado = 0;

        for ($i = 0; $i < 10; $i++) {
            $acumulado += (int) $cuit[$i] * $multiplicadores[$i];
        }

        $verificador = 11 - ($acumulado % 11);

        if ($verificador === 11) {
            $verificador = 0;
        } elseif ($verificador === 10) {
            $verificador = 9;
        }

        return $verificador === (int) $cuit[10];
    }

    /**
     * Patente de vehículo: 3 letras + 3 números (Mercosur) o 2 letras +
     * 3 números + 2 letras (formato anterior).
     */
    public static function isValidPatente(?string $patente): bool
    {
        $patente = strtoupper(trim((string) $patente));

        return (bool) preg_match('/^[A-Z]{3}\d{3}$/', $patente)
            || (bool) preg_match('/^[A-Z]{2}\d{3}[A-Z]{2}$/', $patente);
    }

    private function validarSujetoGenerador(CotRemitoData $remito, string $empresa, array &$errores): void
    {
        if (! in_array($remito->sujetoGenerador, [CotRemitoData::GENERADOR_EMISOR, CotRemitoData::GENERADOR_DESTINATARIO], true)) {
            $errores['sujeto_generador'] = 'El sujeto generador debe ser "E" (emisor) o "D" (destinatario).';

            return;
        }

        // El CUIT del generador tiene que ser el CUIT de la empresa que presenta
        // el archivo: si el generador es el emisor, coincide con el origen; si es
        // el destinatario, con el destinatario.
        $esperado = $remito->sujetoGenerador === CotRemitoData::GENERADOR_EMISOR
            ? 'origen_cuit'
            : 'destinatario_cuit';

        $informado = $remito->sujetoGenerador === CotRemitoData::GENERADOR_EMISOR
            ? $remito->origenCuit
            : $remito->destinatarioCuit;

        if ($informado !== $empresa) {
            $etiqueta = $remito->sujetoGenerador === CotRemitoData::GENERADOR_EMISOR
                ? 'El CUIT de origen'
                : 'El CUIT del destinatario';

            $errores[$esperado] = $etiqueta . " debe coincidir con el CUIT de la empresa que presenta el archivo ({$empresa}) cuando el sujeto generador es \"{$remito->sujetoGenerador}\".";
        }
    }

    private function validarCodigoUnico(CotRemitoData $remito, array &$errores): void
    {
        $codigo = $remito->codigoUnico;

        if ($codigo === '') {
            $errores['codigo_unico'] = 'El código único es obligatorio.';

            return;
        }

        if (! preg_match('/^.{3}.{5}(\d{8})$/', $codigo, $matches)) {
            $errores['codigo_unico'] = 'El código único debe tener 16 posiciones: 3 de código AFIP, 5 de prefijo y 8 de número.';

            return;
        }

        if ((int) $matches[1] === 0) {
            $errores['codigo_unico'] = 'El número del comprobante dentro del código único debe ser mayor a cero.';
        }
    }

    private function validarFechas(CotRemitoData $remito, \DateTimeImmutable $hoy, array &$errores): void
    {
        $emision = $this->parseFecha($remito->fechaEmisionNormalizada());

        if ($emision === null) {
            $errores['fecha_emision'] = 'La fecha de emisión es inválida o inexistente (formato AAAAMMDD).';
        }

        $salida = $this->parseFecha($remito->fechaSalidaNormalizada());

        if ($salida === null) {
            $errores['fecha_salida_transporte'] = 'La fecha de salida del transporte es inválida o inexistente (formato AAAAMMDD).';
        }

        if ($emision !== null && $salida !== null && $salida < $emision) {
            $errores['fecha_salida_transporte'] = 'La fecha de salida del transporte no puede ser anterior a la fecha de emisión.';
        }

        if ($salida !== null) {
            $minima = $hoy->modify('-' . abs(self::DIAS_ANTICIPACION_MINIMA) . ' day');
            $maxima = $hoy->modify('+' . self::DIAS_ANTICIPACION_MAXIMA . ' day');

            if ($salida < $minima) {
                $errores['fecha_salida_transporte'] = 'La fecha de salida no puede ser anterior a ayer.';
            } elseif ($salida > $maxima) {
                $errores['fecha_salida_transporte'] = 'La fecha de salida no puede superar los ' . self::DIAS_ANTICIPACION_MAXIMA . ' días desde hoy.';
            }
        }

        if ($remito->horaSalidaTransporte !== '' && ! $this->isValidHora($remito->horaSalidaTransporte)) {
            $errores['hora_salida_transporte'] = 'La hora de salida debe tener 4 dígitos en formato HHMM.';
        }
    }

    private function validarDestinatario(CotRemitoData $remito, array &$errores): void
    {
        if ($remito->destinatarioConsumidorFinal && $remito->destinatarioTenedor) {
            $errores['destinatario_tenedor'] = 'Si el destinatario es consumidor final, no puede ser tenedor de la mercadería.';
        }

        if (! $remito->destinatarioConsumidorFinal) {
            if ($remito->destinatarioCuit === '') {
                $errores['destinatario_cuit'] = 'El CUIT del destinatario es obligatorio cuando no es consumidor final.';
            } elseif (! self::isValidCuit($remito->destinatarioCuit)) {
                $errores['destinatario_cuit'] = 'El CUIT del destinatario no supera la rutina de validación.';
            }

            if ($remito->destinatarioRazonSocial === '') {
                $errores['destinatario_razon_social'] = 'La razón social del destinatario es obligatoria cuando no es consumidor final.';
            }

            return;
        }

        // Consumidor final con importe alto: ARBA exige poder identificar a quién
        // se le entrega, porque la mercadería queda en poder de un tercero.
        $importe = (float) $remito->importe;

        if ($importe >= self::IMPORTE_CONSUMIDOR_FINAL_MINIMO) {
            $tieneDocumento = $remito->destinatarioTipoDocumento !== '' && $remito->destinatarioDocumento !== '';
            $tieneCuit = $remito->destinatarioCuit !== '';

            if (! $tieneDocumento && ! $tieneCuit) {
                $errores['destinatario_documento'] = 'Con consumidor final y un importe de $' . self::IMPORTE_CONSUMIDOR_FINAL_MINIMO . ' o más, hay que informar tipo y número de documento, o CUIT.';
            }

            if ($remito->destinatarioRazonSocial === '') {
                $errores['destinatario_razon_social'] = 'Con consumidor final y un importe de $' . self::IMPORTE_CONSUMIDOR_FINAL_MINIMO . ' o más, hay que informar la razón social (se admite "NR").';
            }
        }

        if ($remito->destinatarioTipoDocumento !== '' && ! in_array($remito->destinatarioTipoDocumento, CotRemitoData::TIPOS_DOCUMENTO, true)) {
            $errores['destinatario_tipo_documento'] = 'El tipo de documento debe ser uno de: ' . implode(', ', CotRemitoData::TIPOS_DOCUMENTO) . '.';
        }
    }

    private function validarDomicilios(CotRemitoData $remito, array &$errores): void
    {
        $this->validarDomicilio($remito->origen, 'origen', $errores);
        $this->validarDomicilio($remito->destino, 'destino', $errores);

        if ($remito->origenCuit === '') {
            $errores['origen_cuit'] = 'El CUIT de origen es obligatorio.';
        } elseif (! self::isValidCuit($remito->origenCuit)) {
            $errores['origen_cuit'] = 'El CUIT de origen no supera la rutina de validación.';
        }

        if ($remito->origenRazonSocial === '') {
            $errores['origen_razon_social'] = 'La razón social de origen es obligatoria.';
        }
    }

    private function validarDomicilio(CotAddressData $domicilio, string $bloque, array &$errores): void
    {
        $prefijo = "{$bloque}_domicilio_";

        if ($domicilio->calle === '') {
            $errores[$prefijo . 'calle'] = 'La calle es obligatoria.';
        }

        // El número se exige mayor a cero salvo que el campo "comple" sea "S/N"
        // (sin número), donde el propio instructivo admite 0 o blanco.
        $sinNumero = $domicilio->comple === 'S/N';

        if (! $sinNumero) {
            if ($domicilio->numero === '') {
                $errores[$prefijo . 'numero'] = 'El número es obligatorio (informar "S/N" en el complemento si la dirección no tiene numeración).';
            } elseif ((int) $domicilio->numero <= 0) {
                $errores[$prefijo . 'numero'] = 'El número debe ser mayor a cero.';
            }
        }

        if (! in_array($domicilio->comple, CotRemitoData::COMPLE_VALIDOS, true)) {
            $errores[$prefijo . 'comple'] = 'El complemento debe ser uno de: ' . implode(', ', ['S/N', '1/2', '1/4', 'BIS']) . ' (o quedar vacío).';
        }

        if ($domicilio->codigoPostal === '') {
            $errores[$prefijo . 'codigo_postal'] = 'El código postal es obligatorio.';
        }

        if ($domicilio->localidad === '') {
            $errores[$prefijo . 'localidad'] = 'La localidad es obligatoria.';
        }

        if ($domicilio->provincia === '') {
            $errores[$prefijo . 'provincia'] = 'La provincia es obligatoria.';
        } elseif (! CotProvincias::existe($domicilio->provincia)) {
            $errores[$prefijo . 'provincia'] = 'El código de provincia no existe en la tabla de provincias del COT de ARBA.';
        }
    }

    /**
     * Error 90: ARBA rechaza el archivo si ni el origen ni el destino son
     * Buenos Aires. Se chequea acá para no gastar el envío.
     */
    private function validarProvincias(CotRemitoData $remito, array &$errores): void
    {
        $origen = $remito->origen->provincia;
        $destino = $remito->destino->provincia;

        if ($origen === '' || $destino === '') {
            return;
        }

        if ($origen !== self::PROVINCIA_BUENOS_AIRES && $destino !== self::PROVINCIA_BUENOS_AIRES) {
            $errores['provincias'] = 'El domicilio de origen o el de destino debe estar en la provincia de Buenos Aires (código "B").';
        }
    }

    private function validarTransporte(CotRemitoData $remito, string $empresa, array &$errores): void
    {
        if ($remito->transportistaCuit === '') {
            $errores['transportista_cuit'] = 'El CUIT del transportista es obligatorio.';
        } elseif (! self::isValidCuit($remito->transportistaCuit)) {
            $errores['transportista_cuit'] = 'El CUIT del transportista no supera la rutina de validación.';
        }

        if (! in_array($remito->tipoRecorrido, CotRemitoData::TIPOS_RECORRIDO, true)) {
            $errores['tipo_recorrido'] = 'El tipo de recorrido debe ser U (urbano), R (rural), M (mixto) o quedar vacío.';
        }

        // Con flota propia (el transportista es la misma empresa) ARBA exige
        // identificar el vehículo: no hay un tercero a quien reclamarle.
        $esFlotaPropia = $remito->transportistaCuit !== '' && $remito->transportistaCuit === $empresa;

        if ($esFlotaPropia && $remito->patenteVehiculo === '') {
            $errores['patente_vehiculo'] = 'La patente del vehículo es obligatoria cuando el transportista es la propia empresa.';
        }

        if ($remito->patenteVehiculo !== '' && ! self::isValidPatente($remito->patenteVehiculo)) {
            $errores['patente_vehiculo'] = 'La patente del vehículo debe ser 3 letras y 3 números, o 2 letras, 3 números y 2 letras.';
        }

        if ($remito->patenteAcoplado !== '' && ! self::isValidPatente($remito->patenteAcoplado)) {
            $errores['patente_acoplado'] = 'La patente del acoplado debe ser 3 letras y 3 números, o 2 letras, 3 números y 2 letras.';
        }
    }

    private function validarImporte(CotRemitoData $remito, array &$errores): void
    {
        $importe = (float) $remito->importe;

        if ($importe < 0) {
            $errores['importe'] = 'El importe no puede ser negativo.';

            return;
        }

        if (! CotNumber::fits($importe, 12)) {
            $errores['importe'] = 'El importe supera los 12 enteros y 2 decimales que admite el campo.';

            return;
        }

        // El instructivo admite importe 0 o blanco cuando origen y destinatario
        // son el mismo CUIT (movimiento dentro de la misma empresa).
        $mismoTitular = $remito->origenCuit !== ''
            && $remito->origenCuit === $remito->destinatarioCuit;

        if ($importe == 0.0 && ! $mismoTitular) {
            $errores['importe'] = 'El importe es obligatorio (sólo se admite cero cuando origen y destinatario son el mismo CUIT).';
        }
    }

    private function validarItems(CotRemitoData $remito, array &$errores): void
    {
        if (count($remito->items) === 0) {
            $errores['items'] = 'El remito necesita al menos un registro de productos (registro 03).';

            return;
        }

        // Se informa el primer problema del primer producto con problemas: es
        // determinista y evita que un remito con veinte productos devuelva un
        // mensaje kilométrico en un solo campo.
        foreach ($remito->items as $indice => $item) {
            $problema = $this->problemaDelItem($item, $indice + 1);

            if ($problema !== null) {
                $errores['items'] = $problema;

                return;
            }
        }
    }

    private function problemaDelItem(CotItemData $item, int $posicion): ?string
    {
        if (! preg_match('/^\d{6}$/', $item->codigoUnicoProducto)) {
            return "Producto {$posicion}: el código único de producto debe ser de 6 dígitos del nomenclador COT.";
        }

        // El código del registro 03 tiene que existir en el nomenclador de ARBA
        // en el nivel VARIEDAD, que es el único de seis dígitos: ARBA lo rechaza
        // con el error 39 si no lo encuentra.
        if (! $this->nomenclador->existeProducto($item->codigoUnicoProducto)) {
            return "Producto {$posicion}: el código '{$item->codigoUnicoProducto}' no existe en el nomenclador de productos de ARBA.";
        }

        if ($item->arbaCodigoUnidadMedida === '') {
            return "Producto {$posicion}: el código de unidad de medida de ARBA es obligatorio.";
        }

        if ((float) $item->cantidad <= 0) {
            return "Producto {$posicion}: la cantidad debe ser mayor a cero.";
        }

        if (! CotNumber::fits($item->cantidad, 13)) {
            return "Producto {$posicion}: la cantidad supera los 13 enteros y 2 decimales que admite el campo.";
        }

        if ((float) $item->cantidadAjustada <= 0) {
            return "Producto {$posicion}: la cantidad ajustada debe ser mayor a cero.";
        }

        if (! CotNumber::fits($item->cantidadAjustada, 13)) {
            return "Producto {$posicion}: la cantidad ajustada supera los 13 enteros y 2 decimales que admite el campo.";
        }

        if ($item->propioCodigoProducto === '') {
            return "Producto {$posicion}: el código propio de producto es obligatorio.";
        }

        if ($item->propioDescripcionProducto === '') {
            return "Producto {$posicion}: la descripción propia del producto es obligatoria.";
        }

        if ($item->propioDescripcionUnidadMedida === '') {
            return "Producto {$posicion}: la descripción propia de la unidad de medida es obligatoria.";
        }

        return null;
    }

    /**
     * El instructivo dice que las longitudes son máximas. No hay truncado: un
     * campo más largo que su definición es un archivo inválido, así que se
     * rechaza acá en lugar de perder el dato en silencio.
     */
    private function validarLargos(CotRemitoData $remito, array &$errores): void
    {
        $valores = [
            'codigo_unico' => $remito->codigoUnico,
            'destinatario_tipo_documento' => $remito->destinatarioTipoDocumento,
            'destinatario_documento' => $remito->destinatarioDocumento,
            'destinatario_cuit' => $remito->destinatarioCuit,
            'destinatario_razon_social' => $remito->destinatarioRazonSocial,
            'propio_destino_domicilio_codigo' => $remito->propioDestinoDomicilioCodigo,
            'origen_cuit' => $remito->origenCuit,
            'origen_razon_social' => $remito->origenRazonSocial,
            'transportista_cuit' => $remito->transportistaCuit,
            'recorrido_localidad' => $remito->recorridoLocalidad,
            'recorrido_calle' => $remito->recorridoCalle,
            'recorrido_ruta' => $remito->recorridoRuta,
            'patente_vehiculo' => $remito->patenteVehiculo,
            'patente_acoplado' => $remito->patenteAcoplado,
        ];

        foreach ($valores as $campo => $valor) {
            $this->validarLargo($campo, $valor, self::LARGOS[$campo], $errores);
        }

        foreach (self::LARGOS_DOMICILIO as $campo => $largo) {
            $this->validarLargo('origen_domicilio_' . $campo, $remito->origen->{$this->propiedadDomicilio($campo)}, $largo, $errores);
            $this->validarLargo('destino_domicilio_' . $campo, $remito->destino->{$this->propiedadDomicilio($campo)}, $largo, $errores);
        }

        foreach ($remito->items as $item) {
            foreach (self::LARGOS_ITEM as $campo => $largo) {
                $this->validarLargo($campo, $item->{$this->propiedadItem($campo)}, $largo, $errores);
            }
        }
    }

    private function validarLargo(string $campo, string $valor, int $largo, array &$errores): void
    {
        if (isset($errores[$campo])) {
            return;
        }

        if (mb_strlen($valor) > $largo) {
            $errores[$campo] = "El campo supera el máximo de {$largo} caracteres que admite el diseño del archivo.";
        }
    }

    private function propiedadDomicilio(string $campo): string
    {
        return [
            'calle' => 'calle',
            'numero' => 'numero',
            'comple' => 'comple',
            'piso' => 'piso',
            'dto' => 'dto',
            'barrio' => 'barrio',
            'codigo_postal' => 'codigoPostal',
            'localidad' => 'localidad',
            'provincia' => 'provincia',
        ][$campo];
    }

    private function propiedadItem(string $campo): string
    {
        return [
            'codigo_unico_producto' => 'codigoUnicoProducto',
            'arba_codigo_unidad_medida' => 'arbaCodigoUnidadMedida',
            'propio_codigo_producto' => 'propioCodigoProducto',
            'propio_descripcion_producto' => 'propioDescripcionProducto',
            'propio_descripcion_unidad_medida' => 'propioDescripcionUnidadMedida',
        ][$campo];
    }

    private function parseFecha(?string $fecha): ?\DateTimeImmutable
    {
        if ($fecha === null || ! preg_match('/^\d{8}$/', $fecha)) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Ymd', $fecha);

        // createFromFormat acepta 20260231 y lo corre a marzo: hay que verificar
        // que la fecha exista de verdad.
        if ($parsed === false || $parsed->format('Ymd') !== $fecha) {
            return null;
        }

        return $parsed;
    }

    private function isValidHora(string $hora): bool
    {
        if (! preg_match('/^\d{4}$/', $hora)) {
            return false;
        }

        $hh = (int) substr($hora, 0, 2);
        $mm = (int) substr($hora, 2, 2);

        return $hh >= 0 && $hh <= 23 && $mm >= 0 && $mm <= 59;
    }
}
