import type { ReactNode } from 'react'

type Tono = 'error' | 'exito' | 'informacion'

const ESTILOS: Record<Tono, string> = {
  error: 'border-red-700 bg-red-50 text-red-900',
  exito: 'border-verde bg-rosa-suave text-verde',
  informacion: 'border-piedra bg-rosa-suave text-piedra',
}

/**
 * `role="alert"` para los errores, que hay que anunciar en cuanto aparecen, y
 * `status` para el resto, que no debe interrumpir lo que se este leyendo.
 */
export function Aviso({ tono, children }: { tono: Tono; children: ReactNode }) {
  return (
    <p
      role={tono === 'error' ? 'alert' : 'status'}
      className={`rounded-fefu border-l-4 px-4 py-3 text-base ${ESTILOS[tono]}`}
    >
      {children}
    </p>
  )
}
