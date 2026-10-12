<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

use App\Src\Afip\Padron100\Excepciones\Padron100Exception;

/**
 * Cliente del WS ws_sr_padron_a100.
 *
 * Por qué una interfaz con una sola operación: el servicio sólo tiene dos métodos
 * (dummy y getParameterCollectionByName) y sin certificado no se lo puede llamar. Con
 * la interfaz, la sincronización y el mapeo se prueban con una respuesta de ejemplo
 * inyectada (ClienteFixturePadron100) sin tocar ARCA ni necesitar credenciales.
 */
interface ClientePadron100
{
    /**
     * Trae todos los registros de la colección indicada.
     *
     * @param  string  $collectionName  Nombre exacto del Anexo 5.1 (ver ColeccionesPadron100)
     *
     * @throws Padron100Exception
     */
    public function traerColeccion(string $collectionName): ColeccionPadron100;
}
