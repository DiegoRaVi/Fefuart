<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Los filtros de los listados del backoffice.
 *
 * Estan aqui y no repartidos por los controllers porque pedidos y eventos
 * hacen lo mismo con distintos campos, y el detalle delicado —agrupar los OR
 * del buscador— es facil de olvidar la segunda vez.
 */
trait SeBusca
{
    /**
     * Busca el termino en varios campos a la vez, **agrupando los OR**.
     *
     * Sin ese grupo, la condicion se lee como `status = paid OR nombre LIKE …`
     * y el filtro de estado deja de aplicarse: salen pedidos de cualquier
     * estado en cuanto se escribe algo en el buscador.
     *
     * Nota de rendimiento: el comodin por delante (`%marta%`) impide usar
     * indice, asi que esto es un escaneo. Con el volumen de este negocio
     * —decenas o cientos de filas— es irrelevante; si algun dia deja de
     * serlo, la salida es un indice de texto completo, no reordenar esto.
     *
     * @param  Builder<static>  $query
     * @param  list<string>  $columnas  Columnas de esta tabla
     * @param  array<string, list<string>>  $relaciones  ['user' => ['name','email']]
     */
    public function scopeBuscar(
        Builder $query,
        ?string $termino,
        array $columnas,
        array $relaciones = [],
        bool $porId = true,
    ): void {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return;
        }

        /*
         * Un numero corto es un numero de pedido, no un fragmento de nada
         * mas: buscar «1» tambien por telefono devolvia casi todo, porque
         * la mayoria de los telefonos contienen un uno. A partir de cuatro
         * cifras ya puede ser un trozo de telefono y se busca por los dos.
         */
        $soloPorId = $porId && ctype_digit($termino) && mb_strlen($termino) < 4;

        $query->where(function (Builder $grupo) use ($termino, $columnas, $relaciones, $porId, $soloPorId) {
            // El numero de pedido es el dato mas preciso que puede tener
            // quien pregunta, y viene en el correo de confirmacion.
            if ($porId && ctype_digit($termino)) {
                $grupo->orWhere($grupo->getModel()->getQualifiedKeyName(), (int) $termino);
            }

            if ($soloPorId) {
                return;
            }

            foreach ($columnas as $columna) {
                $grupo->orWhere($columna, 'like', "%{$termino}%");
            }

            foreach ($relaciones as $relacion => $suyas) {
                $grupo->orWhereHas($relacion, function (BuilderContract $q) use ($suyas, $termino) {
                    foreach ($suyas as $indice => $columna) {
                        $indice === 0
                            ? $q->where($columna, 'like', "%{$termino}%")
                            : $q->orWhere($columna, 'like', "%{$termino}%");
                    }
                });
            }
        });
    }

    /**
     * Rango de fechas inclusivo por los dos lados.
     *
     * `hasta` se lleva al final del dia: quien escribe «hasta el 13» espera
     * que salga lo del 13, y comparando contra un timestamp a secas un
     * registro de las 10:00 de ese dia se quedaria fuera.
     *
     * @param  Builder<static>  $query
     */
    public function scopeEntreFechas(
        Builder $query,
        string $columna,
        ?string $desde,
        ?string $hasta,
    ): void {
        if ($desde) {
            $query->where($columna, '>=', CarbonImmutable::parse($desde)->startOfDay());
        }

        if ($hasta) {
            $query->where($columna, '<=', CarbonImmutable::parse($hasta)->endOfDay());
        }
    }
}
