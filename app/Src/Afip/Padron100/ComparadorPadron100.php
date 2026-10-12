<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

/**
 * Decide qué cambia entre lo que ya está guardado y lo que devolvió ARCA.
 *
 * Está separado de la escritura y no toca la base: es la parte que se puede probar sin
 * certificado y sin conexión, y es donde vive la idempotencia. La regla es simple y
 * deliberada: un registro es "sin cambios" cuando TODOS sus campos comparables son
 * iguales. Por eso sincronizado_at no entra en la comparación (si entrara, la segunda
 * corrida diría "todo actualizado" y la sincronización nunca sería idempotente).
 *
 * Los registros que ya no vienen de ARCA no se borran: se informan como sobrantes.
 */
final class ComparadorPadron100
{
    /**
     * Compara los parámetros crudos de una colección.
     *
     * @param  array<int, ParametroPadron100>  $parametros
     * @param  array<string, array<string, mixed>>  $existentes  [parametro_id => ['descripcion'=>, 'atributos'=>]]
     * @param  string  $momento  Marca de tiempo que se guarda en sincronizado_at
     * @return array{nuevos: array<int, array<string, mixed>>, actualizados: array<int, array<string, mixed>>, sinCambios: int, sobrantes: array<int, string>}
     */
    public function compararParametros(string $coleccion, array $parametros, array $existentes, string $momento): array
    {
        $nuevos = [];
        $actualizados = [];
        $sinCambios = 0;
        $vistos = [];

        foreach ($parametros as $parametro) {
            $id = $parametro->id();
            $vistos[$id] = true;

            $atributos = $parametro->atributos();
            $json = $this->json($atributos);
            $descripcion = $parametro->descripcion();

            $fila = [
                'collection_name' => $coleccion,
                'parametro_id' => $id,
                'descripcion' => $descripcion,
                'atributos' => $json,
                'sincronizado_at' => $momento,
            ];

            if (! isset($existentes[$id])) {
                $nuevos[] = $fila;

                continue;
            }

            $actual = $existentes[$id];

            $igual = (string) $actual['descripcion'] === (string) $descripcion
                && $this->mismosAtributos($actual['atributos'], $atributos);

            if ($igual) {
                $sinCambios++;

                continue;
            }

            $actualizados[] = $fila;
        }

        return [
            'nuevos' => $nuevos,
            'actualizados' => $actualizados,
            'sinCambios' => $sinCambios,
            'sobrantes' => $this->codigos(array_diff(array_keys($existentes), array_keys($vistos))),
        ];
    }

    /**
     * Compara un catálogo con destino propio (afip_documents).
     *
     * Diferencia deliberada y visible: la descripción que gana es la de ARCA, carácter por
     * carácter. El objetivo del servicio es justamente que el catálogo deje de mantenerse
     * a mano, así que si ARCA manda "CERTIFICADO DE MIGRACION" y en la tabla dice
     * "Certificado de Migración", se actualiza. Si no se quiere eso, el comando tiene
     * --solo-nuevos para dar de alta sólo los códigos que faltan.
     *
     * @param  array<string, string>  $deseados  [codigo => nombre]
     * @param  array<string, array<string, mixed>>  $existentes  [codigo => ['id'=>, 'nombre'=>]]
     * @return array{nuevos: array<string, string>, actualizados: array<string, string>, sinCambios: int, sobrantes: array<int, string>}
     */
    public function compararDestino(array $deseados, array $existentes): array
    {
        $nuevos = [];
        $actualizados = [];
        $sinCambios = 0;

        foreach ($deseados as $codigo => $nombre) {
            if (! isset($existentes[$codigo])) {
                $nuevos[$codigo] = $nombre;

                continue;
            }

            if ((string) $existentes[$codigo]['nombre'] !== $nombre) {
                $actualizados[$codigo] = $nombre;

                continue;
            }

            $sinCambios++;
        }

        return [
            'nuevos' => $nuevos,
            'actualizados' => $actualizados,
            'sinCambios' => $sinCambios,
            'sobrantes' => $this->codigos(array_diff(array_keys($existentes), array_keys($deseados))),
        ];
    }

