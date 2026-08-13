<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Resources\MediaAssetResource;
use App\Models\MediaAsset;
use App\Services\MediaStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * N9 — la foto de referencia se sube primero y devuelve un id, que luego se
 * adjunta a la linea del carrito. Asi el formulario de encargo puede
 * mostrarla antes de confirmar nada.
 */
class MediaController extends Controller
{
    public function __construct(private readonly MediaStorageService $media) {}

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $media = $this->media->storeReferenceImage(
            $request->file('file'),
            $request->user(),
        );

        return MediaAssetResource::make($media)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(MediaAsset $media): Response
    {
        // SEC-004: por Policy, nunca por comparacion inline.
        $this->authorize('delete', $media);

        $this->media->delete($media);

        return response()->noContent();
    }
}
