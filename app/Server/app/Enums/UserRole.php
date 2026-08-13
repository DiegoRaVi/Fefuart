<?php

namespace App\Enums;

/**
 * Roles de usuario (D8: solo dos, sin sistema de permisos en base de datos).
 *
 * Los valores coinciden con los que hay hoy en la columna `users.role`, de
 * modo que el cast funciona sin migracion.
 *
 * La Fase 2 lo sustituye por un enum respaldado por entero contra la tabla
 * `roles` (D23): `Customer = 1`, `Admin = 2`. Los ids van en ese orden a
 * proposito — el rol al que se llega por accidente debe ser el que menos
 * puede hacer. El codigo nunca escribe el numero, siempre el caso del enum.
 */
enum UserRole: string
{
    case User = 'user';
    case Admin = 'admin';

    /**
     * Rol con el que se crea toda cuenta nueva. La promocion a `Admin` nunca
     * ocurre por peticion HTTP (SEC-001).
     */
    public static function default(): self
    {
        return self::User;
    }
}