    /**
     * Contraste de provincias contra states. No escribe: informa.
     *
     * La comparación de nombres no es literal porque ARCA manda todo en mayúsculas y sin
     * acentos ("RIO NEGRO") y la tabla del sistema los tiene como se escriben en el país
     * ("Río Negro"): comparar crudo daría 24 diferencias falsas.
     *
     * @param  array<string, string>  $deseados  [codigo => nombre]
     * @param  array<string, array<string, mixed>>  $existentes  [codigo => ['id'=>, 'nombre'=>, 'cot_code'=>]]
     * @return array<string, mixed>
     */
    public function contrastarProvincias(array $deseados, array $existentes): array
    {
        $faltantes = [];
        $distintas = [];
        $coinciden = 0;
        $sinCodigoCot = [];

        foreach ($deseados as $codigo => $nombre) {
            $codigo = (string) $codigo;

            if (! isset($existentes[$codigo])) {
                $faltantes[] = ['codigo' => $codigo, 'nombre' => $nombre];

                continue;
            }

            $local = $existentes[$codigo];

            if ($this->normalizar((string) $local['nombre']) !== $this->normalizar($nombre)) {
                $distintas[] = [
                    'codigo' => $codigo,
                    'arca' => $nombre,
                    'local' => (string) $local['nombre'],
                ];
            } else {
                $coinciden++;
            }

            if ((string) $local['cot_code'] === '') {
                $sinCodigoCot[] = (string) $local['nombre'];
            }
        }

        return [
            'coinciden' => $coinciden,
            'faltantes' => $faltantes,
            'distintas' => $distintas,
            'sobrantes' => $this->codigos(array_diff(array_keys($existentes), array_keys($deseados))),
            'sinCodigoCot' => $sinCodigoCot,
        ];
    }

    /**
     * Los códigos vuelven siempre como texto.
     *
     * Por qué: PHP convierte a entero las claves de array que son numéricas, así que el
     * código '19' de una provincia o el '80' de un documento salen como int si no se los
     * castea. El id de un parámetro de ARCA es texto para el servicio y para la columna
     * parametro_id, así que se mantiene texto de punta a punta.
     *
     * @param  array<int, int|string>  $codigos
     * @return array<int, string>
     */
    private function codigos(array $codigos): array
    {
        return array_values(array_map('strval', $codigos));
    }

    /**
     * Compara dos juegos de atributos sin depender del orden de las claves.
     *
     * No alcanza con comparar los JSON: la columna atributos es de tipo json y MySQL
     * reordena las claves de un objeto al guardarlo, así que el string que vuelve no es
     * el mismo que se escribió aunque el contenido sea idéntico. Comparando los valores
     * (con strval, porque el tipo puede volver como número) la comparación es estable.
     *
     * @param  array<string, mixed>  $guardados
     * @param  array<string, string>  $nuevos
     */
    private function mismosAtributos(array $guardados, array $nuevos): bool
    {
        return array_map('strval', $guardados) == array_map('strval', $nuevos);
    }

    /**
     * JSON estable: el parseo ya ordena los atributos por nombre, así que el mismo
     * contenido da siempre la misma cadena.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function json(array $atributos): string
    {
        $json = json_encode($atributos, JSON_UNESCAPED_UNICODE);

        return $json === false ? '{}' : $json;
    }

    /**
     * Mayúsculas, sin acentos y sin espacios sobrantes, para comparar nombres.
     */
    private function normalizar(string $valor): string
    {
        $mapa = [
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ü' => 'U',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
            'ñ' => 'N', 'Ñ' => 'N',
        ];

        return strtoupper(strtr(trim($valor), $mapa));
    }
}
