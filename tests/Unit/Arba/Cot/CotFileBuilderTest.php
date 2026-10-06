<?php

namespace Tests\Unit\Arba\Cot;

use App\Src\Arba\Cot\CotFileBuilder;
use App\Src\Arba\Cot\CotItemData;
use PHPUnit\Framework\TestCase;

/**
 * El generador del archivo TXT es la pieza donde un error no se ve: un pipe
 * corrido o un decimal mal puesto no rompe nada, produce un archivo que ARBA
 * rechaza o, peor, interpreta distinto.
 *
 * Este test fija dos cosas contra el instructivo:
 *   - el orden exacto de los 44 campos del registro 02 y de los 8 del 03;
 *   - que las cantidades y el importe viajen con dos decimales implícitos.
 *
 * El ejemplo del instructivo que se replica:
 *   01|30111111118
 *   02|20060628|91 R000100000001|20060628|...
 *   03|1|3|2|...
 *   04|2
 */
class CotFileBuilderTest extends TestCase
{
    private const CUIT_EMPRESA = CotRemitoFactory::CUIT_EMPRESA;

    public function test_genera_el_archivo_con_los_cuatro_registros(): void
    {
        $archivo = (new CotFileBuilder())->build(self::CUIT_EMPRESA, [CotRemitoFactory::valid()]);

        $esperado = implode("\n", [
            '01|30111111118',
            '02|20060628|91 R000100000001|20060628|0830|E|0|||20111111112|CLIENTE SA|0|AV CORRIENTES|1234||3|B|CENTRO|1043|CABA|C||SI|30111111118|EMPRESA SA|1|CALLE FALSA|742|||||1900|LA PLATA|B|30111111118|U|LA PLATA||RP 11|AB123CD||0|100000',
            '03|110100|K|20000|P001|HARINA 000|KILO|20000',
            '04|1',
            '',
        ]);

        $this->assertSame($esperado, $archivo);
    }

    public function test_cada_registro_termina_con_fin_de_linea_incluido_el_ultimo(): void
    {
        $archivo = (new CotFileBuilder())->build(self::CUIT_EMPRESA, [CotRemitoFactory::valid()]);

        $this->assertStringEndsWith("04|1\n", $archivo);
        $this->assertSame(4, substr_count($archivo, "\n"));
    }

    public function test_el_registro_02_tiene_44_campos_y_el_03_tiene_8(): void
    {
        $lineas = (new CotFileBuilder())->buildLines(self::CUIT_EMPRESA, [CotRemitoFactory::valid()]);

        $this->assertCount(44, explode('|', $lineas[1]));
        $this->assertCount(8, explode('|', $lineas[2]));
        $this->assertCount(2, explode('|', $lineas[0]));
        $this->assertCount(2, explode('|', $lineas[3]));
    }

    /**
     * El instructivo dice que las longitudes son máximas y que no hace falta
     * completar con blancos ni con ceros. Un campo corto tiene que viajar corto.
     */
    public function test_no_rellena_los_campos_con_blancos_ni_ceros(): void
    {
        $campos = explode('|', (new CotFileBuilder())->buildLines(self::CUIT_EMPRESA, [CotRemitoFactory::valid()])[1]);

        $this->assertSame('1234', $campos[13]);
        $this->assertSame('CABA', $campos[19]);
        $this->assertSame('', $campos[14]);
    }

    /**
     * El error más silencioso del formato: el punto decimal. Si la cantidad 200
     * se manda como "200", ARBA la lee como 2,00.
     */
    public function test_las_cantidades_y_el_importe_van_con_dos_decimales_implicitos(): void
    {
        $lineas = (new CotFileBuilder())->buildLines(self::CUIT_EMPRESA, [CotRemitoFactory::valid()]);

        $campos = explode('|', $lineas[1]);
        $producto = explode('|', $lineas[2]);

        $this->assertSame('20000', $producto[3]);
        $this->assertSame('20000', $producto[7]);
        $this->assertSame('100000', $campos[43]);
        $this->assertStringNotContainsString('.', $lineas[1]);
    }

