<?php

/**
 * Stubs mínimos de Eloquent, para poder LEER las clases del proyecto sin arrancar
 * Laravel.
 *
 * POR QUÉ: el generador de SQL necesita las constantes del catálogo (los códigos de
 * plan y los nombres de las funciones) que viven en los modelos reales, para no
 * mantener una segunda copia de esos valores. Cargar el Eloquent de verdad necesita
 * el arranque de la aplicación y el driver de MySQL, que en esta máquina no están.
 *
 * Sólo declara lo que los modelos de planes usan (herencia, el trait de factorías y
 * los dos tipos de relación que aparecen en las firmas). NO ejecuta consultas: los
 * modelos se cargan para leer sus constantes, no para consultar la base.
 */

namespace Illuminate\Database\Eloquent {
    abstract class Model
    {
        /** @var array<string, mixed> */
        protected $attributes = [];
    }

    abstract class Builder
    {
    }
}

namespace Illuminate\Database\Eloquent\Factories {
    trait HasFactory
    {
    }
}

namespace Illuminate\Database\Eloquent\Relations {
    class HasMany
    {
    }

    class BelongsTo
    {
    }
}
