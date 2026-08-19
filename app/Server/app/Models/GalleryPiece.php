<?php

namespace App\Models;

use Database\Factories\GalleryPieceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * D33 — una pieza de la galeria.
 *
 * Contenido, no catalogo: lo que se enseña no tiene por que coincidir con lo
 * que se vende. La categoria es texto y no una FK a `products` justamente por
 * eso.
 */
class GalleryPiece extends Model
{
    /** @use HasFactory<GalleryPieceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Los ids de las imagenes quedan fuera: los fija el controller a partir
     * de lo que devolvio el almacenamiento, nunca el cuerpo de la peticion.
     * Es la misma leccion de SEC-001 aplicada a la propiedad de un fichero.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'category',
        'description',
        'is_published',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'thumbnail_media_id');
    }

    /**
     * El escaparate: lo publicado, en el orden que fijo la artista.
     *
     * @param  Builder<self>  $query
     */
    public function scopeEscaparate($query): void
    {
        $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }
}
