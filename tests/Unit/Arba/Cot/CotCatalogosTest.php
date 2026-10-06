<?php

namespace Tests\Unit\Arba\Cot;

use App\Src\Arba\Cot\CotProvincias;
use App\Src\Arba\Cot\NomencladorEnMemoria;
use App\Src\Arba\Cot\NomencladorSqlParser;
use PHPUnit\Framework\TestCase;

/**
 * Los catálogos de ARBA que usa el COT.
 *
 * El caso más delicado es NomencladorSqlParser: el script lo genera ARBA, no
 * nosotros, y sus descripciones están llenas de comas, paréntesis (por ejemplo
 * "UNIDAD(ES)") y puntos. Un parser con expresiones regulares se rompe con la
 * primera de esas y produce un catálogo incompleto sin que nadie lo note.
 */
class CotCatalogosTest extends TestCase
{
    /**
     * El script que publica ARBA, tal como se versiona en la aplicación.
     */
    private function scriptReal(): string
    {
        $path = __DIR__ . '/../../../../database/data/arba_nomenclador.sql';

        $this->assertFileExists($path, 'Falta el script del nomenclador de ARBA.');

        return (string) file_get_contents($path);
    }

    public function test_la_tabla_de_provincias_tiene_las_veinticuatro_de_arba(): void
    {
        $this->assertCount(24, CotProvincias::PROVINCIAS);
    }

    /**
     * El error 90 de ARBA exige que una de las dos puntas sea Buenos Aires, y su
     * código es 'B'. Si esta letra cambiara, la validación pasaría a rechazar
     * todo.
     */
    public function test_buenos_aires_es_la_letra_b(): void
    {
        $this->assertSame('Buenos Aires', CotProvincias::descripcion('B'));
        $this->assertTrue(CotProvincias::existe('B'));
    }

    public function test_no_usa_las_letras_i_ni_o(): void
    {
        $this->assertFalse(CotProvincias::existe('I'));
        $this->assertFalse(CotProvincias::existe('O'));
    }

    public function test_acepta_el_codigo_de_provincia_en_minuscula_y_con_espacios(): void
    {
        $this->assertTrue(CotProvincias::existe(' b '));
        $this->assertSame('Santa Fe', CotProvincias::descripcion('s'));
    }

    public function test_rechaza_codigos_de_provincia_que_no_existen(): void
    {
        $this->assertFalse(CotProvincias::existe('Ñ'));
        $this->assertFalse(CotProvincias::existe(''));
        $this->assertFalse(CotProvincias::existe(null));
        $this->assertNull(CotProvincias::descripcion('Zz'));
    }

    public function test_los_codigos_de_provincia_son_de_un_solo_caracter(): void
    {
        foreach (CotProvincias::codigos() as $codigo) {
            $this->assertSame(1, strlen($codigo), "El código '{$codigo}' no es de un carácter.");
        }
    }

    /**
     * Las comas y los paréntesis dentro de una cadena no separan campos.
     */
    public function test_el_parser_no_se_rompe_con_comas_ni_parentesis_en_las_descripciones(): void
    {
        $sql = "INSERT INTO tabla (a, b, c, d, e, f) VALUES ('01', NULL, 1, 'CAPITULO', 'NIVEL(ES), COSAS', NULL);";

        $filas = NomencladorSqlParser::filas($sql);

        $this->assertCount(1, $filas);
        $this->assertSame('NIVEL(ES), COSAS', $filas[0]['descripcion']);
        $this->assertSame('CAPITULO', $filas[0]['tipo']);
    }

    /**
     * El escape de la comilla simple en el script de ARBA es '' (dos comillas),
     * no la barra invertida.
     */
    public function test_el_parser_entiende_la_comilla_escapada(): void
    {
        $sql = "VALUES ('0101', NULL, 2, 'RUBRO', 'CARNE D''ANIMAL, FRESCA', NULL);";

        $tuplas = NomencladorSqlParser::tuplas($sql);

        $this->assertCount(1, $tuplas);
        $this->assertSame("CARNE D'ANIMAL, FRESCA", $tuplas[0][4]);
    }

