<?php

use App\Enums\UserRole;

/**
 * D23 — los ids no son arbitrarios.
 *
 * El rol al que se llega por accidente —un `role_id` sin fijar, un 1 usado
 * como valor por defecto de un entero— tiene que ser el que menos puede
 * hacer. Es la misma familia de fallo que produjo SEC-001, donde el valor
 * que acababa aplicandose era el privilegiado.
 *
 * Si alguien invierte estos dos numeros, este test cae.
 */
it('respalda los roles con enteros y deja el 1 en cliente', function () {
    expect(UserRole::Customer->value)->toBe(1)
        ->and(UserRole::Admin->value)->toBe(2);
});

it('crea toda cuenta nueva como cliente', function () {
    expect(UserRole::default())->toBe(UserRole::Customer);
});

/**
 * El nombre que viaja por la API. Nunca se expone el entero: el id es un
 * detalle de la base de datos, y filtrarlo invita a que el cliente empiece
 * a razonar con numeros de rol.
 */
it('expone cada rol por su nombre, no por su id', function () {
    expect(UserRole::Customer->slug())->toBe('customer')
        ->and(UserRole::Admin->slug())->toBe('admin');
});
