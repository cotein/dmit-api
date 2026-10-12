<?php

declare(strict_types=1);

namespace Tests\Unit\Afip\Padron100;

use PHPUnit\Framework\TestCase;
use App\Src\Afip\Padron100\ColeccionPadron100;
use App\Src\Afip\Padron100\ColeccionesPadron100;
use App\Src\Afip\Padron100\CredencialesPadron100;
use App\Src\Afip\Padron100\ClienteFixturePadron100;
use App\Src\Afip\Padron100\ClienteSoapPadron100;
use App\Src\Afip\Padron100\ComparadorPadron100;
use App\Src\Afip\Padron100\DestinosPadron100;
use App\Src\Afip\Padron100\Excepciones\Padron100Exception;
use App\Src\Afip\Padron100\ParserPadron100;

/**
 * Integración con ws_sr_padron_a100: lo que se puede probar sin certificado.
 *
 * El servicio no se puede llamar en un test (hace falta el certificado del emisor y el
 * WSDL, que se baja del WSASS al adherir el servicio), así que lo que se prueba acá es:
 * el parseo de la respuesta, el armado del pedido SOAP, el mapeo a las tablas del
 * sistema y la idempotencia de la comparación. Los XML de tests/Fixtures tienen la forma
 * del manual v2.1.
 */
class Padron100Test extends TestCase
{
    private function fixtures(): string
    {
        return __DIR__ . '/../../../Fixtures/Afip/Padron100';
    }

    private function clienteFixture(): ClienteFixturePadron100
    {
        return new ClienteFixturePadron100($this->fixtures());
    }

    /**
     * El manual escribe el campo de la descripción de dos formas: <decription> en el
     * ejemplo de respuesta (§3.2.3) y <description> en el esquema (§3.2.2) y en la tabla
     * de tipos de datos (§4.2). Como el WSDL no es público, no hay forma de saber cuál
     * usa ARCA: hay que leer las dos.
     */
    public function test_lee_la_descripcion_con_las_dos_formas_del_manual(): void
    {
        $conDecription = $this->clienteFixture()->traerColeccion(ColeccionesPadron100::TIPO_DOCUMENTO);
        $conDescription = $this->clienteFixture()->traerColeccion(ColeccionesPadron100::TIPO_CLAVE_IDENTIFICACION);

        $this->assertSame('decription', $conDecription->parametros()[0]->campoDescripcion());
        $this->assertSame('CI POLICIA FEDERAL', $conDecription->parametros()[0]->descripcion());
        $this->assertSame('description', $conDescription->parametros()[0]->campoDescripcion());
        $this->assertSame('CUIT', $conDescription->parametros()[0]->descripcion());
    }

    /**
     * Sin descripción el registro igual sirve (el id y los atributos son los datos), pero
     * tiene que quedar el aviso: una descripción vacía no puede pasar como normal.
     */
    public function test_sin_descripcion_avisa_y_guarda_el_registro_igual(): void
    {
        $coleccion = $this->clienteFixture()->traerColeccion(ColeccionesPadron100::TIPO_EMAIL);

        $this->assertCount(3, $coleccion->parametros());
        $this->assertNull($coleccion->parametros()[2]->descripcion());
        $this->assertNull($coleccion->parametros()[2]->campoDescripcion());
        $this->assertCount(1, $coleccion->avisos());
        $this->assertStringContainsString('sin descripción', $coleccion->avisos()[0]);
    }

    /**
     * El ejemplo del manual pide SUPA.TIPO_TELEFONO y la respuesta vuelve con
     * SUPA.E_PROVINCIA: la colección pedida y la informada tienen que quedar por separado.
     */
    public function test_el_nombre_informado_no_se_asume_igual_al_pedido(): void
    {
        $coleccion = $this->clienteFixture()->traerColeccion('SUPA.TIPO_TELEFONO');

        $this->assertSame('SUPA.TIPO_TELEFONO', $coleccion->solicitada());
        $this->assertSame('SUPA.E_PROVINCIA', $coleccion->informada());
        $this->assertFalse($coleccion->nombreCoincide());
    }

    public function test_lee_los_atributos_y_los_metadata(): void
    {
        $coleccion = $this->clienteFixture()->traerColeccion(ColeccionesPadron100::PROVINCIA);
        $misiones = null;

        foreach ($coleccion->parametros() as $parametro) {
            if ($parametro->id() === '19') {
                $misiones = $parametro;
            }
        }

        $this->assertNotNull($misiones);
        $this->assertSame('MISIONES', $misiones->atributo('NOMBRE_PROVINCIA'));
        $this->assertSame('MI', $misiones->atributo('CODIGO_SIM_PROVINCIA'));
        $this->assertArrayHasKey('fechaHora', $coleccion->metadata());
    }

