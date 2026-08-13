<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un fichero subido. La propiedad se comprueba siempre por MediaAssetPolicy
 * contra `user_id`, nunca con un `if` inline (SEC-004, SEC-008).
 */
class MediaAsset extends Model
{
    /** @use HasFactory<\Database\Factories\MediaAssetFactory> */
    use HasFactory;

    protected $fillable = [
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'visibility',
    ];

    /**
     * `user_id` queda fuera de $fillable a proposito: el duenno lo fija el
     * controller a partir de la sesion, jamas el cuerpo de la peticion. Es la
     * misma leccion de SEC-001 aplicada a la propiedad de un fichero.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
