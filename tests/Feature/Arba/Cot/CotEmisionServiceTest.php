<?php

namespace Tests\Feature\Arba\Cot;

use App\Models\Cot;
use App\Models\Company;
use App\Models\CotArchivo;
use App\Src\Arba\Cot\CotEmisionService;
use App\Src\Arba\Cot\CotFileName;
use App\Src\Arba\Cot\CotNomenclador;
use App\Src\Arba\Cot\Transport\CotTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\Arba\Cot\CotRemitoFactory;
use Tests\Unit\Arba\Cot\Transport\FakeCotTransport;

/**
 * El envío completo: validar, armar el TXT, presentarlo y guardar la respuesta.
 *
 * Nada de esto sale a internet. El transporte se reemplaza por uno falso, y esa
 * es justamente la razón de que exista la interfaz CotTransport: probar el
 * camino feliz y todos los de error sin depender de tener una CIT ni de que el
 * servicio de ARBA esté arriba.
 */
class CotEmisionServiceTest extends TestCase
{
    use RefreshDatabase;

    private const CUIT = '30111111118';

    private const PLANTA = '003';

    private const PUERTA = '017';

    private Company $empresa;

    private CotArchivo $archivo;

    protected function setUp(): void
    {
        parent::setUp();

        // El validador consulta el nomenclador: en memoria alcanza y evita
        // importar las 7.340 filas en cada test.
        $this->app->instance(CotNomenclador::class, CotRemitoFactory::nomenclador());

        $this->empresa = Company::factory()->create([
            'afip_number' => self::CUIT,
            'arba_cit_user' => 'usuario-cit',
            'arba_cit_password' => 'clave-cit',
            'arba_cit_environment' => 'testing',
        ]);

        $this->archivo = $this->crearArchivo($this->empresa);
    }

    public function test_envia_el_archivo_y_persiste_la_respuesta_de_arba(): void
    {
        $cot = $this->crearCot($this->empresa, $this->archivo);
        $transport = FakeCotTransport::ok($this->respuestaOk([$cot->codigo_unico]));

        $resultado = $this->servicio($transport)->emitir($this->archivo);

        $this->assertTrue($resultado->exitoso);
        $this->assertTrue($transport->envio());

        $llamada = $transport->ultimaLlamada();
        $this->assertSame('usuario-cit', $llamada['campos']['user']);
        $this->assertSame('clave-cit', $llamada['campos']['password']);
        $this->assertSame($this->archivo->nombre_archivo, $transport->nombreArchivoEnviado());
        $this->assertStringContainsString('http://cot.test.arba.gov.ar', $llamada['url']);

        $archivo = $this->archivo->fresh();

        $this->assertSame(CotArchivo::ESTADO_PROCESADO, $archivo->estado);
        $this->assertSame('123456789', $archivo->numero_comprobante);
        $this->assertSame('d41d8cd98f00b204e9800998ecf8427e', $archivo->codigo_integridad);
        $this->assertSame(200, $archivo->http_status);
        $this->assertNotNull($archivo->enviado_at);
        $this->assertSame(hash('sha256', $archivo->contenido), $archivo->hash);

        $this->assertTrue($cot->fresh()->procesado);
        $this->assertNull($cot->fresh()->errores);
    }

    /**
     * El archivo tiene que salir con los cuatro registros y con el footer
     * contando remitos, no líneas.
     */
    public function test_envia_un_archivo_bien_formado(): void
    {
        $cot = $this->crearCot($this->empresa, $this->archivo);
        $transport = FakeCotTransport::ok($this->respuestaOk([$cot->codigo_unico]));

        $this->servicio($transport)->emitir($this->archivo);

        $enviado = $transport->contenidoEnviado();

        $this->assertStringStartsWith('01|' . self::CUIT . "\n", $enviado);
        $this->assertStringContainsString("\n02|", $enviado);
        $this->assertStringContainsString("\n03|110100|K|20000|", $enviado);
        $this->assertStringEndsWith("04|1\n", $enviado);
        $this->assertStringEndsWith("\n", $enviado);
    }

    public function test_envia_varios_remitos_en_un_mismo_archivo(): void
    {
        $uno = $this->crearCot($this->empresa, $this->archivo, 1);
        $dos = $this->crearCot($this->empresa, $this->archivo, 2);
        $transport = FakeCotTransport::ok($this->respuestaOk([$uno->codigo_unico, $dos->codigo_unico]));

        $resultado = $this->servicio($transport)->emitir($this->archivo);

        $this->assertTrue($resultado->todosProcesados());
        $this->assertStringEndsWith("04|2\n", $transport->contenidoEnviado());
        $this->assertSame(2, $this->archivo->fresh()->cantidad_remitos);
        $this->assertTrue($uno->fresh()->procesado);
        $this->assertTrue($dos->fresh()->procesado);
    }