    /**
     * El parseo no se ata al prefijo ni al namespace: los elige el servidor.
     */
    public function test_no_depende_del_namespace_ni_del_sobre(): void
    {
        $xml = '<?xml version="1.0"?><x><parameterCollectionReturn><parameterCollection><name>E_SEXO</name>'
            . '<parameterList><id>M</id><decription>MASCULINO</decription></parameterList>'
            . '</parameterCollection></parameterCollectionReturn></x>';

        $coleccion = (new ParserPadron100())->parsear($xml, 'E_SEXO');

        $this->assertSame('E_SEXO', $coleccion->informada());
        $this->assertSame('MASCULINO', $coleccion->parametros()[0]->descripcion());
    }

    /**
     * Un SOAP Fault de ARCA tiene que salir como excepción propia y con el motivo.
     */
    public function test_un_fault_de_arca_corta_con_el_motivo(): void
    {
        $xml = '<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body><soap:Fault><faultcode>soap:Server</faultcode>'
            . '<faultstring>Collection not found</faultstring></soap:Fault></soap:Body></soap:Envelope>';

        $this->expectException(Padron100Exception::class);
        $this->expectExceptionMessage('Collection not found');

        (new ParserPadron100())->parsear($xml, 'SUPA.TIPO_DOCUMENTO');
    }

    /**
     * El pedido tiene que ser el del manual: los cuatro campos y el sobre SOAP 1.1 con el
     * namespace del servicio. Y no puede llevar nada del certificado.
     */
    public function test_arma_el_pedido_del_manual(): void
    {
        $cliente = new ClienteSoapPadron100($this->credenciales('TOKEN+1=2', 'FIRMA==', '20227339730'), 'testing');
        $pedido = $cliente->armarPedido('SUPA.TIPO_DOCUMENTO');

        $this->assertStringContainsString('<token>TOKEN+1=2</token>', $pedido);
        $this->assertStringContainsString('<sign>FIRMA==</sign>', $pedido);
        $this->assertStringContainsString('<cuitRepresentada>20227339730</cuitRepresentada>', $pedido);
        $this->assertStringContainsString('<collectionName>SUPA.TIPO_DOCUMENTO</collectionName>', $pedido);
        $this->assertStringContainsString('http://schemas.xmlsoap.org/soap/envelope/', $pedido);
        $this->assertStringContainsString(ClienteSoapPadron100::NAMESPACE_A100, $pedido);
        $this->assertStringNotContainsString('PRIVATE KEY', $pedido);
        $this->assertStringNotContainsString('CERTIFICATE', $pedido);
    }

    /**
     * Sin ticket del WSAA (el caso de un certificado mal puesto) el error tiene que
     * explicarlo, sin volcar credenciales ni rutas del certificado.
     */
    public function test_sin_ticket_el_error_es_claro_y_no_filtra_credenciales(): void
    {
        $cliente = new ClienteSoapPadron100($this->credenciales('', '', '20227339730'), 'testing');

        $this->expectException(Padron100Exception::class);
        $this->expectExceptionMessage('No hay ticket de acceso del WSAA para ws_sr_padron_a100');

        $cliente->traerColeccion(ColeccionesPadron100::TIPO_DOCUMENTO);
    }

    /**
     * El id de una colección es la clave del parámetro, y la comparación no puede contar
     * como cambio lo que es igual: si contara, la sincronización nunca sería idempotente.
     */
    public function test_la_comparacion_de_parametros_es_idempotente(): void
    {
        $comparador = new ComparadorPadron100();
        $coleccion = $this->clienteFixture()->traerColeccion(ColeccionesPadron100::TIPO_DOCUMENTO);

        $primera = $comparador->compararParametros('SUPA.TIPO_DOCUMENTO', $coleccion->parametros(), [], '2026-01-01 10:00:00');
        $this->assertCount(38, $primera['nuevos']);
        $this->assertSame(0, $primera['sinCambios']);

        // Lo guardado es lo que acaba de escribir el almacén: mismos datos, otro momento.
        $existentes = [];
        foreach ($primera['nuevos'] as $fila) {
            $existentes[$fila['parametro_id']] = [
                'descripcion' => $fila['descripcion'],
                'atributos' => json_decode($fila['atributos'], true),
            ];
        }

        $segunda = $comparador->compararParametros('SUPA.TIPO_DOCUMENTO', $coleccion->parametros(), $existentes, '2026-02-02 10:00:00');

        $this->assertSame([], $segunda['nuevos']);
        $this->assertSame([], $segunda['actualizados']);
        $this->assertSame(38, $segunda['sinCambios']);
    }

    /**
     * El orden de las claves del JSON no puede decidir si un registro cambió: MySQL
     * reordena las claves de un objeto al guardarlo en una columna json.
     */
    public function test_el_orden_de_las_claves_del_json_no_cuenta_como_cambio(): void
    {
        $comparador = new ComparadorPadron100();
        $coleccion = $this->clienteFixture()->traerColeccion(ColeccionesPadron100::TIPO_DOCUMENTO);
        $primero = $coleccion->parametros()[0];

        $existentes = [
            $primero->id() => [
                'descripcion' => $primero->descripcion(),
                'atributos' => array_reverse($primero->atributos(), true),
            ],
        ];

        $plan = $comparador->compararParametros('SUPA.TIPO_DOCUMENTO', [$primero], $existentes, '2026-01-01 10:00:00');

        $this->assertSame(1, $plan['sinCambios']);
        $this->assertSame([], $plan['actualizados']);
    }

