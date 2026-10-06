<?php

namespace App\Src\Arba\Cot;

/**
 * Catálogo de códigos de error del servicio de Transporte de Bienes de ARBA.
 *
 * Transcripción de la tabla "Códigos de Error" del instructivo para la
 * aplicación cliente. Sirve para dos cosas: traducir el código que devuelve el
 * XML a un mensaje que se pueda mostrar, y clasificar la respuesta.
 *
 * El detalle que más importa acá: 11 y 17 llegan como error pero significan que
 * el remito YA está emitido. Se reintentó un envío cuyo archivo o remito ya
 * había sido procesado. Tratarlos como fallo es el camino más rápido a un
 * duplicado en el sistema y a un número de COT perdido.
 */
final class CotErrors
{
    /**
     * El archivo ya había sido procesado: el envío es idempotente, no un fallo.
     */
    public const ARCHIVO_YA_PROCESADO = '11';

    /**
     * El remito ya había sido procesado: idem, a nivel de remito.
     */
    public const REMITO_YA_PROCESADO = '17';

    /**
     * La provincia de origen o destino tiene que ser Buenos Aires.
     */
    public const PROVINCIA_INVALIDA = '90';

    public const DESCRIPCIONES = [
        '01' => 'Ha ocurrido un error inesperado. Intente más tarde.',
        '02' => 'El usuario ingresado y/o la contraseña son inválidos.',
        '03' => 'Usuario no habilitado.',
        '04' => 'Código postal origen inválido.',
        '05' => 'Código postal destino inválido.',
        '06' => 'Error al intentar obtener el COT.',
        '07' => 'El usuario ingresado se encuentra bloqueado.',
        '08' => 'Error de parámetro. Parámetro requerido: user / password.',
        '09' => 'El CUIT empresa del nombre del archivo no se corresponde con el CUIT de la empresa autenticada.',
        '10' => 'El nombre del archivo recibido es incorrecto.',
        '11' => 'El archivo recibido ya fue procesado con anterioridad.',
        '12' => 'No se puede procesar el registro 01-HEADER del archivo recibido. Faltan datos.',
        '13' => 'El campo CUIT_EMPRESA no se corresponde con el CUIT de la empresa autenticada.',
        '14' => 'No se puede procesar el registro 02-REMITO. Faltan datos.',
        '15' => 'No se puede procesar el registro 03-PRODUCTOS. Faltan datos.',
        '16' => 'No se puede procesar el registro 04-FOOTER. Faltan datos.',
        '17' => 'El remito ya fue procesado con anterioridad.',
        '18' => 'Ha ocurrido un error inesperado en el procesamiento del remito. Intente más tarde.',
        '19' => 'Ha ocurrido un error inesperado en el procesamiento del archivo recibido. Intente más tarde.',
        '20' => 'El campo FECHA_EMISION es inválido o inexistente.',
        '21' => 'El campo CODIGO_UNICO es inválido o inexistente.',
        '22' => 'El campo FECHA_SALIDA_TRANSPORTE es inválido o inexistente.',
        '23' => 'El campo DESTINATARIO_CUIT es inválido o inexistente.',
        '24' => 'El campo DESTINATARIO_RAZON_SOCIAL es inválido o inexistente.',
        '25' => 'El campo DESTINO_DOMICILIO_CALLE es inválido o inexistente.',
        '26' => 'El campo DESTINO_DOMICILIO_NUMERO es inválido o inexistente.',
        '27' => 'El campo DESTINO_DOMICILIO_LOCALIDAD es inválido o inexistente.',
        '28' => 'El campo DESTINO_DOMICILIO_PROVINCIA es inválido o inexistente.',
        '29' => 'El campo ENTREGA_DOMICILIO_ORIGEN es inválido o inexistente.',
        '30' => 'El campo ORIGEN_DOMICILIO_CALLE es inválido o inexistente.',
        '31' => 'El campo ORIGEN_DOMICILIO_NUMERO es inválido o inexistente.',
        '32' => 'El campo ORIGEN_DOMICILIO_LOCALIDAD es inválido o inexistente.',
        '33' => 'El campo ORIGEN_DOMICILIO_PROVINCIA es inválido o inexistente.',
        '34' => 'El campo TRANSPORTISTA_CUIT es inválido.',
        '36' => 'El campo TIPO_RECORRIDO es inválido.',
        '37' => 'El campo PATENTE_VEHICULO es inválido o inexistente.',
        '38' => 'El campo PATENTE_ACOPLADO es inválido o inexistente.',
        '39' => 'El campo CODIGO_UNICO_PRODUCTO es inválido o inexistente.',
        '41' => 'El campo RENTAS_CODIGO_UNIDAD_MEDIDA es inválido o inexistente.',
        '42' => 'El campo CANTIDAD es inválido o inexistente.',
        '43' => 'El campo CANTIDAD_TOTAL_REMITOS es inválido o inexistente.',
        '44' => 'No hay registro 01 = HEADER.',
        '45' => 'No hay registro 02 = REMITO.',
        '46' => 'No hay registro 03 = PRODUCTOS.',
        '47' => 'No hay registro 04 = FOOTER.',
        '49' => 'El campo CUIT_EMPRESA es inválido o inexistente.',
        '50' => 'El campo CUIT_EMPRESA no coincide con el campo CUIT del archivo.',
        '51' => 'El CUIT del archivo es inválido o inexistente.',
        '52' => 'La fecha del archivo es inválida o inexistente.',
        '53' => 'El número secuencial del archivo es inválido o inexistente.',
        '56' => 'El parámetro FILE es inexistente.',
        '57' => 'El campo CANTIDAD_TOTAL_REMITOS no coincide con la cantidad de remitos enviados en el archivo.',
        '60' => 'El campo HORA_SALIDA_TRANSPORTE supera los 30 minutos desde la partida del transporte.',
        '61' => 'El campo DESTINO_DOMICILIO_COMPLE es inválido.',
        '62' => 'El campo ORIGEN_DOMICILIO_COMPLE es inválido.',
        '63' => 'El campo HORA_SALIDA_TRANSPORTE es inválido.',
        '64' => 'El campo RECORRIDO_CALLE es inválido.',
        '65' => 'El campo RECORRIDO_RUTA es inválido.',
        '66' => 'El campo RECORRIDO_LOCALIDAD es inválido.',
        '67' => 'El campo ORIGEN_DOMICILIO_CODIGOPOSTAL es inválido.',
        '68' => 'El campo ORIGEN_DOMICILIO_BARRIO es inválido.',
        '69' => 'El campo ORIGEN_DOMICILIO_DTO es inválido.',
        '70' => 'El campo ORIGEN_DOMICILIO_PISO es inválido.',
        '71' => 'El campo DESTINO_DOMICILIO_CODIGOPOSTAL es inválido.',
        '72' => 'El campo DESTINO_DOMICILIO_BARRIO es inválido.',
        '73' => 'El campo DESTINO_DOMICILIO_DTO es inválido.',
        '74' => 'El campo DESTINO_DOMICILIO_PISO es inválido.',
        '75' => 'El campo CANTIDAD_AJUSTADA es inválido o inexistente.',
        '76' => 'El campo PROPIO_CODIGO_PRODUCTO es inválido o inexistente.',
        '77' => 'El campo PROPIO_DESCRIPCION_PRODUCTO es inválido o inexistente.',
        '78' => 'El campo PROPIO_DESTINO_DOMICILIO_CODIGO es inválido.',
        '79' => 'El campo SUJETO_GENERADOR es inválido o inexistente.',
        '80' => 'No se ha establecido un canal seguro.',
        '81' => 'El campo PRODUCTO_UNICO es inválido o inexistente.',
        '82' => 'El campo DESTINATARIO_CONSUMIDOR_FINAL es inválido o inexistente.',
        '83' => 'El campo DESTINATARIO_TIPO_DOCUMENTO es inválido o inexistente.',
        '84' => 'El campo DESTINATARIO_DOCUMENTO es inválido o inexistente.',
        '85' => 'El campo ORIGEN_CUIT es inválido o inexistente.',
        '86' => 'El campo ORIGEN_RAZON_SOCIAL es inválido o inexistente.',
        '87' => 'El formulario multipart enviado es incorrecto. Verifique las especificaciones para la aplicación cliente.',
        '88' => 'La extensión del archivo recibido es incorrecta.',
        '89' => 'El campo PROPIO_DESCRIPCION_UNIDAD_MEDIDA es inválido o inexistente.',
        '90' => 'El campo ORIGEN_DOMICILIO_PROVINCIA o el campo DESTINO_DOMICILIO_PROVINCIA debe ser igual a "B" (Buenos Aires).',
        '95' => 'El campo IMPORTE es inválido o inexistente.',
        '96' => 'El campo ORIGEN_TENEDOR es inválido o inexistente.',
        '97' => 'El campo DESTINATARIO_TENEDOR es inválido o inexistente.',
    ];

    public static function descripcion(?string $codigo): string
    {
        $codigo = self::normalize($codigo);

        return self::DESCRIPCIONES[$codigo] ?? "Error {$codigo} de ARBA (código no documentado).";
    }

    /**
     * El "error" en realidad confirma que el envío ya había sido procesado.
     * No hay que generar un COT nuevo ni marcar la operación como fallida.
     */
    public static function esExitoIdempotente(?string $codigo): bool
    {
        return in_array(self::normalize($codigo), [
            self::ARCHIVO_YA_PROCESADO,
            self::REMITO_YA_PROCESADO,
        ], true);
    }

    /**
     * Vale la pena reintentar sin intervención: son fallas transitorias del lado
     * de ARBA, no datos nuestros mal formados.
     */
    public static function esReintentable(?string $codigo): bool
    {
        return in_array(self::normalize($codigo), ['01', '18', '19'], true);
    }

    private static function normalize(?string $codigo): string
    {
        $codigo = (string) $codigo;

        return strlen($codigo) === 1 ? '0' . $codigo : $codigo;
    }
}
