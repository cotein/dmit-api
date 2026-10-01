<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * El nombre de la tabla asociada con el modelo.
     * Es opcional si sigue la convención de Laravel ('order_items').
     *
     * @var string
     */
    protected $table = 'order_items';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'pricelist_id',
        'iva_id',
        'unit_price',
        'quantity',
        'discount_percentage',
        'discount_import',
        'iva_percentage',
        'iva_import',
        'neto_import',
        'total',
        'price_list',
        'is_chp',
        'mts',
        'rounded_mts',
        'real_mts',
        'mts_to_invoiced',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_chp' => 'boolean',
        'price_list' => 'array',
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'discount_import' => 'decimal:2',
        'iva_percentage' => 'decimal:2',
        'iva_import' => 'decimal:2',
        'neto_import' => 'decimal:2',
        'total' => 'decimal:2',
        'mts' => 'decimal:2',
        'rounded_mts' => 'decimal:2',
        'real_mts' => 'decimal:2',
        'mts_to_invoiced' => 'decimal:2',
    ];

    // --- RELACIONES ---

    /**
     * Obtiene el pedido al que pertenece este ítem.
     */
    public function order(): BelongsTo
    {
        // Se asume que el modelo del pedido se llama PedidoCliente
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Obtiene el producto asociado a este ítem.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Obtiene la lista de precios asociada (si aplica).
     */
    public function priceList(): BelongsTo
    {
        // Asumiendo que tienes un modelo PriceList
        return $this->belongsTo(PriceList::class, 'pricelist_id');
    }

    /**
     * Obtiene el tipo de IVA asociado (si aplica).
     */
    public function iva(): BelongsTo
    {
        // Asumiendo que tienes un modelo Iva
        return $this->belongsTo(AfipIva::class, 'iva_id');
    }
}