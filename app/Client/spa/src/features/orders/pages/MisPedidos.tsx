import { useState } from 'react'
import { Link } from 'react-router'

import { euros } from '@/shared/lib/dinero'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Cargando } from '@/shared/ui/Cargando'

import { nombreDelEstado, usePedidos } from '../api'

/**
 * BUG-003 — en v1 `GET /user-orders` apuntaba a un metodo que esperaba un
 * `{id}` que la ruta no definia, asi que respondia 500. Los pedidos salen de
 * la sesion, no de la URL.
 */
export function MisPedidos() {
  const [pagina, setPagina] = useState(1)
  const { data, isPending, isError, error } = usePedidos(pagina)

  if (isPending) {
    return <Cargando texto="Cargando tus pedidos..." />
  }

  if (isError) {
    return <Aviso tono="error">{error.message}</Aviso>
  }

  if (data.data.length === 0) {
    return (
      <div className="mx-auto max-w-2xl space-y-4">
        <h1 className="text-titulo text-verde">Mis pedidos</h1>
        <Aviso tono="informacion">Todavia no has hecho ningun pedido.</Aviso>
        <Link className="text-verde underline underline-offset-4" to="/encargos">
          Ver los encargos
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <h1 className="text-titulo text-verde">Mis pedidos</h1>

      <ul className="space-y-3">
        {data.data.map((pedido) => (
          <li key={pedido.id}>
            <Link
              to={`/pedidos/${pedido.id}`}
              className="flex flex-wrap items-baseline justify-between gap-2 rounded-fefu bg-rosa-suave p-4 transition-shadow duration-300 hover:shadow-[0_2px_8px_rgba(0,0,0,0.15)]"
            >
              <div>
                <span className="text-apartado text-verde">Pedido #{pedido.id}</span>
                <p className="text-sm text-piedra">
                  {pedido.placed_at &&
                    new Date(pedido.placed_at).toLocaleDateString('es-ES', {
                      day: 'numeric',
                      month: 'long',
                      year: 'numeric',
                    })}
                  {' · '}
                  {pedido.items.length}{' '}
                  {pedido.items.length === 1 ? 'encargo' : 'encargos'}
                </p>
              </div>

              <div className="text-right">
                <p className="text-apartado text-verde">{euros(pedido.total)}</p>
                <p className="text-sm text-piedra">{nombreDelEstado(pedido.status)}</p>
              </div>
            </Link>
          </li>
        ))}
      </ul>

      {data.meta.last_page > 1 && (
        <nav aria-label="Paginacion" className="flex items-center justify-between gap-4">
          <Boton
            type="button"
            variante="secundario"
            disabled={pagina <= 1}
            onClick={() => setPagina((p) => p - 1)}
          >
            Anteriores
          </Boton>

          <span className="text-base text-piedra">
            Pagina {data.meta.current_page} de {data.meta.last_page}
          </span>

          <Boton
            type="button"
            variante="secundario"
            disabled={pagina >= data.meta.last_page}
            onClick={() => setPagina((p) => p + 1)}
          >
            Siguientes
          </Boton>
        </nav>
      )}
    </div>
  )
}
