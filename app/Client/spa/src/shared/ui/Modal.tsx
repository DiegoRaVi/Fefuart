import { useEffect, useId, useRef, type ReactNode } from 'react'

interface Props {
  titulo: string
  abierto: boolean
  onCerrar: () => void
  children: ReactNode
}

/**
 * Un modal con `<dialog>` nativo.
 *
 * Se usa el elemento del navegador y no un `div` con `position: fixed`
 * porque `showModal()` ya trae lo que hay que hacer bien y es facil olvidar:
 * atrapa el foco dentro, cierra con Escape, hace inerte lo de detras y pinta
 * el fondo. Reimplementarlo a mano es como se acaban colando modales que un
 * lector de pantalla atraviesa.
 */
export function Modal({ titulo, abierto, onCerrar, children }: Props) {
  const dialog = useRef<HTMLDialogElement>(null)
  const id = useId()

  useEffect(() => {
    const elemento = dialog.current

    if (!elemento) {
      return
    }

    if (abierto && !elemento.open) {
      elemento.showModal()
    }

    if (!abierto && elemento.open) {
      elemento.close()
    }
  }, [abierto])

  return (
    <dialog
      ref={dialog}
      aria-labelledby={id}
      // Escape dispara `close`; hay que avisar a quien controla el estado o
      // el modal se cerraria por dentro y seguiria abierto por fuera.
      onClose={onCerrar}
      // Pinchar en el fondo cierra. El `<dialog>` recibe el clic cuando cae
      // fuera del contenido, que es como se distingue uno del otro.
      onClick={(e) => {
        if (e.target === dialog.current) {
          onCerrar()
        }
      }}
      /*
       * `m-auto` no es decoracion: el navegador centra el `<dialog>` modal
       * con `margin: auto`, y el reset de Tailwind pone `margin: 0` en todo,
       * asi que sin esto sale pegado a la esquina superior izquierda.
       */
      className="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-fefu p-0 text-piedra backdrop:bg-black/40"
    >
      <div className="space-y-4 p-6">
        <div className="flex items-start justify-between gap-4">
          <h2 id={id} className="text-seccion text-verde">
            {titulo}
          </h2>

          <button
            type="button"
            onClick={onCerrar}
            aria-label="Cerrar"
            className="rounded-fefu px-2 text-2xl leading-none text-piedra hover:text-verde"
          >
            ×
          </button>
        </div>

        {children}
      </div>
    </dialog>
  )
}