    public function test_una_cantidad_con_decimales_conserva_los_centavos(): void
    {
        $remito = CotRemitoFactory::valid([
            'items' => [CotRemitoFactory::item(['cantidad' => 1.5, 'cantidadAjustada' => 0.01])],
        ]);

        $producto = explode('|', (new CotFileBuilder())->buildLines(self::CUIT_EMPRESA, [$remito])[2]);

        $this->assertSame('150', $producto[3]);
        $this->assertSame('001', $producto[7]);
    }

    public function test_cada_remito_lleva_sus_productos_inmediatamente_despues(): void
    {
        $lineas = (new CotFileBuilder())->buildLines(self::CUIT_EMPRESA, [
            CotRemitoFactory::valid(),
            CotRemitoFactory::valid(),
        ]);

        $tipos = array_map(fn (string $linea) => explode('|', $linea)[0], $lineas);

        $this->assertSame(['01', '02', '03', '02', '03', '04'], $tipos);
    }

    public function test_el_footer_informa_la_cantidad_de_remitos_no_de_registros(): void
    {
        $lineas = (new CotFileBuilder())->buildLines(self::CUIT_EMPRESA, [
            CotRemitoFactory::valid(),
            CotRemitoFactory::valid(),
            CotRemitoFactory::valid(),
        ]);

        $this->assertSame('04|3', end($lineas));
    }

    public function test_los_booleanos_del_registro_02_van_como_cero_o_uno(): void
    {
        $remito = CotRemitoFactory::valid([
            'destinatarioConsumidorFinal' => true,
            'destinatarioTipoDocumento' => 'DNI',
            'destinatarioDocumento' => '12345678',
            'destinatarioTenedor' => false,
            'emisorTenedor' => false,
            'productoNoTermDev' => true,
            'entregaDomicilioOrigen' => false,
        ]);

        $campos = explode('|', (new CotFileBuilder())->buildLines(self::CUIT_EMPRESA, [$remito])[1]);

        $this->assertSame('1', $campos[6]);
        $this->assertSame('DNI', $campos[7]);
        $this->assertSame('12345678', $campos[8]);
        $this->assertSame('0', $campos[11]);
        $this->assertSame('NO', $campos[22]);
        $this->assertSame('0', $campos[25]);
        $this->assertSame('1', $campos[42]);
    }

    public function test_soporta_mas_de_un_producto_por_remito(): void
    {
        $remito = CotRemitoFactory::valid([
            'items' => [CotRemitoFactory::item(), CotRemitoFactory::item(['codigoUnicoProducto' => '110200'])],
        ]);

        $lineas = (new CotFileBuilder())->buildLines(self::CUIT_EMPRESA, [$remito]);
        $tipos = array_map(fn (string $linea) => explode('|', $linea)[0], $lineas);

        $this->assertSame(['01', '02', '03', '03', '04'], $tipos);
    }

    public function test_rechaza_un_archivo_sin_remitos(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CotFileBuilder())->build(self::CUIT_EMPRESA, []);
    }

    /**
     * Sin registro 03 el archivo se rechaza con el error 46 (o el 15). Se corta
     * al armar en lugar de gastar el envío.
     */
    public function test_rechaza_un_remito_sin_productos(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CotFileBuilder())->build(self::CUIT_EMPRESA, [CotRemitoFactory::valid(['items' => []])]);
    }

    public function test_rechaza_un_cuit_de_empresa_invalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CotFileBuilder())->build('3011111', [CotRemitoFactory::valid()]);
    }

    /**
     * Las planillas suelen traer los códigos como número y perder los ceros a la
     * izquierda. El capítulo (los dos primeros dígitos) tiene que sobrevivir.
     */
    public function test_el_item_completa_con_ceros_a_la_izquierda_el_codigo_de_producto(): void
    {
        $item = CotItemData::fromArray(['codigo_unico_producto' => 20100]);

        $this->assertSame('020100', $item->codigoUnicoProducto);
    }
}
