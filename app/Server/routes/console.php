<?php

use Illuminate\Support\Facades\Schedule;

/**
 * La foto de referencia se sube antes de que exista la linea del carrito, de
 * modo que quien la elige y se va deja el fichero sin dueno. Una vez al dia
 * es de sobra: el margen del propio comando son 24 horas.
 *
 * En local hay que lanzarlo a mano (`php artisan media:limpiar`) o tener
 * corriendo `php artisan schedule:work`. En produccion lo llama el cron
 * (Fase 8).
 */
Schedule::command('media:limpiar')->dailyAt('04:00');
