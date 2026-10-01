<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckConfigTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_it_dumps_the_database_configuration(): void
    {
        // Forzamos la carga de la configuración para el entorno 'testing'.
        $this->createApplication();

        echo "\n--- INICIO DE DIAGNÓSTICO ---\n";

        // 1. ¿Qué valor tiene la variable de entorno DB_CONNECTION?
        echo "env('DB_CONNECTION'): " . env('DB_CONNECTION') . "\n";

        // 2. ¿Cuál es la conexión por defecto según el gestor de configuración?
        echo "config('database.default'): " . config('database.default') . "\n";

        // 3. ¿Cuál es el nombre de la conexión que está usando el gestor de BD en este momento?
        echo "DB::connection()->getName(): " . DB::connection()->getName() . "\n";
        
        echo "--- FIN DE DIAGNÓSTICO ---\n";
        
        // Esta línea es importante para que el test no falle y nos deje ver la salida.
        $this->assertTrue(true);
    }
}
