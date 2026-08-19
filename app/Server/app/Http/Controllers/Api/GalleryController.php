<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGalleryPieceRequest;
use App\Http\Resources\GalleryPieceResource;
use App\Models\GalleryPiece;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * D33 — el escaparate publico.
 *
 * **Sin sesion a proposito.** Es lo que ve quien todavia no es cliente, y
 * para un negocio que vende dibujos es probablemente lo que mas encargos
 * trae. Exigir cuenta para mirar seria pedirle el DNI a alguien que pasa por
 * delante del escaparate.
 */
class GalleryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtros = $request->validate([
            'category' => ['sometimes', Rule::in(StoreGalleryPieceRequest::CATEGORIAS)],
        ]);

        $piezas = GalleryPiece::query()
            ->escaparate()
            ->with(['media', 'thumbnail'])
            ->when(
                isset($filtros['category']),
                fn ($query) => $query->where('category', $filtros['category']),
            )
            ->get();

        return GalleryPieceResource::collection($piezas);
    }
}
