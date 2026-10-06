<?php

namespace App\Src\Arba\Cot;

use App\Models\Cot;
use App\Models\Company;
use App\Models\CotArchivo;
use App\Src\Arba\Cot\Transport\CotTransport;
use App\Src\Arba\Cot\Transport\CurlCotTransport;
use Illuminate\Support\Facades\Log;

/**
 * Presenta un archivo de remitos al servicio de Transporte de Bienes de ARBA.
 *
 * El orden de los pasos no es casual, y el motivo es que enviar es
 * irreversible:
 *
 *   1. Se valida todo ANTES de armar el archivo. Un archivo rechazado gasta un
 *      envío y, peor, deja dudas sobre si algo se emitió.
 *   2. Se guarda el TXT y su hash ANTES de enviarlo. Si el proceso se cae justo
 *      después de enviar, el contenido queda en la base y el reenvío del mismo
 *      nombre de archivo no duplica nada (ARBA contesta el error 11).
 *   3. Se marca el archivo como "enviado" antes del POST, para que una caída a
 *      mitad de camino deje rastro de que el pedido salió.
 *   4. Recién ahí se envía y se guarda la respuesta.
 *
 * Sobre los códigos 11 y 17: llegan como error pero significan que el remito YA
 * estaba emitido. Acá se tratan como emitido y no como fallo, porque marcarlos
 * como rechazados lleva a reintentar y a terminar con duplicados.
 */
final class CotEmisionService
{
    public const TIPO_VALIDACION = 'VALIDACION';

    public const TIPO_CREDENCIALES = 'CREDENCIALES';

    public const TIPO_TRANSPORTE = 'TRANSPORTE';

    public const TIPO_VALIDACION_ARCHIVO = 'VALIDACION_ARCHIVO';

    public function __construct(
        private CotTransport $transport,
        private CotValidator $validator,
        private CotFileBuilder $builder,
        private CotResponseParser $parser,
        private CotRemitoMapper $mapper,
    ) {
    }

    public function emitir(CotArchivo $archivo): CotResultado
    {
        $archivo->loadMissing(['company', 'cots.items']);

        if ($archivo->estado === CotArchivo::ESTADO_PROCESADO) {
            return $this->rechazarLocal(
                $archivo,
                self::TIPO_VALIDACION_ARCHIVO,
                'El archivo ya fue presentado y procesado: no se reenvía.'
            );
        }

        $empresa = $archivo->company;

        if (! $empresa instanceof Company) {
            return $this->rechazarLocal($archivo, self::TIPO_VALIDACION_ARCHIVO, 'El archivo no tiene una empresa asociada.');
        }

        $credenciales = $this->credencialesDe($empresa);

        if ($credenciales === null) {
            return $this->rechazarLocal(
                $archivo,
                self::TIPO_CREDENCIALES,
                'La empresa no tiene cargadas las credenciales de la CIT de ARBA (usuario y clave).'
            );
        }

        if ($archivo->cots->isEmpty()) {
            return $this->rechazarLocal($archivo, self::TIPO_VALIDACION_ARCHIVO, 'El archivo no tiene remitos.');
        }

        $remitos = $this->mapper->fromCollection($archivo->cots);

        $errores = $this->validarTodo($archivo, $remitos);

        if ($errores !== []) {
            return $this->rechazarLocal($archivo, self::TIPO_VALIDACION, $errores);
        }

        // Se guarda el archivo antes de enviarlo: si el proceso muere después
        // del POST, el contenido tiene que estar en la base.
        $contenido = $this->builder->build($archivo->cuit_empresa, $remitos);

        $archivo->contenido = $contenido;
        $archivo->hash = hash('sha256', $contenido);
        $archivo->cantidad_remitos = count($remitos);
        $archivo->estado = CotArchivo::ESTADO_ENVIADO;
        $archivo->enviado_at = now();
        $archivo->save();

        $respuesta = $this->transport->post(
            $this->endpointDe($empresa->arba_cit_environment),
            [
                'user' => (string) $empresa->arba_cit_user,
                'password' => (string) $empresa->arba_cit_password,
            ],
            [
                'file' => [
                    'nombre' => (string) $archivo->nombre_archivo,
                    'contenido' => $contenido,
                ],
            ]
        );

        if ($respuesta->falloElTransporte()) {
            $this->persistirFalloDeTransporte($archivo, $respuesta->errorTransporte ?? 'Error desconocido');

            return CotResultado::error(
                self::TIPO_TRANSPORTE,
                'No se pudo completar la llamada a ARBA: ' . $respuesta->errorTransporte
                . ' El archivo no se reenvió: reintentá con el MISMO nombre de archivo, ARBA deduplica por nombre.'
            );
        }

        $resultado = $this->parser->parse($respuesta->cuerpo);

        $this->persistirRespuesta($archivo, $respuesta->status, $resultado);
        $this->actualizarRemitos($archivo, $resultado);

        return $resultado;
    }

    /**
     * @param  array<int, CotRemitoData>  $remitos
     * @return string[]  vacío si todo está presentable
     */
    private function validarTodo(CotArchivo $archivo, array $remitos): array
    {
        $errores = [];

        $problemaDeArchivo = $this->validarNombreDeArchivo($archivo);

        if ($problemaDeArchivo !== null) {
            $errores[] = $problemaDeArchivo;
        }

        foreach ($remitos as $indice => $remito) {
            $problemas = $this->validator->validate($archivo->cuit_empresa, $remito);

            foreach ($problemas as $campo => $mensaje) {
                $errores[] = 'Remito ' . ($indice + 1) . " ({$remito->codigoUnico}) [{$campo}]: {$mensaje}";
            }
        }

        return $errores;
    }

