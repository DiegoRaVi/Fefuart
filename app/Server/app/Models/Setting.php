<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un ajuste del negocio. Se lee y se escribe por SettingsService, que es
 * quien conoce el tipo y los limites de cada clave.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * `key` no es asignable: la lista de ajustes validos la declara
     * SettingsService y no puede crecer desde una peticion. Sin esto,
     * `PATCH /api/admin/settings` seria un almacen de claves arbitrarias.
     *
     * @var list<string>
     */
    protected $fillable = ['value'];
}
