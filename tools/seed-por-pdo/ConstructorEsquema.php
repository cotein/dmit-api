<?php

namespace Tools\SeedPorPdo;

/**
 * Reemplaza a Illuminate\Database\Schema\Builder en lo único que el escritor de
 * catálogos le pide: saber si la tabla existe.
 *
 * Si la fachada está en modo grabador (sin PDO) esto no puede responderse, y por eso
 * falla en voz alta en vez de devolver false: devolver false haría que el importador
 * informara "no existe la tabla X" para todos los catálogos, que es un resultado
 * creíble y equivocado.
 */
final class ConstructorEsquema
{
    /** @var \PDO|null */
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function hasTable($tabla)
    {
        if ($this->pdo === null) {
            throw new \RuntimeException('hasTable() se llamó sin conexión PDO: la fachada DB está en modo grabador.');
        }

        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $st->execute([$tabla]);

        return (int) $st->fetchColumn() > 0;
    }
}