    /**
     * Sin CIT no hay nada que hacer, y es mejor decirlo antes de intentar: el
     * error 02 de ARBA ("usuario y/o contraseña inválidos") no aclara que el
     * problema es que nunca se cargaron.
     */
    public function test_no_envia_si_la_empresa_no_tiene_credenciales_de_la_cit(): void
    {
        $empresa = Company::factory()->create(['afip_number' => self::CUIT, 'arba_cit_user' => null]);
        $archivo = $this->crearArchivo($empresa);
        $this->crearCot($empresa, $archivo);

        $transport = FakeCotTransport::ok($this->respuestaOk([]));

        $resultado = $this->servicio($transport)->emitir($archivo);

        $this->assertFalse($resultado->exitoso);
        $this->assertFalse($transport->envio());
        $this->assertSame(CotEmisionService::TIPO_CREDENCIALES, $archivo->fresh()->error_tipo);
        $this->assertStringContainsString('CIT', $archivo->fresh()->error_mensaje);
    }

    /**
     * Un archivo rechazado gasta un envío y deja la duda de si algo se emitió.
     * Todo lo que se pueda validar se valida antes de salir.
     */
    public function test_no_envia_si_un_remito_no_valida(): void
    {
        // Sin productos: el registro 03 es obligatorio.
        $cot = $this->crearCot($this->empresa, $this->archivo);
        $cot->items()->delete();

        $transport = FakeCotTransport::ok($this->respuestaOk([]));

        $resultado = $this->servicio($transport)->emitir($this->archivo);

        $this->assertFalse($resultado->exitoso);
        $this->assertFalse($transport->envio());
        $this->assertSame(CotEmisionService::TIPO_VALIDACION, $this->archivo->fresh()->error_tipo);
        $this->assertStringContainsString('registro de productos', $this->archivo->fresh()->error_mensaje);
    }

    public function test_detalla_que_remito_y_que_campo_no_valida(): void
    {
        $this->crearCot($this->empresa, $this->archivo, 1);
        $this->crearCot($this->empresa, $this->archivo, 2, ['transportistaCuit' => '']);

        $transport = FakeCotTransport::ok($this->respuestaOk([]));

        $resultado = $this->servicio($transport)->emitir($this->archivo);

        $this->assertFalse($resultado->exitoso);
        $this->assertStringContainsString('Remito 2', $resultado->errorMensaje);
        $this->assertStringContainsString('transportista_cuit', $resultado->errorMensaje);
    }

    public function test_no_reenvia_un_archivo_ya_procesado(): void
    {
        $this->crearCot($this->empresa, $this->archivo);
        $this->archivo->estado = CotArchivo::ESTADO_PROCESADO;
        $this->archivo->save();

        $transport = FakeCotTransport::ok($this->respuestaOk([]));

        $resultado = $this->servicio($transport)->emitir($this->archivo);

        $this->assertFalse($resultado->exitoso);
        $this->assertFalse($transport->envio());
        $this->assertStringContainsString('ya fue presentado', $resultado->errorMensaje);
    }

    /**
     * El nombre del archivo es la clave de idempotencia y además es donde viajan
     * CUIT, planta, puerta y fecha: si no coincide con el contenido, ARBA rechaza
     * el archivo entero.
     */
    public function test_rechaza_si_el_nombre_del_archivo_no_coincide_con_los_datos(): void
    {
        $this->crearCot($this->empresa, $this->archivo);
        $this->archivo->planta = '099';
        $this->archivo->save();

        $transport = FakeCotTransport::ok($this->respuestaOk([]));

        $resultado = $this->servicio($transport)->emitir($this->archivo);

        $this->assertFalse($resultado->exitoso);
        $this->assertFalse($transport->envio());
        $this->assertStringContainsString('planta', $resultado->errorMensaje);
    }

