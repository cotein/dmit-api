<?php

/**
 * Configuración del corte de planes (topes por CUIT).
 *
 * `admin_token` protege la configuración INTERNA (interruptores, recálculo,
 * asignación de planes). Es un secreto de operación y no una pantalla: no se expone
 * al cliente y no viaja nunca en una respuesta.
 *
 * Si el token queda vacío, los endpoints internos sólo aceptan a un usuario
 * autenticado con el permiso CREAR COMPAÑIA. Es a propósito: sin secreto configurado
 * esas rutas quedan cerradas, y la puesta en marcha se hace por consola (que es
 * donde tiene que hacerse, porque los interruptores deciden si alguien puede
 * facturar).
 */
return [
    'admin_token' => env('PLAN_ADMIN_TOKEN', ''),

    /*
     * El plan que se le asigna a los contribuyentes que ya existían cuando se
     * encienda el corte. Es el interno, sin topes: nadie se despierta bloqueado.
     */
    'internal_plan_code' => env('PLAN_INTERNAL_CODE', 'internal'),

    /*
     * El plan gratuito: el que rige cuando no hay suscripción o no está vigente.
     */
    'free_plan_code' => env('PLAN_FREE_CODE', 'free'),

    /*
     * Cuántas restantes faltan para que la pantalla destaque el contador y muestre
     * el aviso suave que NO bloquea la emisión.
     */
    'grace_remaining' => (int) env('PLAN_GRACE_REMAINING', 2),
];
