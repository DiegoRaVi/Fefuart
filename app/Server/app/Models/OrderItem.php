<?php

namespace App\Models;

use App\Enums\DeliveryType;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El encargo concreto: un dibujo unico, con sus notas y su foto de partida
 * (D14, N9). `quantity` son copias de esa misma lamina, no encargos
 * distintos (N3).
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * SEC-006: los importes —`unit_price`, `additional_copy_price` y
     * `line_total`— no estan aqui. Los escribe CartService a partir de lo
     * que devuelve PricingService, nunca la peticion. En v1 el navegador
     * enviaba `price` y el validador lo aceptaba como `required|numeric`.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_notes',
        'quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_type' => DeliveryType::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'additional_copy_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function referenceMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'reference_media_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function deliveredMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'delivered_media_id');
    }
}
