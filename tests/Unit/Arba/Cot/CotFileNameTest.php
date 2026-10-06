<?php

namespace Tests\Unit\Arba\Cot;

use App\Src\Arba\Cot\CotFileName;
use PHPUnit\Framework\TestCase;

/**
 * El nombre del archivo es la clave de idempotencia del envío (ARBA responde el
 * error 11 ante un nombre repetido) y además es donde viajan la fecha y el
 * secuencial, que NO van dentro del TXT. Si el armado se desvía, el archivo se
 * rechaza con los errores 51, 52 o 53 sin que el contenido tenga nada malo.
 */
class CotFileNameTest extends TestCase
{
    public function test_arma_el_nombre_con_planta_y_puerta_completadas(): void
    {
        $nombre = CotFileName::build('30111111118', '0', '2', '20060716', '183');

        $this->assertSame('TB_30111111118_000002_20060716_000183.txt', $nombre);
    }

    /**
     * El instructivo publica un ejemplo contradictorio: la tabla define planta
     * 000 y puerta 002, pero el nombre de ejemplo muestra "003002". Se sigue la
     * definición estructural (planta + puerta, 3 + 3 con ceros a la izquierda).
     */
    public function test_no_sigue_el_ejemplo_contradictorio_del_instructivo(): void
    {
        $nombre = CotFileName::build('30111111118', '000', '002', '20060716', '000183');

        $this->assertStringContainsString('_000002_', $nombre);
        $this->assertStringNotContainsString('_003002_', $nombre);
    }

    public function test_normaliza_el_cuit_con_guiones(): void
    {
        $nombre = CotFileName::build('30-11111111-8', '0', '2', '20060716', '183');

        $this->assertSame('TB_30111111118_000002_20060716_000183.txt', $nombre);
    }

    public function test_acepta_la_fecha_en_otros_formatos_y_la_normaliza(): void
    {
        $nombre = CotFileName::build('30111111118', '0', '2', '2006-07-16', '183');

        $this->assertStringContainsString('_20060716_', $nombre);
    }

    public function test_desarma_un_nombre_valido(): void
    {
        $partes = CotFileName::parse('TB_30111111118_000002_20060716_000183.txt');

        $this->assertSame('30111111118', $partes['cuit']);
        $this->assertSame('000', $partes['planta']);
        $this->assertSame('002', $partes['puerta']);
        $this->assertSame('20060716', $partes['fecha']);
        $this->assertSame('000183', $partes['secuencia']);
    }

    public function test_el_armado_y_el_desarmado_son_inversos(): void
    {
        $nombre = CotFileName::build('20111111112', '003', '017', '2026-10-05', '42');

        $partes = CotFileName::parse($nombre);

        $this->assertSame('20111111112', $partes['cuit']);
        $this->assertSame('003', $partes['planta']);
        $this->assertSame('017', $partes['puerta']);
        $this->assertSame('20261005', $partes['fecha']);
        $this->assertSame('000042', $partes['secuencia']);
    }

    public function test_rechaza_un_cuit_que_no_tiene_once_digitos(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CotFileName::build('3011111', '0', '2', '20060716', '183');
    }

    public function test_rechaza_una_secuencia_en_cero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CotFileName::build('30111111118', '0', '2', '20060716', '0');
    }

    public function test_reconoce_un_nombre_invalido_sin_lanzar_excepcion(): void
    {
        $this->assertFalse(CotFileName::isValid('TB_30111111118_20060716.txt'));
        $this->assertTrue(CotFileName::isValid('TB_30111111118_000002_20060716_000183.txt'));
    }

    /**
     * '16/07/2006' sacado por dígitos daría '16072006', que como AAAAMMDD es el
     * 20 de julio de 1607. El formato tiene que decidirse, no adivinarse.
     */
    public function test_interpreta_la_fecha_argentina_como_dia_mes_ano(): void
    {
        $this->assertSame('20060716', CotFileName::normalizeFecha('16/07/2006'));
        $this->assertSame('20060716', CotFileName::normalizeFecha('16-07-2006'));
        $this->assertSame('20060716', CotFileName::normalizeFecha('2006/07/16'));
        $this->assertSame('20060716', CotFileName::normalizeFecha('2006-07-16'));
        $this->assertSame('20060716', CotFileName::normalizeFecha('20060716'));
    }

    public function test_acepta_un_objeto_de_fecha(): void
    {
        $this->assertSame('20060716', CotFileName::normalizeFecha(new \DateTimeImmutable('2006-07-16')));

        $nombre = CotFileName::build('30111111118', '0', '2', new \DateTimeImmutable('2006-07-16'), '183');

        $this->assertStringContainsString('_20060716_', $nombre);
    }

    public function test_rechaza_una_fecha_que_no_existe(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CotFileName::normalizeFecha('31/02/2006');
    }

    public function test_rechaza_un_texto_que_no_es_fecha(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CotFileName::normalizeFecha('cualquier cosa');
    }
}
