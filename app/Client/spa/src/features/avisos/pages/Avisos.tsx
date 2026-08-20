import { useState } from 'react'
import { Link } from 'react-router'

import { useSesion } from '@/features/auth/sesion'
import { Aviso as Recuadro } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Cargando } from '@/shared/ui/Cargando'

import { type Notificacion, useAvisos, useMarcarLeido } from '../api'

/**
 * D10 — el centro de avisos dentro de la aplicacion.
 *
 * Cada fila se pinta con los mismos campos venga de donde venga el aviso, y
 * eso es lo que evita el `switch` por tipo que habria que ampliar cada vez
 * que nazca uno nuevo.
 */
export function Avisos() {
  const { autenticado } = useSesion()
  const [pagina, setPagina] = useState(1)
  const { data, isPending, isError, error } = useAvisos(autenticado, pagina)

  if (isPending) {
    return <Cargando texto="Cargando tus avisos..." />
  }

  if (isError) {
    return <Recuadro tono="error">{error.message}</Recuadro>
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <h1 className="text-titulo text-verde">Avisos</h1>

      {data.data.length === 0 ? (
        <Recuadro tono="informacion">Todavía no tienes avisos.</Recuadro>
      ) : (
        <ul className="space-y-3">
          {data.data.map((aviso) => (
            <Fila key={aviso.id} aviso={aviso} />
          ))}
        </ul>
      )}

      {data.meta.last_page > 1 && (
        <div className="flex items-center justify-between">
          <Boton
            type="button"
            variante="secundario"
            disabled={pagina === 1}
            onClick={() => setPagina((actual) => actual - 1)}
          >
            Anteriores
          </Boton>
          <span className="text-sm text-piedra">
            Pagina {data.meta.current_page} de {data.meta.last_page}
          </span>
          <Boton
            type="button"
            variante="secundario"
            disabled={pagina === data.meta.last_page}
            onClick={() => setPagina((actual) => actual + 1)}
          >
            Siguientes
          </Boton>
        </div>
      )}
    </div>
  )
}

function Fila({ aviso }: { aviso: Notificacion }) {
  const marcar = useMarcarLeido()

  return (
    <li
      className={`rounded-fefu p-4 ${aviso.leido ? 'bg-rosa-suave/50' : 'bg-rosa-suave'}`}
    >
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <Link
          to={aviso.enlace}
          className="text-apartado text-verde underline-offset-4 hover:underline"
        >
          {aviso.titulo}
        </Link>

        <time className="text-sm text-piedra" dateTime={aviso.creado_en}>
          {new Date(aviso.creado_en).toLocaleDateString('es-ES', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
          })}
        </time>
      </div>

      <p className="mt-1 text-base text-piedra">{aviso.cuerpo}</p>

      {/* Un aviso ya leido no ofrece el boton: no hay nada que marcar. */}
      {!aviso.leido && (
        <Boton
          type="button"
          variante="secundario"
          className="mt-3"
          disabled={marcar.isPending}
          onClick={() => marcar.mutate(aviso.id)}
        >
          Marcar como leido
        </Boton>
      )}
    </li>
  )
}
