<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;

/**
 * SEC-004 / SEC-008 — la propiedad de un fichero se decide aqui y solo aqui.
 *
 * En v1 `DELETE /api/products/{id}` estaba bajo `IsUserAuth` sin comprobar
 * nada, de modo que cualquier cuenta autenticada borraba el producto de otro
 * y su imagen del disco. El commit que «arreglo» el problema (`eb84d3c`)
 * movio el borrado al lado inseguro en vez de comprobar la propiedad.
 */
class MediaAssetPolicy
{
    /**
     * Ver un fichero: su duenno. La administradora tambien, porque necesita
     * la foto de referencia para dibujar el encargo.
     */
    public function view(User $user, MediaAsset $media): bool
    {
        return $this->owns($user, $media) || $user->isAdmin();
    }

    /**
     * Adjuntar un fichero a una linea del carrito exige ser su duenno — la
     * administradora no encarga por nadie. Sin esto, un `reference_media_id`
     * ajeno colaria la foto de otra persona en el pedido propio.
     */
    public function attach(User $user, MediaAsset $media): bool
    {
        return $this->owns($user, $media);
    }

    public function delete(User $user, MediaAsset $media): bool
    {
        return $this->owns($user, $media) || $user->isAdmin();
    }

    private function owns(User $user, MediaAsset $media): bool
    {
        return $user->id === $media->user_id;
    }
}
