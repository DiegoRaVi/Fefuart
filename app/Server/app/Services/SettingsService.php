<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Los ajustes del negocio, con su tipo y sus limites en un solo sitio.
 *
 * N15 pide que el porcentaje de la señal sea configurable desde el
 * backoffice. Lo que no puede ser configurable es *que* claves existen: la
 * lista vive aqui, de modo que `PATCH /api/admin/settings` no pueda
 * convertirse en un almacen de claves arbitrarias.
 */
class SettingsService
{
    private const CACHE = 'settings';

    /**
     * Cada ajuste con su valor por defecto y sus limites. El defecto es lo
     * que vale antes de que nadie lo toque y tambien lo que se usa si la
     * fila desaparece.
     *
     * @var array<string, array{defecto: int, min: int, max: int, etiqueta: string}>
     */
    private const AJUSTES = [
        'deposit_percentage' => [
            'defecto' => 30,
            'min' => 0,
            'max' => 100,
            'etiqueta' => 'Porcentaje de señal sobre el presupuesto',
        ],
        'quote_validity_days' => [
            'defecto' => 14,
            'min' => 1,
            'max' => 365,
            'etiqueta' => 'Dias que vale un presupuesto',
        ],
    ];

    /** N15 — la señal es un porcentaje fijo del presupuesto. */
    public function porcentajeDeSenal(): int
    {
        return $this->entero('deposit_percentage');
    }

    /** P1 — cuanto vale un presupuesto antes de caducar. */
    public function diasDeValidezDelPresupuesto(): int
    {
        return $this->entero('quote_validity_days');
    }

    /**
     * @return array<string, int>
     */
    public function todos(): array
    {
        return Cache::rememberForever(self::CACHE, function () {
            $guardados = Setting::query()->pluck('value', 'key');

            $ajustes = [];

            foreach (self::AJUSTES as $clave => $definicion) {
                $ajustes[$clave] = (int) ($guardados[$clave] ?? $definicion['defecto']);
            }

            return $ajustes;
        });
    }

    /**
     * @return array<string, array{valor: int, min: int, max: int, etiqueta: string}>
     */
    public function descritos(): array
    {
        $valores = $this->todos();
        $descritos = [];

        foreach (self::AJUSTES as $clave => $definicion) {
            $descritos[$clave] = [
                'valor' => $valores[$clave],
                'min' => $definicion['min'],
                'max' => $definicion['max'],
                'etiqueta' => $definicion['etiqueta'],
            ];
        }

        return $descritos;
    }

    /**
     * @param  array<string, int|string>  $valores
     * @return array<string, int>
     */
    public function guardar(array $valores): array
    {
        foreach ($valores as $clave => $valor) {
            $definicion = self::AJUSTES[$clave] ?? throw new InvalidArgumentException("Ajuste desconocido: {$clave}.");

            $entero = (int) $valor;

            if ($entero < $definicion['min'] || $entero > $definicion['max']) {
                throw new InvalidArgumentException(
                    "«{$definicion['etiqueta']}» tiene que estar entre {$definicion['min']} y {$definicion['max']}."
                );
            }

            // `key` no es asignable en masa —la lista de ajustes validos es
            // esta, no la que llegue en una peticion—, asi que se asigna a
            // mano en vez de por `updateOrCreate`.
            $ajuste = Setting::query()->find($clave) ?? new Setting;
            $ajuste->key = $clave;
            $ajuste->value = (string) $entero;
            $ajuste->save();
        }

        Cache::forget(self::CACHE);

        return $this->todos();
    }

    /**
     * @return list<string>
     */
    public function claves(): array
    {
        return array_keys(self::AJUSTES);
    }

    private function entero(string $clave): int
    {
        return $this->todos()[$clave];
    }
}