    public function test_el_parser_ignora_los_parentesis_que_no_son_filas(): void
    {
        $sql = <<<'SQL'
        CREATE TABLE arba_nomenclador_tb (
            codigo VARCHAR(8) NOT NULL PRIMARY KEY,
            nivel SMALLINT NOT NULL,
            tipo VARCHAR(12) NOT NULL CHECK (tipo IN ('CAPITULO', 'RUBRO', 'VARIEDAD'))
        );
        INSERT INTO arba_nomenclador_tb (codigo, codigo_padre, nivel, tipo, descripcion, unidad_medida) VALUES
            ('01', NULL, 1, 'CAPITULO', 'ANIMALES VIVOS', NULL);
        SQL;

        $filas = NomencladorSqlParser::filas($sql);

        $this->assertCount(1, $filas);
        $this->assertSame('01', $filas[0]['codigo']);
        $this->assertNull($filas[0]['codigo_padre']);
        $this->assertSame(1, $filas[0]['nivel']);
        $this->assertSame('CAPITULO', $filas[0]['tipo']);
    }

    public function test_lee_completo_el_script_real_de_arba(): void
    {
        $filas = NomencladorSqlParser::filas($this->scriptReal());
        $porTipo = array_count_values(array_column($filas, 'tipo'));

        $this->assertSame(7340, count($filas));
        $this->assertSame(96, $porTipo['CAPITULO']);
        $this->assertSame(2098, $porTipo['RUBRO']);
        $this->assertSame(5146, $porTipo['VARIEDAD']);
    }

    /**
     * Los códigos que van al registro 03 son los del nivel VARIEDAD: son los
     * únicos de seis dígitos.
     */
    public function test_los_productos_del_nomenclador_son_de_seis_digitos(): void
    {
        $productos = array_filter(
            NomencladorSqlParser::filas($this->scriptReal()),
            fn (array $f) => $f['tipo'] === 'VARIEDAD'
        );

        foreach ($productos as $producto) {
            $this->assertSame(6, strlen($producto['codigo']), "El producto {$producto['codigo']} no tiene 6 dígitos.");
        }
    }

    public function test_ningun_producto_del_script_queda_huerfano(): void
    {
        $filas = NomencladorSqlParser::filas($this->scriptReal());
        $codigos = array_flip(array_column($filas, 'codigo'));

        foreach ($filas as $fila) {
            if ($fila['codigo_padre'] === null) {
                continue;
            }

            $this->assertArrayHasKey(
                $fila['codigo_padre'],
                $codigos,
                "El código {$fila['codigo']} apunta a un padre inexistente ({$fila['codigo_padre']})."
            );
        }
    }

    /**
     * La tabla tiene una FK auto-referencial, así que el orden de inserción no
     * es un detalle: si un hijo va antes que su padre, la importación falla.
     */
    public function test_el_orden_deja_los_padres_antes_que_los_hijos(): void
    {
        $sql = <<<'SQL'
        VALUES
            ('010100', '0101', 3, 'VARIEDAD', 'CABALLOS', 'UNIDAD(ES)'),
            ('01', NULL, 1, 'CAPITULO', 'ANIMALES VIVOS', NULL),
            ('0101', '01', 2, 'RUBRO', 'CABALLOS, ASNOS', NULL);
        SQL;

        $ordenadas = NomencladorSqlParser::ordenarPadresPrimero(NomencladorSqlParser::filas($sql));

        $this->assertSame(['01', '0101', '010100'], array_column($ordenadas, 'codigo'));
    }

    public function test_el_indice_de_la_base_respeta_el_orden_de_los_padres(): void
    {
        $filas = NomencladorSqlParser::ordenarPadresPrimero(NomencladorSqlParser::filas($this->scriptReal()));

        $vistos = [];
        foreach ($filas as $fila) {
            if ($fila['codigo_padre'] !== null) {
                $this->assertArrayHasKey(
                    $fila['codigo_padre'],
                    $vistos,
                    "El código {$fila['codigo']} aparece antes que su padre {$fila['codigo_padre']}."
                );
            }

            $vistos[$fila['codigo']] = true;
        }
    }

    public function test_el_nomenclador_en_memoria_contesta_lo_esperado(): void
    {
        $nomenclador = new NomencladorEnMemoria(['110100', '  110200  ']);

        $this->assertTrue($nomenclador->existeProducto('110100'));
        $this->assertTrue($nomenclador->existeProducto('110200'));
        $this->assertFalse($nomenclador->existeProducto('999999'));
        $this->assertFalse($nomenclador->existeProducto(''));
    }

    public function test_el_nomenclador_en_memoria_se_puede_armar_desde_las_filas_del_script(): void
    {
        $filas = NomencladorSqlParser::filas($this->scriptReal());
        $nomenclador = NomencladorEnMemoria::desdeFilas($filas);

        $this->assertTrue($nomenclador->existeProducto('110100'));
        $this->assertTrue($nomenclador->existeProducto('010100'));
        $this->assertFalse($nomenclador->existeProducto('123456'));
    }
}
