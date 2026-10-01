<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    /**
     * El nombre de la tabla asociada con el modelo, ya que no sigue la convención de Laravel.
     *
     * @var string
     */
    protected $table = 'orders';

    protected $appends = ['status'];
    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'customer_id',
        'status_id',
        'user_id',
        'sale_invoice_id',
        'voucher_id',
        'parent_id',
        'is_editing_by_user',
        'code',
        'number',
        'pay_method',
        'total',
        'aditional_pay_method',
        'delivery_date',
        'created_on_meli',
        'date',
        'meli_id',
        'is_meli_order',
        'meli_data',
        'delivery_address',
        'who_prepared',
        'who_delivered',
        'is_editing',
        'geocoder',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'delivery_date' => 'date',
        'created_on_meli' => 'date',
        'geocoder' => 'array',
        'meli_data' => 'array',
        'is_meli_order' => 'boolean',
        'is_editing' => 'boolean',
        'total' => 'decimal:2',
        'aditional_pay_method' => 'decimal:2',
    ];

    // --- RELACIONES ---

    /**
     * Un pedido pertenece a una compañía (para multi-tenant).
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Un pedido pertenece a un cliente.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Un pedido es creado por un usuario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Un pedido puede tener una factura de venta asociada.
     */
    public function saleInvoice(): BelongsTo
    {
        return $this->belongsTo(SaleInvoices::class);
    }

    /**
     * Relación a sí misma para pedidos padre.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'parent_id');
    }

    /**
     * Relación a sí misma para pedidos hijos.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Order::class, 'parent_id');
    }

    /**
     * El usuario que está actualmente editando el pedido.
     */
    public function editingByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'is_editing_by_user');
    }

    /**
     * Un pedido tiene muchos ítems. ¡Esta es la relación clave que faltaba!
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function status()
    {
        // Eloquent buscará la clave foránea `status_id` en la tabla `orders`.
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function getStatusAttribute()
    {
        // Usamos la relación 'status()' que ya definimos para obtener el modelo relacionado
        // y devolvemos el atributo 'name' de ese modelo.
        return $this->status()->first()->name;
    }
}