<?php

namespace App\Src\Arba\Cot\Transport;

/**
 * Cómo le hablamos a ARBA.
 *
 * Es una interfaz por un motivo concreto: los tests no pueden salir a internet
 * ni depender de que el servicio de ARBA esté arriba. Con el transporte detrás
 * de una interfaz, toda la lógica del COT (armado del archivo, envío, parseo y
 * persistencia) se prueba con un transporte falso.
 *
 * El envío es multipart/form-data, como pide el instructivo para la aplicación
 * cliente: los campos user, password y file.
 */
interface CotTransport
{
    /**
     * @param  array<string, string>  $campos   campos de texto del formulario
     * @param  array<string, array{nombre: string, contenido: string}>  $archivos  archivos a subir
     */
    public function post(string $url, array $campos, array $archivos = []): CotHttpResponse;
}