    public function test_persiste_el_rechazo_de_transaccion_de_arba(): void
    {
        $this->crearCot($this->empresa, $this->archivo);

        $transport = FakeCotTransport::ok(
            '<TBError><tipoError>DATO</tipoError><codigoError>02</codigoError>'
            . '<mensajeError>El usuario ingresado y/o la contraseña son inválidos.</mensajeError></TBError>'
        );

        $resultado = $this->servicio($transport)->emitir($this->archivo);

        $this->assertFalse($resultado->exitoso);

        $archivo = $this->archivo->fresh();

        $this->assertSame(CotArchivo::ESTADO_ERROR, $archivo->estado);
        $this->assertSame('DATO', $archivo->error_tipo);
        $this->assertSame('02', $archivo->error_codigo);
        $this->assertStringContainsString('inválidos', $archivo->error_mensaje);
        $this->assertStringContainsString('TBError', $archivo->respuesta);
    }

    /**
     * Si se corta la conexión no se sabe si ARBA procesó el archivo. Lo que sí
     * tiene que quedar es el TXT guardado y el aviso de reintentar con el MISMO
     * nombre, que es lo que hace segura la reintentada.
     */
    public function test_guarda_el_archivo_aunque_falle_el_transporte(): void
    {
        $this->crearCot($this->empresa, $this->archivo);
        $transport = FakeCotTransport::caido('Operation timed out after 60000 milliseconds');

        $resultado = $this->servicio($transport)->emitir($this->archivo);

        $this->assertFalse($resultado->exitoso);
        $this->assertStringContainsString('timed out', $resultado->errorMensaje);
        $this->assertStringContainsString('MISMO nombre', $resultado->errorMensaje);

        $archivo = $this->archivo->fresh();

        $this->assertSame(CotArchivo::ESTADO_ERROR, $archivo->estado);
        $this->assertSame(CotEmisionService::TIPO_TRANSPORTE, $archivo->error_tipo);
        $this->assertNotEmpty($archivo->contenido);
        $this->assertNotNull($archivo->hash);
        $this->assertNotNull($archivo->enviado_at);
    }

    public function test_marca_el_archivo_como_rechazado_si_un_remito_falla(): void
    {
        $uno = $this->crearCot($this->empresa, $this->archivo, 1);
        $dos = $this->crearCot($this->empresa, $this->archivo, 2);

        $transport = FakeCotTransport::ok(
            '<TBCOMPROBANTE><numeroComprobante>555</numeroComprobante><validacionesRemitos>'
            . '<remito><numeroUnico>' . $uno->codigo_unico . '</numeroUnico><procesado>SI</procesado></remito>'
            . '<remito><numeroUnico>' . $dos->codigo_unico . '</numeroUnico><procesado>NO</procesado>'
            . '<errores><error><codigo>42</codigo><descripcion>El campo CANTIDAD es inválido o inexistente.</descripcion></error></errores>'
            . '</remito></validacionesRemitos></TBCOMPROBANTE>'
        );

        $resultado = $this->servicio($transport)->emitir($this->archivo);

        $this->assertTrue($resultado->exitoso);
        $this->assertFalse($resultado->todosProcesados());
        $this->assertSame(CotArchivo::ESTADO_RECHAZADO, $this->archivo->fresh()->estado);

        $this->assertTrue($uno->fresh()->procesado);
        $this->assertFalse($dos->fresh()->procesado);
        $this->assertStringContainsString('42', $dos->fresh()->errores);

        $this->assertStringContainsString($dos->codigo_unico, $this->archivo->fresh()->error_mensaje);
    }

    /**
     * Los códigos 11 y 17 llegan como error pero significan que el remito ya
     * estaba emitido. Ignorarlo llevaría a reintentar y duplicar.
     */
    public function test_trata_los_codigos_11_y_17_como_ya_emitido(): void
    {
        $cot = $this->crearCot($this->empresa, $this->archivo);

        $transport = FakeCotTransport::ok(
            '<TBCOMPROBANTE><numeroComprobante>555</numeroComprobante><validacionesRemitos>'
            . '<remito><numeroUnico>' . $cot->codigo_unico . '</numeroUnico><procesado>NO</procesado>'
            . '<errores><error><codigo>17</codigo><descripcion>El remito ya fue procesado con anterioridad.</descripcion></error></errores>'
            . '</remito></validacionesRemitos></TBCOMPROBANTE>'
        );

        $resultado = $this->servicio($transport)->emitir($this->archivo);

        $this->assertTrue($resultado->todosProcesados());
        $this->assertTrue($cot->fresh()->procesado);
        $this->assertSame(CotArchivo::ESTADO_PROCESADO, $this->archivo->fresh()->estado);
    }

