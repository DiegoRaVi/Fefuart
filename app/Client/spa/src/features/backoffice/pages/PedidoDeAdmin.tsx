import { Link, useParams } from 'react-router'

import type { OrderItem } from '@/features/cart/api'
import { nombreDelEstado } from '@/features/orders/api'
import { euros } from '@/shared/lib/dinero'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Cargando } from '@/shared/ui/Cargando'

import { useCambiarEstadoDePedido, usePedidoDeAdmin } from '../api'

/**
 * Las transiciones posibles desde cada estado. Es la misma tabla que declara
 * `OrderStatus` en el backend, que es quien manda: aqui solo se usa para no
 * ofrecer botones que van a devolver 422.
 */
const SIGUIENTES: Record<string, string[]> = {
  cart: [],
  pending_payment: ['paid', 'cancelled'],
  paid: ['in_progress', 'cancelled'],
  in_progress: ['shipped', 'completed', 'cancelled'],
  shipped: ['completed'],
  completed: [],
  cancelled: [],
}

export function PedidoDeAdmin() {
  const { id = '' } = useParams()
  const { data: pedido, isPending, isError, error } = usePedidoDeAdmin(id)
  const cambiar = useCambiarEstadoDePedido(id)

  if (isPending) {
    return <Cargando texto="Cargando el pedido..." />
  }

  if (isError) {
    return <Aviso tono="error">{error.message}</Aviso>
  }

  const siguientes = SIGUIENTES[pedido.status] ?? []

  return (
    <div className="max-w-3xl space-y-8">
      <div className="space-y-2">
        <Link className="text-base text-piedra underline underline-offset-4" to="/backoffice/pedidos">
          Volver a los pedidos
        </Link>

        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <h1 className="text-titulo text-verde">Pedido #{pedido.id}</h1>
          <p className="rounded-fefu bg-rosa px-3 py-1 text-verde">
            {nombreDelEstado(pedido.status)}
          </p>
        </div>

        {/* SEC-009 — estos datos solo llegan porque quien pregunta es la
            administradora; el servidor no los incluye en las rutas de
            cliente. */}
        {pedido.customer && (
          <p className="text-base text-piedra">
            {pedido.customer.name} ·{' '}
            <a
              className="underline underline-offset-4"
              href={`mailto:${pedido.customer.email}`}
            >
              {pedido.customer.email}
            </a>
          </p>
        )}

        {pedido.placed_at && (
          <p className="text-sm text-piedra">
            Hecho el {new Date(pedido.placed_at).toLocaleDateString('es-ES')}
          </p>
        )}
      </div>

      <section aria-labelledby="encargos" className="space-y-3">
        <h2 id="encargos" className="text-seccion text-verde">
          Que hay que dibujar
        </h2>

        <ul className="space-y-3">
          {pedido.items.map((linea) => (
            <li key={linea.id}>
              <Linea linea={linea} />
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="importes" className="space-y-1 border-t border-piedra/20 pt-4">
        <h2 id="importes" className="sr-only">
          Importes
        </h2>
        <div className="flex justify-between text-base">
          <span>Encargos</span>
          <span>{euros(pedido.subtotal)}</span>
        </div>
        <div className="flex justify-between text-base">
          <span>Envio</span>
          <span>{euros(pedido.shipping_total)}</span>
        </div>
        <div className="flex justify-between text-seccion text-verde">
          <span>Total</span>
          <span>{euros(pedido.total)}</span>
        </div>
      </section>

      {pedido.shipping_address.line1 && (
        <section aria-labelledby="envio" className="space-y-1">
          <h2 id="envio" className="text-seccion text-verde">
            Envio
          </h2>
          <address className="text-base not-italic text-piedra">
            {pedido.shipping_address.name && <p>{pedido.shipping_address.name}</p>}
            <p>{pedido.shipping_address.line1}</p>
            {pedido.shipping_address.line2 && <p>{pedido.shipping_address.line2}</p>}
            <p>
              {pedido.shipping_address.postal_code} {pedido.shipping_address.city}
              {pedido.shipping_address.province && `, ${pedido.shipping_address.province}`}
            </p>
            {pedido.shipping_address.phone && <p>Tel: {pedido.shipping_address.phone}</p>}
          </address>
        </section>
      )}

      <section aria-labelledby="estado" className="space-y-3 border-t border-piedra/20 pt-4">
        <h2 id="estado" className="text-seccion text-verde">
          Cambiar el estado
        </h2>

        {cambiar.isError && <Aviso tono="error">{cambiar.error.message}</Aviso>}

        {siguientes.length === 0 ? (
          <p className="text-base text-piedra">
            Este pedido ya no se mueve de aqui.
          </p>
        ) : (
          <div className="flex flex-wrap gap-2">
            {siguientes.map((destino) => (
              <Boton
                key={destino}
                type="button"
                variante={destino === 'cancelled' ? 'secundario' : 'principal'}
                onClick={() => cambiar.mutate(destino)}
                disabled={cambiar.isPending}
              >
                {nombreDelEstado(destino)}
              </Boton>
            ))}
          </div>
        )}

        {/* La maquina de estados la valida el backend con OrderStatus: esto
            solo evita ofrecer botones que devolverian 422. */}
      </section>
    </div>
  )
}

function Linea({ linea }: { linea: OrderItem }) {
  return (
    <article className="flex flex-wrap gap-4 rounded-fefu bg-rosa-suave p-4">
      {/* N9 — la foto de partida es lo que la artista necesita para dibujar. */}
      {linea.reference_media?.url ? (
        <a href={linea.reference_media.url} target="_blank" rel="noreferrer noopener">
          <img
            src={linea.reference_media.url}
            alt={`Foto de referencia de ${linea.product_name}`}
            className="h-32 w-32 rounded-fefu object-cover"
          />
        </a>
      ) : (
        <div className="flex h-32 w-32 items-center justify-center rounded-fefu bg-white/60 text-center text-sm text-piedra">
          Sin foto
        </div>
      )}

      <div className="min-w-48 flex-1 space-y-1">
        <h3 className="text-apartado text-verde">{linea.product_name}</h3>

        <p className="text-sm text-piedra">
          {linea.variant_name} ·{' '}
          {linea.delivery_type === 'digital' ? 'Descarga digital' : 'Envio a domicilio'}
          {linea.quantity > 1 && ` · ${linea.quantity} copias`}
        </p>

        {linea.customer_notes && (
          <p className="text-base italic text-piedra">«{linea.customer_notes}»</p>
        )}
      </div>

      <p className="text-apartado text-verde">{euros(linea.line_total)}</p>
    </article>
  )
}
