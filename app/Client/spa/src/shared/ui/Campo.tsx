import { useId, type InputHTMLAttributes, type Ref } from 'react'

interface Props extends InputHTMLAttributes<HTMLInputElement> {
  etiqueta: string
  error?: string
  ayuda?: string
  ref?: Ref<HTMLInputElement>
}

/**
 * Campo con etiqueta y error asociados por id.
 *
 * `aria-invalid` y `aria-describedby` no son decoracion: sin ellos, quien usa
 * un lector de pantalla oye el campo pero no se entera de que esta mal.
 */
export function Campo({ etiqueta, error, ayuda, ref, ...props }: Props) {
  const id = useId()
  const idError = `${id}-error`
  const idAyuda = `${id}-ayuda`

  const descrito = [error ? idError : null, ayuda ? idAyuda : null]
    .filter(Boolean)
    .join(' ')

  return (
    <div className="space-y-1">
      <label htmlFor={id} className="block font-bold text-verde">
        {etiqueta}
      </label>

      <input
        {...props}
        id={id}
        ref={ref}
        aria-invalid={error ? true : undefined}
        aria-describedby={descrito || undefined}
        className={`w-full rounded-fefu border px-3 py-2 text-base outline-none ${
          error ? 'border-red-700' : 'border-piedra/40'
        }`}
      />

      {ayuda && (
        <p id={idAyuda} className="text-sm text-piedra">
          {ayuda}
        </p>
      )}

      {error && (
        <p id={idError} role="alert" className="text-sm font-bold text-red-700">
          {error}
        </p>
      )}
    </div>
  )
}
