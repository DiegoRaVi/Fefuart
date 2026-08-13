<?php

namespace App\Enums;

/**
 * Roles de usuario (D8: solo dos, sin sistema de permisos en base de datos).
 *
 * D23 — respaldado por entero contra la tabla `roles`. Los ids van en este
 * orden a proposito: el rol al que se llega por accidente —un `role_id` sin
 * fijar, un 1 usado como valor por defecto de un entero— debe ser el que
 * menos puede hacer. Es la misma familia de fallo que produjo SEC-001, donde
 * el valor que acababa aplicandose era el privilegiado.
 *
 * El codigo nunca escribe el numero: siempre el caso del enum.
 */
enum UserRole: int
{
    case Customer = 1;
    case Admin = 2;

    /**
     * Rol con el que se crea toda cuenta nueva. La promocion a `Admin` nunca
     * ocurre por peticion HTTP (SEC-001, N20).
     */
    public static function default(): self
    {
        return self::Customer;
    }

    /**
     * El nombre con el que el rol viaja por la API y se guarda en `roles`.
     *
     * El entero no sale nunca del backend: es un detalle de la base de datos,
     * y publicarlo invita al cliente a razonar con numeros de rol.
     */
    public function slug(): string
    {
        return match ($this) {
            self::Customer => 'customer',
            self::Admin => 'admin',
        };
    }
}
