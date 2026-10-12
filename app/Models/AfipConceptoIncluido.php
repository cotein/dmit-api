<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Concepto incluido en el comprobante (código de un dígito): producto,
 * servicios, productos y servicios, otro.
 *
 * No confundir con CompanyBillingConcept: ése es la configuración de facturación
 * de cada empresa (`companies.billing_concept`) y está en uso; éste es el
 * nomenclador de ARCA.
 *
 * Lo llena:
 *   php artisan afip:import-datos-base conceptos
 */
class AfipConceptoIncluido extends Model
{
    use HasFactory;

    protected $table = 'afip_conceptos_incluidos';

    protected $fillable = [
        'codigo',
        'descripcion',
    ];
}
