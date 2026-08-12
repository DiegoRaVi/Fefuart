<?php

namespace App\Enums;

/**
 * Roles de usuario (D8: solo dos, sin sistema de permisos en base de datos).
 *
 * Los valores coinciden con los que ya existen en la columna `users.role`,
 * de modo que el cast funciona sin migracion. La Fase 2 renombra el caso
 * `User` a `Customer` junto con el cambio de esquema (N20).
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
