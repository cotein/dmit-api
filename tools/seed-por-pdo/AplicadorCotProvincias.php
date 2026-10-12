<?php

namespace Tools\SeedPorPdo;

use App\Src\Arba\Cot\CotProvincias;

/**
 * Replica la parte `provincias` de cot:import-catalogs, por PDO.
 *
 * POR QUÉ NO SE EJECUTA LA CLASE DEL COMANDO: ImportCotCatalogs.php usa expresiones
 * `match`, que PHP 7.4 no puede ni parsear (`php -l` falla). No es una decisión de
 * estilo: con el único PHP que tiene pdo_mysql en esta máquina, ese archivo es
 * ilegible. Las otras dos partes que el comando sabe hacer (nomenclador y unidades)
 * no se tocan acá porque no hacen falta para esto.
 *
 * LOS DATOS NO SE COPIAN: la tabla de provincias sale de CotProvincias::PROVINCIAS,
 * que es la constante que el comando también usa, y la regla de derivación de
 * states.cot_code es la misma comprobación de ISO 3166-2 que hace el comando
 * ("AR-B" -> "B"), incluido el CotProvincias::existe() que descarta lo que no esté
 * en la tabla de ARBA.
 */
final class AplicadorCotProvincias
{
    /** @var \PDO */
    private $pdo;

    /** @var int */
    private $enlazados = 0;

    /** @var int */
    private $sinCodigo = 0;

    /** @var array<int, string> */
    private $avisos = [];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function provinciasEscritas()
    {
        return count(CotProvincias::PROVINCIAS);
    }

    public function estadosEnlazados()
    {
        return $this->enlazados;
    }

    public function estadosSinCodigo()
    {
        return $this->sinCodigo;
    }

    public function avisos()
    {
        return $this->avisos;
    }

    public function aplicar($dryRun = false)
    {
        if (! $dryRun) {
            // Mismo updateOrInsert que el comando: por `codigo`, que es la clave
            // con UNIQUE, así que correrlo dos veces no duplica provincias.
            $st = $this->pdo->prepare(
                'INSERT INTO cot_provincias (codigo, descripcion, created_at, updated_at)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), updated_at = VALUES(updated_at)'
            );

            $ahora = date('Y-m-d H:i:s');

            foreach (CotProvincias::PROVINCIAS as $codigo => $descripcion) {
                $st->execute([$codigo, $descripcion, $ahora, $ahora]);
            }
        }

        return $this->enlazarConStates($dryRun);
    }

    /**
     * Completa states.cot_code con la letra que usa ARBA en el COT.
     *
     * Es lo que la pantalla necesita: filtra por `whereNotNull('cot_code')`, así que
     * sin esto el selector de provincia del COT sale vacío aunque states tenga las
     * 24 provincias cargadas.
     */
    private function enlazarConStates($dryRun)
    {
        $states = $this->pdo->query('SELECT id, name, codigo31662 FROM states');

        if ($states->rowCount() === 0) {
            $this->avisos[] = 'La tabla states está vacía: no hay a qué enlazar.';

            return 0;
        }

        $st = $this->pdo->prepare('UPDATE states SET cot_code = ? WHERE id = ?');

        foreach ($states as $state) {
            $letra = $this->letraDesdeIso31662($state['codigo31662']);

            if ($letra === null || ! CotProvincias::existe($letra)) {
                $this->sinCodigo++;
                $this->avisos[] = "Sin código COT: {$state['name']} (codigo31662 = '{$state['codigo31662']}')";

                continue;
            }

            if (! $dryRun) {
                $st->execute([$letra, $state['id']]);
            }

            $this->enlazados++;
        }

        return $this->enlazados;
    }

    /**
     * Misma regla que ImportCotCatalogs::letraDesdeIso31662.
     */
    private function letraDesdeIso31662($iso)
    {
        $iso = strtoupper(trim((string) $iso));

        if ($iso === '' || ! preg_match('/^[A-Z]{2}-([A-Z])$/', $iso, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
