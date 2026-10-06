<?php

namespace Tests\Unit\Arba\Cot;

use App\Src\Arba\Cot\CotAddressData;
use App\Src\Arba\Cot\CotRemitoData;
use App\Src\Arba\Cot\CotValidator;
use PHPUnit\Framework\TestCase;

/**
 * Reglas cruzadas del instructivo que, si se pasan por alto, se descubren con el
 * archivo rechazado por ARBA y sin indicación de qué remito lo rompió.
 *
 * El caso más caro es el del sujeto generador: cuando el generador es el
 * EMISOR, el CUIT de origen tiene que ser el de la empresa que presenta el
 * archivo; cuando es el DESTINATARIO, el que tiene que coincidir es el del
 * destinatario. Si eso se invierte, el archivo entero se rechaza.
 */
class CotValidatorTest extends TestCase
{
    private CotValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new CotValidator(CotRemitoFactory::nomenclador());
    }

    public function test_el_remito_de_referencia_es_valido(): void
    {
        $this->assertSame([], $this->validar(CotRemitoFactory::valid()));
    }

    public function test_la_rutina_de_cuit_detecta_un_digito_verificador_mal(): void
    {
        $this->assertTrue(CotValidator::isValidCuit('30111111118'));
        $this->assertTrue(CotValidator::isValidCuit('20111111112'));
        $this->assertFalse(CotValidator::isValidCuit('30111111119'));
        $this->assertFalse(CotValidator::isValidCuit('3011111111'));
        $this->assertFalse(CotValidator::isValidCuit(''));
    }

    public function test_reconoce_los_dos_formatos_de_patente(): void
    {
        $this->assertTrue(CotValidator::isValidPatente('AB123CD'));
        $this->assertTrue(CotValidator::isValidPatente('ABC123'));
        $this->assertFalse(CotValidator::isValidPatente('AB123C'));
        $this->assertFalse(CotValidator::isValidPatente('123ABC'));
    }

    public function test_con_sujeto_generador_emisor_el_cuit_de_origen_es_el_de_la_empresa(): void
    {
        $errores = $this->validar(CotRemitoFactory::valid(['origenCuit' => '20111111112']));

        $this->assertArrayHasKey('origen_cuit', $errores);
    }

    public function test_con_sujeto_generador_destinatario_el_cuit_del_destinatario_es_el_de_la_empresa(): void
    {
        $remito = CotRemitoFactory::valid([
            'sujetoGenerador' => CotRemitoData::GENERADOR_DESTINATARIO,
            'destinatarioCuit' => CotRemitoFactory::CUIT_EMPRESA,
            'origenCuit' => CotRemitoFactory::CUIT_DESTINATARIO,
        ]);

        $this->assertSame([], $this->validar($remito));
    }

    public function test_con_sujeto_generador_destinatario_rechaza_si_el_destinatario_no_es_la_empresa(): void
    {
        $remito = CotRemitoFactory::valid([
            'sujetoGenerador' => CotRemitoData::GENERADOR_DESTINATARIO,
            'destinatarioCuit' => '20111111112',
        ]);

        $this->assertArrayHasKey('destinatario_cuit', $this->validar($remito));
    }

    public function test_rechaza_un_sujeto_generador_que_no_sea_e_ni_d(): void
    {
        $this->assertArrayHasKey('sujeto_generador', $this->validar(CotRemitoFactory::valid(['sujetoGenerador' => 'X'])));
    }

    public function test_la_fecha_de_salida_no_puede_ser_anterior_a_la_de_emision(): void
    {
        $remito = CotRemitoFactory::valid(['fechaSalidaTransporte' => '20060601']);

        $this->assertArrayHasKey('fecha_salida_transporte', $this->validar($remito));
    }

    public function test_la_fecha_de_salida_no_puede_superar_los_treinta_dias(): void
    {
        $remito = CotRemitoFactory::valid(['fechaSalidaTransporte' => '20060828']);

        $this->assertArrayHasKey('fecha_salida_transporte', $this->validar($remito));
    }

    public function test_la_fecha_de_salida_no_puede_ser_anterior_a_ayer(): void
    {
        $remito = CotRemitoFactory::valid([
            'fechaEmision' => '2006-06-01',
            'fechaSalidaTransporte' => '20060620',
        ]);

        $this->assertArrayHasKey('fecha_salida_transporte', $this->validar($remito));
    }

    public function test_acepta_la_ventana_de_manana(): void
    {
        // La ventana es [hoy - 1, hoy + 30]: mañana tiene que pasar.
        $remito = CotRemitoFactory::valid([
            'fechaEmision' => '2006-06-28',
            'fechaSalidaTransporte' => '20060629',
        ]);

        $this->assertArrayNotHasKey('fecha_salida_transporte', $this->validar($remito));
    }

    public function test_rechaza_una_fecha_que_no_existe_en_el_calendario(): void
    {
        $remito = CotRemitoFactory::valid(['fechaEmision' => '20060231']);

        $this->assertArrayHasKey('fecha_emision', $this->validar($remito));
    }

    /**
     * createFromFormat no falla con el 31 de febrero: devuelve el 3 de marzo.
     * Si el validador no mira los avisos, una fecha imposible pasa como buena.
     */
    public function test_rechaza_el_treinta_y_uno_de_febrero(): void
    {
        $remito = CotRemitoFactory::valid([
            'fechaEmision' => '31/02/2006',
            'fechaSalidaTransporte' => '31/02/2006',
        ]);

        $errores = $this->validar($remito);

        $this->assertArrayHasKey('fecha_emision', $errores);
        $this->assertArrayHasKey('fecha_salida_transporte', $errores);
    }

    /**
     * El formato argentino d/m/Y tiene que llegar al campo como AAAAMMDD.
     */
    public function test_acepta_la_fecha_en_formato_argentino(): void
    {
        $remito = CotRemitoFactory::valid([
            'fechaEmision' => '28/06/2006',
            'fechaSalidaTransporte' => '28/06/2006',
        ]);

        $this->assertSame('20060628', $remito->fechaEmisionNormalizada());
        $this->assertSame([], $this->validar($remito));
    }

    public function test_rechaza_una_hora_de_salida_invalida(): void
    {
        $this->assertArrayHasKey('hora_salida_transporte', $this->validar(CotRemitoFactory::valid(['horaSalidaTransporte' => '2599'])));
        $this->assertArrayHasKey('hora_salida_transporte', $this->validar(CotRemitoFactory::valid(['horaSalidaTransporte' => '830'])));
    }

    public function test_el_consumidor_final_no_puede_ser_tenedor(): void
    {
        $remito = CotRemitoFactory::valid([
            'destinatarioConsumidorFinal' => true,
            'destinatarioTenedor' => true,
        ]);

        $this->assertArrayHasKey('destinatario_tenedor', $this->validar($remito));
    }

    public function test_consumidor_final_con_importe_alto_exige_documento_y_razon_social(): void
    {
        $remito = CotRemitoFactory::valid([
            'destinatarioConsumidorFinal' => true,
            'destinatarioCuit' => '',
            'destinatarioRazonSocial' => '',
            'destinatarioTipoDocumento' => '',
            'destinatarioDocumento' => '',
            'importe' => 5000,
        ]);

        $errores = $this->validar($remito);

        $this->assertArrayHasKey('destinatario_documento', $errores);
        $this->assertArrayHasKey('destinatario_razon_social', $errores);
    }

    public function test_consumidor_final_con_importe_bajo_no_exige_documento(): void
    {
        $remito = CotRemitoFactory::valid([
            'destinatarioConsumidorFinal' => true,
            'destinatarioCuit' => '',
            'destinatarioRazonSocial' => '',
            'importe' => 4999.99,
        ]);

        $this->assertSame([], $this->validar($remito));
    }

    public function test_consumidor_final_con_importe_alto_pasa_si_informa_dni(): void
    {
        $remito = CotRemitoFactory::valid([
            'destinatarioConsumidorFinal' => true,
            'destinatarioCuit' => '',
            'destinatarioRazonSocial' => 'NR',
            'destinatarioTipoDocumento' => 'DNI',
            'destinatarioDocumento' => '12345678',
            'importe' => 5000,
        ]);

        $this->assertSame([], $this->validar($remito));
    }

    public function test_exige_que_alguna_punta_del_viaje_sea_buenos_aires(): void
    {
        $remito = CotRemitoFactory::valid([
            'destino' => new CotAddressData(
                calle: 'AV COLON',
                numero: '100',
                codigoPostal: '5000',
                localidad: 'CORDOBA',
                provincia: 'X',
            ),
            'origen' => new CotAddressData(
                calle: 'CALLE FALSA',
                numero: '742',
                codigoPostal: '1900',
                localidad: 'LA PLATA',
                provincia: 'B',
            ),
        ]);

        $this->assertSame([], $this->validar($remito));

        $remito->origen->provincia = 'K';

        $this->assertArrayHasKey('provincias', $this->validar($remito));
    }

    /**
     * La provincia tiene que existir en la tabla de ARBA: un código inventado
     * hace que ARBA rechace el archivo con el error 28 o el 33.
     */
    public function test_rechaza_un_codigo_de_provincia_que_no_existe(): void
    {
        $remito = CotRemitoFactory::valid([
            'destino' => new CotAddressData(
                calle: 'AV COLON',
                numero: '100',
                codigoPostal: '5000',
                localidad: 'CORDOBA',
                provincia: 'I',
            ),
        ]);

        $this->assertArrayHasKey('destino_domicilio_provincia', $this->validar($remito));
    }

    /**
     * El código del registro 03 tiene que existir en el nomenclador de ARBA en
     * el nivel VARIEDAD, que es el único de seis dígitos. ARBA rechaza el remito
     * con el error 39 si el código no está en su nomenclador.
     */
    public function test_rechaza_un_producto_que_no_esta_en_el_nomenclador(): void
    {
        $remito = CotRemitoFactory::valid([
            'items' => [CotRemitoFactory::item(['codigoUnicoProducto' => '999999'])],
        ]);

        $errores = $this->validar($remito);

        $this->assertArrayHasKey('items', $errores);
        $this->assertStringContainsString('999999', $errores['items']);
        $this->assertStringContainsString('nomenclador', $errores['items']);
    }

    public function test_acepta_los_productos_que_estan_en_el_nomenclador(): void
    {
        foreach ([CotRemitoFactory::PRODUCTO, CotRemitoFactory::PRODUCTO_ALTERNO] as $codigo) {
            $remito = CotRemitoFactory::valid(['items' => [CotRemitoFactory::item(['codigoUnicoProducto' => $codigo])]]);

            $this->assertSame([], $this->validar($remito), "El código {$codigo} debería ser aceptado.");
        }
    }

    public function test_con_flota_propia_la_patente_del_vehiculo_es_obligatoria(): void
    {
        $this->assertArrayHasKey('patente_vehiculo', $this->validar(CotRemitoFactory::valid(['patenteVehiculo' => ''])));
    }

    public function test_con_transportista_de_terceros_la_patente_no_es_obligatoria(): void
    {
        $remito = CotRemitoFactory::valid([
            'transportistaCuit' => '20111111112',
            'patenteVehiculo' => '',
        ]);

        $this->assertArrayNotHasKey('patente_vehiculo', $this->validar($remito));
    }

    public function test_rechaza_una_patente_mal_formada(): void
    {
        $this->assertArrayHasKey('patente_vehiculo', $this->validar(CotRemitoFactory::valid(['patenteVehiculo' => 'AB12CD'])));
        $this->assertArrayHasKey('patente_acoplado', $this->validar(CotRemitoFactory::valid(['patenteAcoplado' => 'XX'])));
    }

    public function test_el_importe_cero_solo_se_admite_si_origen_y_destinatario_son_el_mismo_cuit(): void
    {
        $this->assertArrayHasKey('importe', $this->validar(CotRemitoFactory::valid(['importe' => 0])));

        $mismoTitular = CotRemitoFactory::valid([
            'destinatarioCuit' => CotRemitoFactory::CUIT_EMPRESA,
            'importe' => 0,
        ]);

        $this->assertSame([], $this->validar($mismoTitular));
    }

    public function test_rechaza_un_importe_negativo(): void
    {
        $this->assertArrayHasKey('importe', $this->validar(CotRemitoFactory::valid(['importe' => -1])));
    }

    public function test_exige_al_menos_un_producto(): void
    {
        $this->assertArrayHasKey('items', $this->validar(CotRemitoFactory::valid(['items' => []])));
    }

    public function test_valida_los_datos_obligatorios_de_cada_producto(): void
    {
        $sinCodigo = CotRemitoFactory::valid(['items' => [CotRemitoFactory::item(['codigoUnicoProducto' => ''])] ]);
        $sinUnidad = CotRemitoFactory::valid(['items' => [CotRemitoFactory::item(['arbaCodigoUnidadMedida' => ''])]]);
        $cantidadCero = CotRemitoFactory::valid(['items' => [CotRemitoFactory::item(['cantidad' => 0])]]);
        $ajustadaCero = CotRemitoFactory::valid(['items' => [CotRemitoFactory::item(['cantidadAjustada' => 0])]]);
        $sinDescripcion = CotRemitoFactory::valid(['items' => [CotRemitoFactory::item(['propioDescripcionProducto' => ''])]]);

        $this->assertArrayHasKey('items', $this->validar($sinCodigo));
        $this->assertArrayHasKey('items', $this->validar($sinUnidad));
        $this->assertArrayHasKey('items', $this->validar($cantidadCero));
        $this->assertArrayHasKey('items', $this->validar($ajustadaCero));
        $this->assertArrayHasKey('items', $this->validar($sinDescripcion));
    }

    /**
     * El instructivo dice que las longitudes son máximas. No hay truncado, así
     * que un campo largo invalida el archivo en lugar de perder el dato.
     */
    public function test_rechaza_los_campos_que_superan_el_largo_del_diseno(): void
    {
        $remito = CotRemitoFactory::valid(['destinatarioRazonSocial' => str_repeat('A', 51)]);

        $this->assertArrayHasKey('destinatario_razon_social', $this->validar($remito));
    }

    /**
     * Con varios problemas en un mismo producto se informa el primero y no el
     * último que se evaluó: si el mensaje depende del orden interno del
     * validador, el usuario ve un error distinto cada vez que reintenta.
     */
    public function test_informa_el_primer_problema_del_primer_producto_con_problemas(): void
    {
        $remito = CotRemitoFactory::valid([
            'items' => [
                CotRemitoFactory::item(),
                CotRemitoFactory::item([
                    'codigoUnicoProducto' => '110200',
                    'arbaCodigoUnidadMedida' => '',
                    'cantidad' => 0,
                    'propioDescripcionProducto' => '',
                ]),
            ],
        ]);

        $errores = $this->validar($remito);

        $this->assertArrayHasKey('items', $errores);
        $this->assertStringContainsString('Producto 2', $errores['items']);
        $this->assertStringContainsString('unidad de medida', $errores['items']);
    }

    public function test_valida_el_complemento_del_domicilio(): void
    {
        $invalido = CotRemitoFactory::valid([
            'origen' => new CotAddressData(
                calle: 'CALLE FALSA',
                numero: '742',
                comple: '3/4',
                codigoPostal: '1900',
                localidad: 'LA PLATA',
                provincia: 'B',
            ),
        ]);

        $this->assertArrayHasKey('origen_domicilio_comple', $this->validar($invalido));
    }

    public function test_acepta_sin_numero_cuando_el_complemento_es_sn(): void
    {
        $remito = CotRemitoFactory::valid([
            'origen' => new CotAddressData(
                calle: 'RUTA 11 KM 12',
                numero: '',
                comple: 'S/N',
                codigoPostal: '1900',
                localidad: 'LA PLATA',
                provincia: 'B',
            ),
        ]);

        $this->assertArrayNotHasKey('origen_domicilio_numero', $this->validar($remito));
    }

    public function test_rechaza_un_codigo_unico_con_numero_cero(): void
    {
        $this->assertArrayHasKey('codigo_unico', $this->validar(CotRemitoFactory::valid(['codigoUnico' => '91 R000100000000'])));
        $this->assertArrayHasKey('codigo_unico', $this->validar(CotRemitoFactory::valid(['codigoUnico' => '91R0001'])));
    }

    public function test_rechaza_un_tipo_de_recorrido_invalido(): void
    {
        $this->assertArrayHasKey('tipo_recorrido', $this->validar(CotRemitoFactory::valid(['tipoRecorrido' => 'Z'])));
    }

    public function test_rechaza_un_cuit_de_transportista_invalido(): void
    {
        $this->assertArrayHasKey('transportista_cuit', $this->validar(CotRemitoFactory::valid(['transportistaCuit' => '30111111119'])));
    }

    /**
     * @return array<string, string>
     */
    private function validar(CotRemitoData $remito): array
    {
        return $this->validator->validate(
            CotRemitoFactory::CUIT_EMPRESA,
            $remito,
            new \DateTimeImmutable(CotRemitoFactory::HOY_DEL_EJEMPLO)
        );
    }
}
