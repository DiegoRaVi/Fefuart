<?php

namespace App\Models;

use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un cobro. D3 — su estado va aparte del estado del negocio.
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * SEC-006 llevado hasta el final: aqui no hay nada asignable en masa.
     * El importe sale del pedido, que a su vez lo calculo PricingService, y
     * el estado solo lo mueve el webhook con firma verificada. Ningun campo
     * de esta tabla deberia poder venir de una peticion.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'kind' => PaymentKind::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