    /**
     * Si ARBA no informa un remito, no se lo puede dar por bueno en silencio.
     */
    public function test_un_remito_que_arba_no_informa_queda_sin_procesar(): void
    {
        $cot = $this->crearCot($this->empresa, $this->archivo);

        $transport = FakeCotTransport::ok(
            '<TBCOMPROBANTE><numeroComprobante>555</numeroComprobante><validacionesRemitos>'
            . '</validacionesRemitos></TBCOMPROBANTE>'
        );

        $this->servicio($transport)->emitir($this->archivo);

        $this->assertFalse($cot->fresh()->procesado);
        $this->assertStringContainsString('no informó el resultado', $cot->fresh()->errores);
    }

    public function test_usa_el_endpoint_de_produccion_cuando_la_empresa_esta_en_produccion(): void
    {
        $this->empresa->arba_cit_environment = 'production';
        $this->empresa->save();

        $cot = $this->crearCot($this->empresa, $this->archivo);
        $transport = FakeCotTransport::ok($this->respuestaOk([$cot->codigo_unico]));

        $this->servicio($transport)->emitir($this->archivo);

        $this->assertStringStartsWith('https://cot.arba.gov.ar', $transport->ultimaLlamada()['url']);
    }

    /**
     * Un ambiente mal cargado no puede terminar mandando un remito real de
     * producción: si no se entiende el valor, se va a testing.
     */
    public function test_un_ambiente_desconocido_cae_en_testing(): void
    {
        $this->empresa->arba_cit_environment = 'cualquiera';
        $this->empresa->save();

        $cot = $this->crearCot($this->empresa, $this->archivo);
        $transport = FakeCotTransport::ok($this->respuestaOk([$cot->codigo_unico]));

        $this->servicio($transport)->emitir($this->archivo);

        $this->assertStringContainsString('cot.test.arba.gov.ar', $transport->ultimaLlamada()['url']);
    }

    public function test_rechaza_un_archivo_sin_remitos(): void
    {
        $transport = FakeCotTransport::ok($this->respuestaOk([]));

        $resultado = $this->servicio($transport)->emitir($this->archivo);

        $this->assertFalse($resultado->exitoso);
        $this->assertFalse($transport->envio());
        $this->assertStringContainsString('no tiene remitos', $resultado->errorMensaje);
    }

    /**
     * La clave de la CIT no puede quedar en la base en texto plano ni salir en
     * una serialización del modelo.
     */
    public function test_la_clave_de_la_cit_viaja_al_endpoint_pero_no_se_persiste_en_claro(): void
    {
        $cot = $this->crearCot($this->empresa, $this->archivo);
        $transport = FakeCotTransport::ok($this->respuestaOk([$cot->codigo_unico]));

        $this->servicio($transport)->emitir($this->archivo);

        $this->assertSame('clave-cit', $transport->ultimaLlamada()['campos']['password']);

        $crudo = \Illuminate\Support\Facades\DB::table('companies')->where('id', $this->empresa->id)->value('arba_cit_password');
        $this->assertNotSame('clave-cit', $crudo);

        $this->assertArrayNotHasKey('arba_cit_password', $this->empresa->fresh()->toArray());
    }

    private function servicio(FakeCotTransport $transport): CotEmisionService
    {
        $this->app->instance(CotTransport::class, $transport);

        return $this->app->make(CotEmisionService::class);
    }

    private function crearArchivo(Company $empresa, string $planta = self::PLANTA, string $puerta = self::PUERTA): CotArchivo
    {
        $hoy = now()->format('Y-m-d');

        // La secuencia sale del id de la empresa para que dos empresas del mismo
        // test no choquen contra el índice único del nombre de archivo.
        $secuencia = str_pad((string) $empresa->id, 6, '0', STR_PAD_LEFT);

        return CotArchivo::create([
            'company_id' => $empresa->id,
            'nombre_archivo' => CotFileName::build(self::CUIT, $planta, $puerta, $hoy, $secuencia),
            'cuit_empresa' => self::CUIT,
            'planta' => $planta,
            'puerta' => $puerta,
            'fecha_archivo' => $hoy,
            'secuencia' => $secuencia,
        ]);
    }

