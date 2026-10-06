<?php

namespace Tests\Unit\Arba\Cot;

use App\Src\Arba\Cot\CotResponseParser;
use PHPUnit\Framework\TestCase;

/**
 * La respuesta de ARBA es el único punto del módulo donde el formato lo decide
 * otro. Si el parseo falla, el archivo ya se envió: no hay segunda oportunidad
 * sin arriesgar un duplicado.
 *
 * Los XML de este test están copiados del instructivo para la aplicación
 * cliente, así que si ARBA cambia el formato hay que actualizarlos ahí primero.
 */
class CotResponseParserTest extends TestCase
{
    private CotResponseParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new CotResponseParser();
    }

    private function comprobante(array $remitos, string $numero = '123456789'): string
    {
        $cuerpo = '';

        foreach ($remitos as $remito) {
            $cuerpo .= '<remito><numeroUnico>' . $remito['numeroUnico'] . '</numeroUnico>'
                . '<procesado>' . $remito['procesado'] . '</procesado>';

            if (isset($remito['errores'])) {
                $cuerpo .= '<errores class="list">';

                foreach ($remito['errores'] as $error) {
                    $cuerpo .= '<error><codigo>' . $error[0] . '</codigo><descripcion>' . $error[1] . '</descripcion></error>';
                }

                $cuerpo .= '</errores>';
            }

            $cuerpo .= '</remito>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<TBCOMPROBANTE>'
            . '<cuitEmpresa>30111111118</cuitEmpresa>'
            . '<numeroComprobante>' . $numero . '</numeroComprobante>'
            . '<nombreArchivo>TB_30111111118_000002_20060716_000183.txt</nombreArchivo>'
            . '<codigoIntegridad>d41d8cd98f00b204e9800998ecf8427e</codigoIntegridad>'
            . '<validacionesRemitos class="list">' . $cuerpo . '</validacionesRemitos>'
            . '</TBCOMPROBANTE>';
    }

    public function test_lee_una_transaccion_aceptada_con_un_remito_procesado(): void
    {
        $resultado = $this->parser->parse($this->comprobante([
            ['numeroUnico' => '91 R000100000001', 'procesado' => 'SI'],
        ]));

        $this->assertTrue($resultado->exitoso);
        $this->assertSame('123456789', $resultado->numeroComprobante);
        $this->assertSame('30111111118', $resultado->cuitEmpresa);
        $this->assertSame('d41d8cd98f00b204e9800998ecf8427e', $resultado->codigoIntegridad);
        $this->assertSame('TB_30111111118_000002_20060716_000183.txt', $resultado->nombreArchivo);
        $this->assertCount(1, $resultado->remitos);
        $this->assertTrue($resultado->remito('91 R000100000001')->procesado);
        $this->assertTrue($resultado->todosProcesados());
    }

    /**
     * El instructivo es explícito: los remitos de un archivo se procesan
     * independientemente. Un rechazo no invalida a los demás.
     */
    public function test_lee_un_archivo_con_un_remito_aceptado_y_uno_rechazado(): void
    {
        $resultado = $this->parser->parse($this->comprobante([
            ['numeroUnico' => '91 R000100000001', 'procesado' => 'SI'],
            [
                'numeroUnico' => '91 R000100000002',
                'procesado' => 'NO',
                'errores' => [['21', 'El campo CODIGO_UNICO es inválido o inexistente.']],
            ],
        ]));

        $this->assertTrue($resultado->exitoso);
        $this->assertFalse($resultado->todosProcesados());
        $this->assertCount(1, $resultado->remitosProcesados());
        $this->assertCount(1, $resultado->remitosRechazados());

        $rechazado = $resultado->remito('91 R000100000002');

        $this->assertFalse($rechazado->procesado);
        $this->assertSame(['21'], $rechazado->codigosDeError());
        $this->assertStringContainsString('CODIGO_UNICO', $rechazado->descripcionDeErrores());
    }

    /**
     * Los códigos 11 y 17 llegan como error pero significan que el remito ya
     * estaba emitido. Si se trataran como fallo, el reintento produciría un
     * duplicado.
     */
    public function test_reconoce_un_remito_que_ya_estaba_emitido(): void
    {
        $resultado = $this->parser->parse($this->comprobante([
            [
                'numeroUnico' => '91 R000100000001',
                'procesado' => 'NO',
                'errores' => [['11', 'El archivo recibido ya fue procesado con anterioridad.']],
            ],
            [
                'numeroUnico' => '91 R000100000002',
                'procesado' => 'NO',
                'errores' => [['17', 'El remito ya fue procesado con anterioridad.']],
            ],
        ]));

        $this->assertTrue($resultado->remito('91 R000100000001')->yaEstabaEmitido());
        $this->assertTrue($resultado->remito('91 R000100000002')->yaEstabaEmitido());
        $this->assertCount(2, $resultado->remitosYaEmitidos());
        $this->assertSame([], $resultado->remitosRechazados());
        $this->assertTrue($resultado->todosProcesados());
    }

    /**
     * Un remito con el 11 y además un error real NO está emitido: hay que
     * mirar todos los códigos, no el primero.
     */
    public function test_no_da_por_emitido_un_remito_que_ademas_tiene_un_error_real(): void
    {
        $resultado = $this->parser->parse($this->comprobante([
            [
                'numeroUnico' => '91 R000100000001',
                'procesado' => 'NO',
                'errores' => [
                    ['11', 'El archivo recibido ya fue procesado con anterioridad.'],
                    ['43', 'El campo CANTIDAD_TOTAL_REMITOS es inválido o inexistente.'],
                ],
            ],
        ]));

        $this->assertFalse($resultado->remito('91 R000100000001')->yaEstabaEmitido());
        $this->assertCount(1, $resultado->remitosRechazados());
    }

    public function test_lee_un_error_de_transaccion(): void
    {
        $resultado = $this->parser->parse(
            '<?xml version="1.0"?><TBError>'
            . '<tipoError>DATO</tipoError>'
            . '<codigoError>02</codigoError>'
            . '<mensajeError>El usuario y/o la clave son inválidos.</mensajeError>'
            . '</TBError>'
        );

        $this->assertFalse($resultado->exitoso);
        $this->assertSame('DATO', $resultado->errorTipo);
        $this->assertSame('02', $resultado->errorCodigo);
        $this->assertStringContainsString('inválidos', $resultado->errorMensaje);
        $this->assertSame([], $resultado->remitos);
    }

    /**
     * Si ARBA manda el código y se olvida la descripción, el catálogo local la
     * traduce igual: el usuario no tiene que ver un error sin explicación.
     */
    public function test_traduce_el_codigo_de_error_cuando_ariba_no_manda_descripcion(): void
    {
        $resultado = $this->parser->parse(
            '<TBError><tipoError>DATO</tipoError><codigoError>10</codigoError></TBError>'
        );

        $this->assertFalse($resultado->exitoso);
        $this->assertSame('El nombre del archivo recibido es incorrecto.', $resultado->errorMensaje);
    }

    public function test_una_respuesta_vacia_no_revienta(): void
    {
        $resultado = $this->parser->parse('   ');

        $this->assertFalse($resultado->exitoso);
        $this->assertStringContainsString('respuesta vacía', strtolower($resultado->errorMensaje));
    }

    /**
     * Lo que devuelve un proxy o un portal cautivo suele ser HTML. Ojo: un HTML
     * bien formado ES un XML válido, así que no cae por "XML inválido" sino por
     * raíz desconocida. Las dos situaciones tienen que llegar como mensaje
     * entendible y con el cuerpo guardado.
     */
    public function test_una_respuesta_html_no_revienta(): void
    {
        $html = '<html><head><title>502 Bad Gateway</title></head><body>nginx</body></html>';

        $resultado = $this->parser->parse($html);

        $this->assertFalse($resultado->exitoso);
        $this->assertStringContainsString('estructura inesperada', $resultado->errorMensaje);
        $this->assertStringContainsString('502 Bad Gateway', $resultado->errorMensaje);
        $this->assertSame($html, $resultado->cuerpoCrudo);
    }

    public function test_una_respuesta_ilegible_no_revienta(): void
    {
        $basura = '<html><body>sin cerrar y sin sentido';

        $resultado = $this->parser->parse($basura);

        $this->assertFalse($resultado->exitoso);
        $this->assertStringContainsString('no es un XML válido', $resultado->errorMensaje);
        $this->assertSame($basura, $resultado->cuerpoCrudo);
    }

    public function test_una_raiz_desconocida_se_reporta_como_estructura_inesperada(): void
    {
        $resultado = $this->parser->parse('<CUALQUIERA><algo>1</algo></CUALQUIERA>');

        $this->assertFalse($resultado->exitoso);
        $this->assertStringContainsString('estructura inesperada', $resultado->errorMensaje);
        $this->assertStringContainsString('CUALQUIERA', $resultado->errorMensaje);
    }

    /**
     * Algún día ARBA puede envolver la respuesta (un sobre SOAP, por ejemplo).
     * Se tolera buscando el comprobante en todo el documento, sin importar los
     * namespaces, en lugar de fallar.
     */
    public function test_tolera_una_respuesta_envuelta_en_soap(): void
    {
        // Sin la declaracion XML de apertura: adentro de un sobre no es valida.
        $comprobante = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->comprobante([
            ['numeroUnico' => '91 R000100000001', 'procesado' => 'SI'],
        ]));

        $resultado = $this->parser->parse(
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>' . $comprobante . '</soap:Body>'
            . '</soap:Envelope>'
        );

        $this->assertTrue($resultado->exitoso);
        $this->assertSame('123456789', $resultado->numeroComprobante);
        $this->assertTrue($resultado->remito('91 R000100000001')->procesado);
    }

    public function test_un_remito_sin_bloque_de_errores_queda_sin_errores(): void
    {
        $resultado = $this->parser->parse(
            '<TBCOMPROBANTE><numeroComprobante>1</numeroComprobante><validacionesRemitos>'
            . '<remito><numeroUnico>91 R000100000001</numeroUnico><procesado>SI</procesado></remito>'
            . '</validacionesRemitos></TBCOMPROBANTE>'
        );

        $this->assertSame([], $resultado->remito('91 R000100000001')->errores);
    }

    public function test_un_remito_sin_numero_unico_se_ignora_en_lugar_de_romper(): void
    {
        $resultado = $this->parser->parse(
            '<TBCOMPROBANTE><numeroComprobante>1</numeroComprobante><validacionesRemitos>'
            . '<remito><procesado>SI</procesado></remito>'
            . '<remito><numeroUnico>91 R000100000001</numeroUnico><procesado>SI</procesado></remito>'
            . '</validacionesRemitos></TBCOMPROBANTE>'
        );

        $this->assertCount(1, $resultado->remitos);
        $this->assertNotNull($resultado->remito('91 R000100000001'));
    }

    public function test_consulta_un_remito_que_no_vino_devuelve_null(): void
    {
        $resultado = $this->parser->parse($this->comprobante([
            ['numeroUnico' => '91 R000100000001', 'procesado' => 'SI'],
        ]));

        $this->assertNull($resultado->remito('no existe'));
    }
}
