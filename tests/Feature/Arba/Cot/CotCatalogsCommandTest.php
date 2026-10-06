<?php

namespace Tests\Feature\Arba\Cot;

use App\Models\ArbaNomenclador;
use App\Models\Company;
use App\Models\Cot;
use App\Models\CotArchivo;
use App\Models\CotUnidadMedida;
use App\Src\Arba\Cot\NomencladorArba;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifica el andamiaje del módulo COT: que las migraciones existan de verdad,
 * que los catálogos se puedan cargar y que la clave de la CIT quede cifrada.
 *
 * El cifrado no es un detalle: la credencial es de ARBA y abre la emisión de
 * remitos en nombre de la empresa. Si se guarda en claro o se serializa, el
 * problema aparece recién cuando ya está en un backup o en un log.
 */
class CotCatalogsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_tablas_del_modulo_cot_existen(): void
    {
        foreach ([
            'arba_nomenclador_tb',
            'cot_archivos',
            'cots',
            'cot_items',
            'cot_unidades_medida',
            'cot_provincias',
            'company_traslado_points',
        ] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}.");
        }

        $this->assertTrue(Schema::hasColumn('states', 'cot_code'));
        $this->assertTrue(Schema::hasColumn('companies', 'arba_cit_user'));
        $this->assertTrue(Schema::hasColumn('companies', 'arba_cit_password'));
        $this->assertTrue(Schema::hasColumn('companies', 'arba_cit_environment'));
    }

    public function test_importa_las_provincias_del_cot(): void
    {
        $this->artisan('cot:import-catalogs', ['tipo' => 'provincias'])->assertExitCode(0);

        $this->assertSame(24, DB::table('cot_provincias')->count());
        $this->assertSame('Buenos Aires', DB::table('cot_provincias')->where('codigo', 'B')->value('descripcion'));
        $this->assertSame('Capital Federal', DB::table('cot_provincias')->where('codigo', 'C')->value('descripcion'));
    }

    public function test_importar_las_provincias_dos_veces_no_duplica(): void
    {
        $this->artisan('cot:import-catalogs', ['tipo' => 'provincias'])->assertExitCode(0);
        $this->artisan('cot:import-catalogs', ['tipo' => 'provincias'])->assertExitCode(0);

        $this->assertSame(24, DB::table('cot_provincias')->count());
    }

    public function test_importa_el_nomenclador_de_arba(): void
    {
        $this->importarNomenclador();

        $this->assertSame(7340, ArbaNomenclador::count());
        $this->assertSame(96, ArbaNomenclador::capitulos()->count());
        $this->assertSame(5146, ArbaNomenclador::productos()->count());
    }

    /**
     * El código que va al registro 03 tiene que ser del nivel VARIEDAD, que es
     * el único de 6 dígitos, y del que cuelga toda la jerarquía.
     */
    public function test_el_nomenclador_importado_tiene_los_productos_de_seis_digitos_con_su_jerarquia(): void
    {
        $this->importarNomenclador();

        $producto = ArbaNomenclador::productos()->where('codigo', '110100')->first();

        $this->assertNotNull($producto);
        $this->assertSame(ArbaNomenclador::VARIEDAD, $producto->tipo);
        $this->assertStringContainsString('HARINA DE TRIGO', $producto->descripcion);
        $this->assertSame('KILOGRAMO(S)', $producto->unidad_medida);

        $this->assertSame('1101', $producto->codigo_padre);
        $this->assertStringStartsWith('PRODUCTOS DE LA MOLINERIA', $producto->padre->padre->descripcion);
        $this->assertSame(ArbaNomenclador::CAPITULO, $producto->padre->padre->tipo);
    }

    public function test_reimportar_el_nomenclador_no_duplica_ni_rompe_la_foreana(): void
    {
        $this->importarNomenclador();
        $this->importarNomenclador();

        $this->assertSame(7340, ArbaNomenclador::count());
    }

    public function test_el_dry_run_no_escribe_el_nomenclador(): void
    {
        $this->artisan('cot:import-catalogs', [
            'tipo' => 'nomenclador',
            '--file' => $this->rutaNomenclador(),
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, ArbaNomenclador::count());
    }

    public function test_el_importador_falla_con_un_mensaje_claro_si_no_pasa_el_archivo(): void
    {
        $this->artisan('cot:import-catalogs', ['tipo' => 'nomenclador'])->assertExitCode(1);
        $this->artisan('cot:import-catalogs', ['tipo' => 'nomenclador', '--file' => '/no/existe.sql'])->assertExitCode(1);
        $this->artisan('cot:import-catalogs', ['tipo' => 'inventado'])->assertExitCode(1);
    }

    public function test_el_importador_rechaza_un_script_sin_variedades(): void
    {
        $archivo = $this->sqlTemporal("VALUES ('01', NULL, 1, 'CAPITULO', 'ANIMALES VIVOS', NULL);");

        $this->artisan('cot:import-catalogs', ['tipo' => 'nomenclador', '--file' => $archivo])->assertExitCode(1);

        @unlink($archivo);
    }

    public function test_el_importador_rechaza_un_script_con_padres_inexistentes(): void
    {
        $archivo = $this->sqlTemporal("VALUES ('010100', '9999', 3, 'VARIEDAD', 'HUERFANO', 'UNIDAD(ES)');");

        $this->artisan('cot:import-catalogs', ['tipo' => 'nomenclador', '--file' => $archivo])->assertExitCode(1);

        @unlink($archivo);
    }

    /**
     * El validador de producción sale a buscar el código en la base: es el que
     * usa el servicio antes de presentar el archivo.
     */
    public function test_el_nomenclador_de_produccion_consulta_la_base(): void
    {
        $this->importarNomenclador();

        $nomenclador = new NomencladorArba();

        $this->assertTrue($nomenclador->existeProducto('110100'));
        $this->assertTrue($nomenclador->existeProducto('010100'));
        $this->assertFalse($nomenclador->existeProducto('999999'));

        // Un capítulo o un rubro NO son productos: sólo la variedad lo es, y es
        // la única con código de seis dígitos.
        $this->assertFalse($nomenclador->existeProducto('11'));
        $this->assertFalse($nomenclador->existeProducto('1101'));

        $this->assertSame('KILOGRAMO(S)', $nomenclador->unidadMedidaDe('110100'));
        $this->assertNull($nomenclador->unidadMedidaDe('999999'));
    }

    public function test_importa_las_unidades_de_medida_y_rechaza_las_de_mas_de_un_caracter(): void
    {
        $archivo = $this->csvTemporal("codigo;descripcion\nK;KILOGRAMO\nU;UNIDAD\nKG;NO VA\n");

        $this->artisan('cot:import-catalogs', ['tipo' => 'unidades', '--file' => $archivo])->assertExitCode(0);

        $this->assertSame(2, CotUnidadMedida::count());
        $this->assertDatabaseHas('cot_unidades_medida', ['codigo' => 'K', 'descripcion' => 'KILOGRAMO']);

        @unlink($archivo);
    }

    /**
     * La clave de la CIT tiene que quedar cifrada en la base y fuera de
     * cualquier serialización del modelo.
     */
    public function test_la_clave_de_la_cit_se_guarda_cifrada_y_no_se_serializa(): void
    {
        $company = Company::factory()->create();
        $company->arba_cit_user = '30111111118';
        $company->arba_cit_password = 'clave-de-prueba';
        $company->save();

        $recargada = Company::find($company->id);

        $this->assertSame('30111111118', $recargada->arba_cit_user);
        $this->assertSame('clave-de-prueba', $recargada->arba_cit_password);

        $this->assertNotSame(
            'clave-de-prueba',
            DB::table('companies')->where('id', $company->id)->value('arba_cit_password'),
            'La clave quedó en texto plano en la base.'
        );

        $this->assertArrayNotHasKey('arba_cit_password', $recargada->toArray());
    }

    public function test_las_relaciones_del_modulo_estan_cableadas(): void
    {
        $company = Company::factory()->create();
        $point = $company->trasladoPoints()->create(['planta' => '003', 'puerta' => '017']);

        $this->assertSame('003', $point->fresh()->planta);
        $this->assertTrue($company->trasladoPoints()->exists());

        $archivo = CotArchivo::create([
            'company_id' => $company->id,
            'nombre_archivo' => 'TB_30111111118_003017_20261005_000001.txt',
            'contenido' => "01|30111111118\n",
            'cantidad_remitos' => 1,
        ]);

        $cot = $archivo->cots()->create([
            'company_id' => $company->id,
            'sujeto_generador' => 'E',
            'codigo_unico' => '91 R000100000001',
        ]);

        $cot->items()->create([
            'codigo_unico_producto' => '110100',
            'arba_codigo_unidad_medida' => 'K',
            'cantidad' => 200,
        ]);

        $this->assertSame($archivo->id, $cot->fresh()->archivo->id);
        $this->assertCount(1, $cot->fresh()->items);

        $empresa = $archivo->fresh()->company;
        $this->assertNotNull($empresa);
        $this->assertSame($company->id, $empresa->id);
    }

    /**
     * numero_cot y procesado los escribe la respuesta de ARBA. Si estuvieran en
     * $fillable, un request podría inventar un COT que nunca se emitió.
     */
    public function test_el_numero_de_cot_no_es_asignable_masivamente(): void
    {
        $cot = new Cot();

        $this->assertFalse($cot->isFillable('numero_cot'));
        $this->assertFalse($cot->isFillable('procesado'));
        $this->assertFalse($cot->isFillable('errores'));
        $this->assertTrue($cot->isFillable('codigo_unico'));
        $this->assertTrue($cot->isFillable('sale_invoice_id'));
    }

    private function importarNomenclador(): void
    {
        $this->artisan('cot:import-catalogs', [
            'tipo' => 'nomenclador',
            '--file' => $this->rutaNomenclador(),
        ])->assertExitCode(0);
    }

    private function rutaNomenclador(): string
    {
        return base_path('database/data/arba_nomenclador.sql');
    }

    private function csvTemporal(string $contenido): string
    {
        return $this->archivoTemporal('csv', $contenido);
    }

    private function sqlTemporal(string $contenido): string
    {
        return $this->archivoTemporal('sql', $contenido);
    }

    private function archivoTemporal(string $extension, string $contenido): string
    {
        $path = sys_get_temp_dir() . '/cot-' . uniqid() . '.' . $extension;

        file_put_contents($path, $contenido);

        return $path;
    }
}
