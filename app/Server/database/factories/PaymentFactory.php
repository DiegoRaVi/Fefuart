<?php

namespace Database\Factories;

use App\Enums\PaymentKind;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payable_type' => Order::class,
            'payable_id' => Order::factory(),
            'provider' => 'stripe',
            'provider_session_id' => 'cs_test_'.Str::random(24),
            'provider_payment_intent_id' => null,
            'amount' => '45.00',
            'currency' => 'EUR',
            'status' => PaymentStatus::Pending,
            'kind' => PaymentKind::Full,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Succeeded,
            'provider_payment_intent_id' => 'pi_test_'.Str::random(24),
            'paid_at' => now(),
        ]);
    }

    public function failed(string $motivo = 'card_declined'): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed,
            'failure_reason' => $motivo,
        ]);
    }
}
