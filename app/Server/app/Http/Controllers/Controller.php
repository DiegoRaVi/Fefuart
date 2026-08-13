<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * `AuthorizesRequests` da el `$this->authorize()` de las Policies. Laravel 12
 * ya no lo trae en el esqueleto, y aqui hace falta en todos los controllers
 * que tocan un recurso propio: la autorizacion pasa siempre por Policy y
 * nunca por una comparacion inline (SEC-003, SEC-004, SEC-008, SEC-009).
 */
abstract class Controller
{
    use AuthorizesRequests;
}
