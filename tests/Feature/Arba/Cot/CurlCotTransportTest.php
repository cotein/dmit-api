<?php

namespace Tests\Feature\Arba\Cot;

use App\Src\Arba\Cot\CotEmisionService;
use App\Src\Arba\Cot\Transport\CotTransport;
use App\Src\Arba\Cot\Transport\CurlCotTransport;
use Tests\TestCase;

/**
 * Prueba el cliente cURL de verdad, contra un servidor HTTP local.
 *
 * El resto del módulo se prueba con un transporte falso, y eso está bien, pero
 * deja sin verificar justo la parte que no se puede probar sin una CIT: que el
 * multipart salga bien armado y que las credenciales lleguen.
 *
 * El riesgo concreto es CURLStringFile, la pieza que manda el TXT como archivo
 * sin escribirlo a disco. Si el multipart se armara mal, ARBA contestaría el
 * error 56 ("el parámetro FILE es inexistente") y desde el sistema se vería
 * como un problema de ARBA.
 *
 * Se levanta `php -S` en un puerto libre y un receptor que deja lo recibido en
 * un JSON. Si el servidor no arranca, el test se saltea en lugar de fallar: no
 * queremos que un entorno sin la CLI de PHP rompa la suite.
 */
class CurlCotTransportTest extends TestCase
{
    private ?int $puerto = null;

    /** @var resource|null */
    private $proceso = null;

    private string $directorio = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->directorio = sys_get_temp_dir() . '/cot-transporte-' . uniqid();

        if (! mkdir($this->directorio, 0777, true) && ! is_dir($this->directorio)) {
            $this->markTestSkipped('No se pudo crear el directorio temporal.');
        }

        $this->escribirReceptor();

