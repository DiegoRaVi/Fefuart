<?php

namespace App\Enums;

/**
 * Por que campo busca el backoffice cuando la busqueda es precisa.
 *
 * La caja rapida mira en todos a la vez, que es comodo pero impreciso:
 * escribir «600» mezcla telefonos, numeros de pedido y cualquier nombre que
 * lleve esas cifras. Con el modal se elige **uno**, y entonces lo que salga
 * es exactamente lo que se pidio.
 *
 * Solo se puede elegir uno a la vez a proposito: dos campos rellenos
 * obligarian a decidir si se combinan con Y o con O, y ninguna de las dos
 * respuestas es evidente para quien esta buscando un pedido con prisa.
 */
enum CampoDeBusqueda: string
{
    case Numero = 'numero';
    case Nombre = 'nombre';
    case Email = 'email';
    case Telefono = 'telefono';

    /** Titulo y lugar solo tienen sentido en eventos. */
    case Titulo = 'titulo';
    case Lugar = 'lugar';

    /**
     * Columnas de la propia tabla que mira este campo.
     *
     * @return list<string>
     */
    public function columnasDePedido(): array
    {
        return match ($this) {
            // El nombre del envio puede no ser el de la cuenta: un regalo, o
            // alguien que pide para otra persona. Quien busca no sabe cual le
            // han dado, asi que «nombre» mira en los dos.
            self::Nombre => ['shipping_name'],
            self::Telefono => ['shipping_phone'],
            default => [],
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function relacionesDePedido(): array
    {
        return match ($this) {
            self::Nombre => ['user' => ['name']],
            self::Email => ['user' => ['email']],
            default => [],
        };
    }

    public function buscaPorId(): bool
    {
        return $this === self::Numero;
    }

    /**
     * @return list<string>
     */
    public function columnasDeEvento(): array
    {
        return match ($this) {
            self::Titulo => ['title'],
            self::Lugar => ['location'],
            self::Telefono => ['phone'],
            default => [],
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public function relacionesDeEvento(): array
    {
        return match ($this) {
            self::Nombre => ['user' => ['name']],
            self::Email => ['user' => ['email']],
            default => [],
        };
    }

    /**
     * Los que ofrece el buscador de pedidos. `titulo` y `lugar` no aparecen
     * porque un pedido no tiene ninguno de los dos.
     *
     * @return list<self>
     */
    public static function dePedidos(): array
    {
        return [self::Numero, self::Nombre, self::Email, self::Telefono];
    }

    /**
     * En eventos no se busca por numero: nadie llama diciendo «el evento
     * numero siete».
     *
     * @return list<self>
     */
    public static function deEventos(): array
    {
        return [self::Titulo, self::Lugar, self::Nombre, self::Email, self::Telefono];
    }
}
