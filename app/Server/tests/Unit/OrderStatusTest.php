<?php

use App\Enums\OrderStatus;

/**
 * En v1 el estado del pedido lo decidia el navegador: «Pagar» era un
 * `PATCH /orders/{id} {status:'pending'}` y el servidor aceptaba cualquier
 * valor del enum, incluido `paid` (SEC-003). Aqui las transiciones validas
 * se declaran una vez y se comprueban en los Services.
 *
 * No hay fase de boceto (D19).
 */
it('permite avanzar por la maquina de estados', function (OrderStatus $desde, OrderStatus $hasta) {
    expect($desde->canTransitionTo($hasta))->toBeTrue();
})->with([
    [OrderStatus::Cart, OrderStatus::PendingPayment],
    [OrderStatus::PendingPayment, OrderStatus::Paid],
    [OrderStatus::Paid, OrderStatus::InProgress],
    [OrderStatus::InProgress, OrderStatus::Shipped],
    // Un pedido digital no se envia: pasa de en curso a completado.
    [OrderStatus::InProgress, OrderStatus::Completed],
    [OrderStatus::Shipped, OrderStatus::Completed],
]);

it('rechaza los saltos que en v1 eran un PATCH cualquiera', function (OrderStatus $desde, OrderStatus $hasta) {
    expect($desde->canTransitionTo($hasta))->toBeFalse();
})->with([
    // El salto que permitia marcarse un pedido como pagado sin pasar por caja.
    [OrderStatus::Cart, OrderStatus::Paid],
    [OrderStatus::Cart, OrderStatus::Completed],
    [OrderStatus::PendingPayment, OrderStatus::Shipped],
    [OrderStatus::Paid, OrderStatus::Cart],
    // Estados finales.
    [OrderStatus::Completed, OrderStatus::InProgress],
    [OrderStatus::Cancelled, OrderStatus::Paid],
]);

/**
 * N12 — el cliente cancela solo antes de pagar. Una vez pagado se acuerda
 * con la artista y lo aplica ella desde el backoffice.
 */
it('deja cancelar solo antes de pagar', function () {
    expect(OrderStatus::Cart->isCancellableByCustomer())->toBeTrue()
        ->and(OrderStatus::PendingPayment->isCancellableByCustomer())->toBeTrue()
        ->and(OrderStatus::Paid->isCancellableByCustomer())->toBeFalse()
        ->and(OrderStatus::Shipped->isCancellableByCustomer())->toBeFalse();
});

it('sabe que un pedido en el carrito todavia no se ha hecho', function () {
    expect(OrderStatus::Cart->isPlaced())->toBeFalse()
        ->and(OrderStatus::PendingPayment->isPlaced())->toBeTrue();
});