    /**
     * Los códigos de documento se cargan en afip_documents; las provincias NO se escriben
     * (ya las trae el sistema por la vía de ARBA): sólo se contrastan.
     */
    public function test_el_destino_de_documentos_escribe_y_el_de_provincias_solo_compara(): void
    {
        $documentos = DestinosPadron100::destinoDe(ColeccionesPadron100::TIPO_DOCUMENTO);
        $provincias = DestinosPadron100::destinoDe(ColeccionesPadron100::PROVINCIA);

        $this->assertSame('afip_documents', $documentos['tabla']);
        $this->assertSame(DestinosPadron100::MODO_ESCRITURA, $documentos['modo']);
        $this->assertSame('states', $provincias['tabla']);
        $this->assertSame(DestinosPadron100::MODO_CONTRASTE, $provincias['modo']);

        $coleccion = $this->clienteFixture()->traerColeccion(ColeccionesPadron100::TIPO_DOCUMENTO);
        $cuit = null;

        foreach ($coleccion->parametros() as $parametro) {
            if ($parametro->id() === '80') {
                $cuit = $parametro;
            }
        }

        $this->assertNotNull($cuit);
        $this->assertSame('80', DestinosPadron100::valor($documentos, 'codigo', $cuit));
        $this->assertSame('CUIT', DestinosPadron100::valor($documentos, 'nombre', $cuit));
    }

    /**
     * El contraste de provincias no puede dar falsos positivos por mayúsculas ni acentos:
     * ARCA manda "RIO NEGRO" y la tabla del sistema tiene "Río Negro".
     */
    public function test_el_contraste_de_provincias_ignora_mayusculas_y_acentos(): void
    {
        $contraste = (new ComparadorPadron100())->contrastarProvincias(
            ['19' => 'MISIONES', '22' => 'RIO NEGRO', '23' => 'SANTA CRUZ'],
            [
                '19' => ['id' => 19, 'nombre' => 'Misiones', 'cot_code' => 'N'],
                '22' => ['id' => 22, 'nombre' => 'Río Negro', 'cot_code' => 'R'],
                '23' => ['id' => 23, 'nombre' => 'SANTA CRUZ DE LA SIERRA', 'cot_code' => 'Z'],
                '99' => ['id' => 99, 'nombre' => 'Provincia vieja', 'cot_code' => ''],
            ]
        );

        $this->assertSame(2, $contraste['coinciden']);
        $this->assertCount(1, $contraste['distintas']);
        $this->assertSame('23', $contraste['distintas'][0]['codigo']);
        $this->assertSame(['99'], $contraste['sobrantes']);
        $this->assertSame(0, count($contraste['faltantes']));
    }

    /**
     * El catálogo del Anexo 5.1 queda escrito una sola vez: los nombres que usa la
     * sincronización tienen que estar todos ahí.
     */
    public function test_las_colecciones_por_defecto_estan_en_el_catalogo(): void
    {
        $this->assertNotEmpty(ColeccionesPadron100::POR_DEFECTO);

        foreach (ColeccionesPadron100::POR_DEFECTO as $coleccion) {
            $this->assertTrue(ColeccionesPadron100::existe($coleccion), "Falta {$coleccion} en el catálogo del Anexo 5.1.");
        }

        $this->assertTrue(ColeccionesPadron100::existe(ColeccionesPadron100::TIPO_DOCUMENTO));
    }

    private function credenciales(string $token, string $sign, string $cuit): CredencialesPadron100
    {
        return new class($token, $sign, $cuit) implements CredencialesPadron100 {
            private $token;

            private $sign;

            private $cuit;

            public function __construct(string $token, string $sign, string $cuit)
            {
                $this->token = $token;
                $this->sign = $sign;
                $this->cuit = $cuit;
            }

            public function token(): string
            {
                return $this->token;
            }

            public function sign(): string
            {
                return $this->sign;
            }

            public function cuitRepresentada(): string
            {
                return $this->cuit;
            }
        };
    }

    /**
     * La respuesta del fixture tiene que ser la de una colección conocida: si el archivo
     * quedara con el nombre de otra colección, la sincronización cargaría el catálogo
     * equivocado.
     */
    public function test_los_fixtures_estan_completos(): void
    {
        $disponibles = $this->clienteFixture()->disponibles();

        foreach ([
            ColeccionesPadron100::TIPO_DOCUMENTO,
            ColeccionesPadron100::TIPO_CLAVE_IDENTIFICACION,
            ColeccionesPadron100::TIPO_EMAIL,
            ColeccionesPadron100::PROVINCIA,
        ] as $coleccion) {
            $this->assertContains($coleccion, $disponibles, "Falta el fixture de {$coleccion}.");
        }

        $this->assertInstanceOf(ColeccionPadron100::class, $this->clienteFixture()->traerColeccion(ColeccionesPadron100::PROVINCIA));
    }
}
