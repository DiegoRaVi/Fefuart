<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\SeBusca;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El pedido. Los importes son de solo lectura desde fuera: los calcula
 * PricingService y los escriben CartService y CheckoutService (SEC-006).
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SeBusca, SoftDeletes;

    /**
     * SEC-003/SEC-006: ni `status`, ni `subtotal`, ni `shipping_total`, ni
     * `total`, ni `placed_at` son asignables en masa. En v1 el cliente movia
     * el estado y el total con un `PATCH /orders/{id}` y el servidor los
     * aceptaba tal cual.
     *
     * @var list<string>
     */
    protected $fillable = [
        'shipping_name',
        'shipping_phone',
        'shipping_line1',
        'shipping_line2',
        'shipping_city',
        'shipping_province',
        'shipping_postal_code',
        'shipping_country',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * D3 — los cobros del pedido. Son varios y no uno porque un intento
     * caducado o rechazado no se borra: es justo el rastro que hay que poder
     * mirar cuando un cliente dice que pago y el pedido dice que no.
     *
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * @return BelongsTo<ShippingMethod, $this>
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopePlaced(Builder $query): void
    {
        $query->where('status', '!=', OrderStatus::Cart);
    }
}