    /**
     * El nombre del archivo es la clave de idempotencia y, además, es donde
     * viajan el CUIT, la planta, la puerta, la fecha y el secuencial. Si no
     * coincide con los datos que se van a mandar adentro, ARBA rechaza el
     * archivo entero (errores 9, 50 y 51 a 53).
     */
    private function validarNombreDeArchivo(CotArchivo $archivo): ?string
    {
        try {
            $partes = CotFileName::parse((string) $archivo->nombre_archivo);
        } catch (\InvalidArgumentException $e) {
            return 'El nombre del archivo no respeta el formato: ' . $e->getMessage();
        }

        $esperado = [
            'CUIT' => preg_replace('/\D/', '', (string) $archivo->cuit_empresa),
            'planta' => (string) $archivo->planta,
            'puerta' => (string) $archivo->puerta,
            'fecha' => $archivo->fecha_archivo?->format('Ymd'),
        ];

        foreach ($esperado as $dato => $valor) {
            if ($valor !== null && $valor !== '' && $partes[strtolower($dato)] !== $valor) {
                return "El nombre del archivo no coincide con {$dato}: el archivo dice '{$partes[strtolower($dato)]}' y el COT tiene '{$valor}'.";
            }
        }

        return null;
    }

    /**
     * @return array{usuario: string, clave: string}|null
     */
    private function credencialesDe(Company $empresa): ?array
    {
        $usuario = (string) $empresa->arba_cit_user;
        $clave = (string) $empresa->arba_cit_password;

        if (trim($usuario) === '' || trim($clave) === '') {
            return null;
        }

        return ['usuario' => $usuario, 'clave' => $clave];
    }

    private function endpointDe(?string $environment): string
    {
        $ambiente = strtolower(trim((string) $environment));

        if (! in_array($ambiente, ['testing', 'production'], true)) {
            $ambiente = 'testing';
        }

        return (string) config("cot.endpoints.{$ambiente}");
    }

    /**
     * @param  string[]  $mensajes
     */
    private function rechazarLocal(CotArchivo $archivo, string $tipo, array|string $mensajes): CotResultado
    {
        $mensajes = is_array($mensajes) ? $mensajes : [$mensajes];
        $detalle = implode(' ', $mensajes);

        $archivo->estado = CotArchivo::ESTADO_RECHAZADO;
        $archivo->error_tipo = $tipo;
        $archivo->error_codigo = '';
        $archivo->error_mensaje = $detalle;
        $archivo->save();

        Log::warning('COT: envío rechazado antes de salir', [
            'cot_archivo_id' => $archivo->id,
            'nombre_archivo' => $archivo->nombre_archivo,
            'tipo' => $tipo,
            'detalle' => $mensajes,
        ]);

        return CotResultado::error($tipo, $detalle);
    }

    private function persistirFalloDeTransporte(CotArchivo $archivo, string $mensaje): void
    {
        $archivo->estado = CotArchivo::ESTADO_ERROR;
        $archivo->error_tipo = self::TIPO_TRANSPORTE;
        $archivo->error_codigo = '';
        $archivo->error_mensaje = $mensaje;
        $archivo->save();
    }

    private function persistirRespuesta(CotArchivo $archivo, ?int $status, CotResultado $resultado): void
    {
        $archivo->http_status = $status;
        $archivo->respuesta = $resultado->cuerpoCrudo;
        $archivo->numero_comprobante = $resultado->numeroComprobante;
        $archivo->codigo_integridad = $resultado->codigoIntegridad;

        if (! $resultado->exitoso) {
            $archivo->estado = CotArchivo::ESTADO_ERROR;
            $archivo->error_tipo = $resultado->errorTipo;
            $archivo->error_codigo = $resultado->errorCodigo;
            $archivo->error_mensaje = $resultado->errorMensaje;
        } else {
            $archivo->estado = $resultado->todosProcesados()
                ? CotArchivo::ESTADO_PROCESADO
                : CotArchivo::ESTADO_RECHAZADO;

            $archivo->error_tipo = $resultado->todosProcesados() ? null : self::TIPO_VALIDACION;
            $archivo->error_codigo = null;
            $archivo->error_mensaje = $resultado->todosProcesados() ? null : $this->detalleDeRechazos($resultado);
        }

        $archivo->save();
    }

    private function actualizarRemitos(CotArchivo $archivo, CotResultado $resultado): void
    {
        foreach ($archivo->cots as $cot) {
            $remito = $resultado->remito((string) $cot->codigo_unico);

            if ($remito === null) {
                // ARBA no informó este remito: se deja constancia en lugar de
                // darlo por bueno en silencio.
                $cot->procesado = false;
                $cot->errores = json_encode([[
                    'codigo' => '',
                    'descripcion' => 'ARBA no informó el resultado de este remito.',
                ]], JSON_UNESCAPED_UNICODE);
                $cot->save();

                continue;
            }

            // Un rechazo por código 11 o 17 significa que ya estaba emitido, no
            // que haya fallado.
            $cot->procesado = $remito->procesado || $remito->yaEstabaEmitido();
            $cot->numero_cot = $remito->numeroCot;
            $cot->errores = $remito->errores === []
                ? null
                : json_encode($remito->errores, JSON_UNESCAPED_UNICODE);
            $cot->save();
        }
    }

    private function detalleDeRechazos(CotResultado $resultado): string
    {
        $partes = [];

        foreach ($resultado->remitosRechazados() as $remito) {
            $partes[] = $remito->numeroUnico . ' -> ' . $remito->descripcionDeErrores();
        }

        return implode(' | ', $partes);
    }

    /**
     * Texto para mostrarle al usuario sin obligarlo a leer el XML.
     */
    public static function descripcionDeEstado(?int $status): string
    {
        return CurlCotTransport::descripcionHttp($status);
    }
}
