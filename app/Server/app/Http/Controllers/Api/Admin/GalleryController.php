<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGalleryPieceRequest;
use App\Http\Requests\Admin\UpdateGalleryPieceRequest;
use App\Http\Resources\GalleryPieceResource;
use App\Models\GalleryPiece;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * D33 — la galeria la gestiona Felicitas, no un desarrollador.
 *
 * Es la misma decision que D5 tomo para el catalogo, y aqui pesa mas: su
 * archivo son 207 MB de fotos sin curar, y elegir cuales se enseñan es una
 * decision artistica que cambia con el tiempo.
 */
class GalleryController extends Controller
{
    /** Aqui si sale lo no publicado: es la vista de quien lo gestiona. */
    public function index(): AnonymousResourceCollection
    {
        $piezas = GalleryPiece::query()
            ->with(['media', 'thumbnail'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return GalleryPieceResource::collection($piezas);
    }

    public function store(
        StoreGalleryPieceRequest $request,
        MediaStorageService $medios,
    ): JsonResponse {
        $imagenes = $medios->storeGalleryImage($request->file('file'), $request->user());

        $pieza = new GalleryPiece($request->safe()->except('file'));

        // Los ids de las imagenes los fija el servidor a partir de lo que
        // devolvio el almacenamiento, nunca el cuerpo de la peticion.
        $pieza->media_asset_id = $imagenes['media']->id;
        $pieza->thumbnail_media_id = $imagenes['thumbnail']->id;

        // Al final de la fila: quien sube algo espera verlo, y reordenar es
        // otro gesto.
        $pieza->sort_order = (int) GalleryPiece::max('sort_order') + 1;
        $pieza->save();

        return GalleryPieceResource::make($pieza->load(['media', 'thumbnail']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateGalleryPieceRequest $request, GalleryPiece $gallery): GalleryPieceResource
    {
        $gallery->update($request->validated());

        return GalleryPieceResource::make($gallery->load(['media', 'thumbnail']));
    }

    /** Borrar la pieza se lleva sus dos imagenes: nada queda tirado. */
    public function destroy(GalleryPiece $gallery, MediaStorageService $medios): Response
    {
        $grande = $gallery->media;
        $mini = $gallery->thumbnail;

        $gallery->forceDelete();

        $medios->delete($grande);
        $medios->delete($mini);

        return response()->noContent();
    }

    /**
     * El orden en que se enseña la obra.
     *
     * Llega la lista completa de ids y no un par de posiciones: mover una
     * pieza cambia la de todas las que arrastra, y mandar solo el cambio
     * obligaria a que el cliente calculase el resto — que es como se acaban
     * teniendo dos ordenes distintos segun quien mire.
     *
     * @throws ValidationException
     */
    public function reorder(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:gallery_pieces,id'],
        ]);

        DB::transaction(function () use ($datos) {
            foreach ($datos['ids'] as $posicion => $id) {
                GalleryPiece::whereKey($id)->update(['sort_order' => $posicion + 1]);
            }
        });

        return $this->index();
    }
}