    private function crearCot(Company $empresa, CotArchivo $archivo, int $numero = 1, array $overrides = []): Cot
    {
        $datos = CotRemitoFactory::valid(array_merge([
            'codigoUnico' => '91 R0001' . str_pad((string) $numero, 8, '0', STR_PAD_LEFT),
            'fechaEmision' => now()->format('Y-m-d'),
            'fechaSalidaTransporte' => now()->format('Y-m-d'),
        ], $overrides));

        $cot = Cot::create([
            'cot_archivo_id' => $archivo->id,
            'company_id' => $empresa->id,
            'sujeto_generador' => $datos->sujetoGenerador,
            'codigo_unico' => $datos->codigoUnico,
            'fecha_emision' => $datos->fechaEmisionNormalizada(),
            'fecha_salida_transporte' => $datos->fechaSalidaNormalizada(),
            'hora_salida_transporte' => $datos->horaSalidaTransporte,
            'destinatario_consumidor_final' => $datos->destinatarioConsumidorFinal,
            'destinatario_tipo_documento' => $datos->destinatarioTipoDocumento,
            'destinatario_documento' => $datos->destinatarioDocumento,
            'destinatario_cuit' => $datos->destinatarioCuit,
            'destinatario_razon_social' => $datos->destinatarioRazonSocial,
            'destinatario_tenedor' => $datos->destinatarioTenedor,
            'destino_domicilio_calle' => $datos->destino->calle,
            'destino_domicilio_numero' => $datos->destino->numero,
            'destino_domicilio_comple' => $datos->destino->comple,
            'destino_domicilio_piso' => $datos->destino->piso,
            'destino_domicilio_dto' => $datos->destino->dto,
            'destino_domicilio_barrio' => $datos->destino->barrio,
            'destino_domicilio_codigo_postal' => $datos->destino->codigoPostal,
            'destino_domicilio_localidad' => $datos->destino->localidad,
            'destino_domicilio_provincia' => $datos->destino->provincia,
            'propio_destino_domicilio_codigo' => $datos->propioDestinoDomicilioCodigo,
            'entrega_domicilio_origen' => $datos->entregaDomicilioOrigen,
            'origen_cuit' => $datos->origenCuit,
            'origen_razon_social' => $datos->origenRazonSocial,
            'emisor_tenedor' => $datos->emisorTenedor,
            'origen_domicilio_calle' => $datos->origen->calle,
            'origen_domicilio_numero' => $datos->origen->numero,
            'origen_domicilio_comple' => $datos->origen->comple,
            'origen_domicilio_piso' => $datos->origen->piso,
            'origen_domicilio_dto' => $datos->origen->dto,
            'origen_domicilio_barrio' => $datos->origen->barrio,
            'origen_domicilio_codigo_postal' => $datos->origen->codigoPostal,
            'origen_domicilio_localidad' => $datos->origen->localidad,
            'origen_domicilio_provincia' => $datos->origen->provincia,
            'transportista_cuit' => $datos->transportistaCuit,
            'tipo_recorrido' => $datos->tipoRecorrido,
            'recorrido_localidad' => $datos->recorridoLocalidad,
            'recorrido_calle' => $datos->recorridoCalle,
            'recorrido_ruta' => $datos->recorridoRuta,
            'patente_vehiculo' => $datos->patenteVehiculo,
            'patente_acoplado' => $datos->patenteAcoplado,
            'producto_no_term_dev' => $datos->productoNoTermDev,
            'importe' => $datos->importe,
        ]);

        foreach ($datos->items as $item) {
            $cot->items()->create([
                'codigo_unico_producto' => $item->codigoUnicoProducto,
                'arba_codigo_unidad_medida' => $item->arbaCodigoUnidadMedida,
                'cantidad' => $item->cantidad,
                'propio_codigo_producto' => $item->propioCodigoProducto,
                'propio_descripcion_producto' => $item->propioDescripcionProducto,
                'propio_descripcion_unidad_medida' => $item->propioDescripcionUnidadMedida,
                'cantidad_ajustada' => $item->cantidadAjustada,
            ]);
        }

        return $cot;
    }

    /**
     * @param  string[]  $codigosUnicos
     */
    private function respuestaOk(array $codigosUnicos): string
    {
        $remitos = '';

        foreach ($codigosUnicos as $codigo) {
            $remitos .= '<remito><numeroUnico>' . $codigo . '</numeroUnico><procesado>SI</procesado></remito>';
        }

        return '<TBCOMPROBANTE>'
            . '<cuitEmpresa>' . self::CUIT . '</cuitEmpresa>'
            . '<numeroComprobante>123456789</numeroComprobante>'
            . '<codigoIntegridad>d41d8cd98f00b204e9800998ecf8427e</codigoIntegridad>'
            . '<validacionesRemitos class="list">' . $remitos . '</validacionesRemitos>'
            . '</TBCOMPROBANTE>';
    }
}