        if (! $this->levantarServidor()) {
            $this->markTestSkipped('No se pudo levantar el servidor local (¿falta la CLI de PHP?).');
        }
    }

    protected function tearDown(): void
    {
        if (is_resource($this->proceso)) {
            proc_terminate($this->proceso);
            proc_close($this->proceso);
        }

        if ($this->directorio !== '' && is_dir($this->directorio)) {
            foreach (glob($this->directorio . '/*') ?: [] as $archivo) {
                @unlink($archivo);
            }

            @rmdir($this->directorio);
        }

        parent::tearDown();
    }

    public function test_envia_el_archivo_y_las_credenciales_como_multipart(): void
    {
        (new CurlCotTransport(timeout: 10, connectTimeout: 5))->post(
            $this->url(),
            ['user' => 'usuario-cit', 'password' => 'clave-cit'],
            ['file' => ['nombre' => 'TB_30111111118_000002_20060716_000183.txt', 'contenido' => "01|30111111118\n04|1\n"]]
        );

        $recibido = $this->recibido();

        $this->assertSame('POST', $recibido['method']);
        $this->assertStringContainsString('multipart/form-data', $recibido['content_type']);

        $this->assertSame('usuario-cit', $recibido['post']['user']);
        $this->assertSame('clave-cit', $recibido['post']['password']);

        $this->assertArrayHasKey('file', $recibido['files']);
        $this->assertSame('TB_30111111118_000002_20060716_000183.txt', $recibido['files']['file']['name']);
        $this->assertSame("01|30111111118\n04|1\n", $recibido['files']['file']['contenido']);
    }

    public function test_devuelve_el_cuerpo_y_el_status_de_la_respuesta(): void
    {
        $respuesta = (new CurlCotTransport(timeout: 10, connectTimeout: 5))->post($this->url(), ['user' => 'u']);

        $this->assertTrue($respuesta->fueExitosa());
        $this->assertSame(200, $respuesta->status);
        $this->assertStringContainsString('<TBCOMPROBANTE>', $respuesta->cuerpo);
        $this->assertFalse($respuesta->falloElTransporte());
    }

    /**
     * Un puerto sin nada escuchando tiene que volver como fallo de transporte y
     * no como excepción: es la diferencia entre "ARBA no contestó" (reintentar)
     * y "ARBA contestó un rechazo" (corregir).
     */
    public function test_un_endpoint_inalcanzable_devuelve_fallo_de_transporte(): void
    {
        $respuesta = (new CurlCotTransport(timeout: 2, connectTimeout: 2))
            ->post('http://127.0.0.1:1/no-existe', ['user' => 'u']);

        $this->assertFalse($respuesta->fueExitosa());
        $this->assertTrue($respuesta->falloElTransporte());
        $this->assertNotNull($respuesta->errorTransporte);
        $this->assertNull($respuesta->status);
    }

    public function test_no_expone_la_clave_en_el_resumen(): void
    {
        $respuesta = (new CurlCotTransport(timeout: 10, connectTimeout: 5))->post($this->url(), ['password' => 'clave-secreta']);

        $this->assertStringNotContainsString('clave-secreta', $respuesta->resumen());
    }

    public function test_el_container_resuelve_el_transporte_de_la_configuracion(): void
    {
        $this->assertSame(CurlCotTransport::class, config('cot.transport'));
        $this->assertInstanceOf(CurlCotTransport::class, app(CotTransport::class));
        $this->assertInstanceOf(CotEmisionService::class, app(CotEmisionService::class));
    }

    public function test_los_endpoints_estan_configurados(): void
    {
        $ruta = '/TransporteBienes/SeguridadCliente/presentarRemitos.do';

        $this->assertStringEndsWith($ruta, config('cot.endpoints.testing'));
        $this->assertStringEndsWith($ruta, config('cot.endpoints.production'));

        // Producción va por HTTPS: por ahí viaja la clave de la CIT.
        $this->assertStringStartsWith('https://', config('cot.endpoints.production'));
    }

    private function url(string $path = '/presentar'): string
    {
        return 'http://127.0.0.1:' . $this->puerto . $path;
    }

    private function escribirReceptor(): void
    {
        $codigo = <<<'PHP'
        <?php
        $files = [];
        foreach ($_FILES as $campo => $info) {
            $files[$campo] = [
                'name' => $info['name'],
                'size' => $info['size'],
                'contenido' => file_get_contents($info['tmp_name']),
            ];
        }
        file_put_contents(__DIR__ . '/recibido.json', json_encode([
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
            'post' => $_POST,
            'files' => $files,
        ]));
        header('Content-Type: text/xml');
        echo '<TBCOMPROBANTE><numeroComprobante>1</numeroComprobante></TBCOMPROBANTE>';
        PHP;

        file_put_contents($this->directorio . '/receptor.php', $codigo);
    }

    private function levantarServidor(): bool
    {
        for ($intento = 0; $intento < 5; $intento++) {
            $puerto = random_int(20000, 40000);
            $descriptores = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];

            $proceso = proc_open(
                ['php', '-S', '127.0.0.1:' . $puerto, '-t', $this->directorio, $this->directorio . '/receptor.php'],
                $descriptores,
                $pipes
            );

            if (! is_resource($proceso)) {
                return false;
            }

            if ($this->esperarAlPuerto($puerto)) {
                $this->proceso = $proceso;
                $this->puerto = $puerto;

                return true;
            }

            proc_terminate($proceso);
            proc_close($proceso);
        }

        return false;
    }

    private function esperarAlPuerto(int $puerto): bool
    {
        for ($i = 0; $i < 60; $i++) {
            $socket = @fsockopen('127.0.0.1', $puerto, $errno, $errstr, 0.2);

            if (is_resource($socket)) {
                fclose($socket);

                return true;
            }

            usleep(50000);
        }

        return false;
    }

    /**
     * @return array{method: string, content_type: string, post: array<string, string>, files: array<string, array{name: string, size: int, contenido: string}>}
     */
    private function recibido(): array
    {
        $path = $this->directorio . '/recibido.json';

        $this->assertFileExists($path, 'El servidor local no recibió nada.');

        return json_decode((string) file_get_contents($path), true) ?: [];
    }
}
