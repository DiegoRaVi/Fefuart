<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * N15 — los ajustes que la artista puede cambiar sin tocar codigo.
 *
 * Hoy son dos: el porcentaje de la señal y los dias que vale un
 * presupuesto. Que claves existen no se decide aqui ni en la peticion, sino
 * en SettingsService; sin eso, esto seria un almacen de claves arbitrarias
 * escribible desde fuera.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $ajustes) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->ajustes->descritos()]);
    }

    public function update(Request $request): JsonResponse
    {
        // Las claves admitidas salen del servicio, no de una lista repetida
        // aqui: anadir un ajuste no puede exigir acordarse de tocar dos
        // sitios.
        $reglas = [];

        foreach ($this->ajustes->claves() as $clave) {
            $reglas[$clave] = ['sometimes', 'integer'];
        }

        $valores = $request->validate($reglas);

        if ($valores === []) {
            throw ValidationException::withMessages([
                'ajustes' => 'No has enviado ningun ajuste que cambiar.',
            ]);
        }

        // Los limites de cada ajuste los conoce el servicio, que es tambien
        // quien los aplica al leerlos.
        try {
            $this->ajustes->guardar($valores);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['ajustes' => $e->getMessage()]);
        }

        return response()->json(['data' => $this->ajustes->descritos()]);
    }
}
