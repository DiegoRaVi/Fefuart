import { useMutation } from '@tanstack/react-query'
import { useId, useRef, useState } from 'react'

import { toApiError } from '@/shared/api/errors'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'

import { borrarFoto, subirFoto, type MediaAsset } from './api'

interface Props {
  foto: MediaAsset | null
  onCambio: (foto: MediaAsset | null) => void
  error?: string
}

/**
 * N9 — la foto es el material de partida, no un adjunto.
 *
 * Se sube en cuanto se elige, de modo que el cliente ve la miniatura y sabe
 * que ha entrado antes de confirmar el encargo. Los ficheros que nadie llega
 * a usar los recoge `media:limpiar`.
 */
export function SubidaDeFoto({ foto, onCambio, error }: Props) {
  const id = useId()
  const input = useRef<HTMLInputElement>(null)
  const [errorSubida, setErrorSubida] = useState<string | null>(null)

  const subir = useMutation({
    mutationFn: subirFoto,
    onSuccess: (subida) => {
      setErrorSubida(null)
      onCambio(subida)
    },
    onError: (e) => setErrorSubida(toApiError(e).message),
  })

  const quitar = useMutation({
    mutationFn: borrarFoto,
    onSuccess: () => onCambio(null),
    // Si el borrado falla, la foto se queda en el servidor pero el cliente
    // deja de usarla; `media:limpiar` la recogera.
    onError: () => onCambio(null),
  })

  function elegir(file: File | undefined) {
    if (file) {
      subir.mutate(file)
    }
  }

  if (foto) {
    return (
      <div className="space-y-2">
        <p className="font-bold text-verde">Tu foto</p>

        <div className="flex items-start gap-4 rounded-fefu bg-rosa-suave p-4">
          {foto.url && (
            <img
              src={foto.url}
              alt={`Vista previa de ${foto.original_name}`}
              className="h-24 w-24 rounded-fefu object-cover"
            />
          )}

          <div className="flex-1 space-y-2">
            <p className="text-base break-all">{foto.original_name}</p>

            <Boton
              type="button"
              variante="secundario"
              onClick={() => quitar.mutate(foto.id)}
              disabled={quitar.isPending}
            >
              {quitar.isPending ? 'Quitando...' : 'Elegir otra'}
            </Boton>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-2">
      <label htmlFor={id} className="block font-bold text-verde">
        Tu foto
      </label>

      <p className="text-sm text-piedra">
        El dibujo se hace a partir de ella. JPEG, PNG o WebP, hasta 5 MB.
      </p>

      <input
        id={id}
        ref={input}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        disabled={subir.isPending}
        aria-invalid={error || errorSubida ? true : undefined}
        onChange={(e) => elegir(e.target.files?.[0])}
        className="block w-full text-base file:mr-4 file:rounded-fefu file:border-0 file:bg-piedra file:px-4 file:py-2 file:text-white hover:file:bg-verde"
      />

      {subir.isPending && (
        <p role="status" className="text-sm text-piedra">
          Subiendo la foto...
        </p>
      )}

      {(errorSubida ?? error) && <Aviso tono="error">{errorSubida ?? error}</Aviso>}
    </div>
  )
}
