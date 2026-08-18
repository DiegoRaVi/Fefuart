import { Link, useParams } from 'react-router'

import type { OrderItem, ShippingAddress } from '@/features/cart/api'
import { usePagarPedido } from '@/features/pagos/api'
import { ApiError } from '@/shared/api/errors'
import { euros } from '@/shared/lib/dinero'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Cargando } from '@/shared/ui/Cargando'

import { nombreDelEstado, useCancelarPedido, usePedido } from '../api'

/** N12 — el cliente cancela solo antes de pagar. */
const CANCELABLES = ['cart', 'pending_payment']

export function DetalleDePedido() {
  const { id = '' } = useParams()
  const { data: pedido, isPending, isError, error } = usePedido(id)
  const cancelar = useCancelarPedido(id)
  const pagar = usePagarPedido(id)

  if (isPending) {
    return <Cargando texto="Cargando el pedido..." />
  }

  if (isError) {
    /**
     * SEC-003/SEC-008 — el pedido de otro responde 403, no 404. La
     * distincion la hace el backend por Policy; aqui solo se traduce.
     */
    const ajeno = error instanceof ApiError && error.isForbidden

    return (
      <div className="mx-auto max-w-2xl space-y-4">
        <Aviso tono="error">
          {ajeno ? 'Este pedido no es tuyo.' : error.message}
        </Aviso>
        <Link className="text-verde underline underline-offset-4" to="/pedidos">
          Volver a mis pedidos
        </Link>
      </div>
    )
  }

  const sePuedeCancelar = CANCELABLES.includes(pedido.status)

  return (
    <div className="mx-auto max-w-3xl space-y-8">
      <div className="space-y-2">
        <Link className="text-base text-piedra underline underline-offset-4" to="/pedidos">
          Volver a mis pedidos
        </Link>

        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <h1 className="text-titulo text-verde">Pedido #{pedido.id}</h1>
          <p className="rounded-fefu bg-rosa px-3 py-1 text-verde">
            {nombreDelEstado(pedido.status)}
          </p>
        </div>

        {pedido.placed_at && (
          <p className="text-base text-piedra">
            Hecho el{' '}
            {new Date(pedido.placed_at).toLocaleDateString('es-ES', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
            })}
          </p>
        )}
      </div>

      <section aria-labelledby="encargos" className="space-y-3">
        <h2 id="encargos" className="text-seccion text-verde">
          Encargos
        </h2>

        <ul className="space-y-3">
          {pedido.items.map((linea) => (
            <li key={linea.id}>
              <Linea linea={linea} />
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="importes" className="space-y-2 border-t border-piedra/20 pt-4">
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
        <p className="text-sm text-piedra">IVA incluido.</p>
      </section>

      {pedido.shipping_address.line1 && (
        <Direccion direccion={pedido.shipping_address} />
      )}

      {pedido.status === 'pending_payment' && (
        <section aria-labelledby="pago" className="space-y-2 border-t border-piedra/20 pt-4">
          <h2 id="pago" className="text-seccion text-verde">
            Pagar
          </h2>

          {pagar.isError && <Aviso tono="error">{pagar.error.message}</Aviso>}

          <Boton type="button" onClick={() => pagar.mutate()} disabled={pagar.isPending}>
            {pagar.isPending ? 'Abriendo la pasarela...' : `Pagar ${euros(pedido.total)}`}
          </Boton>

          {/* D7 — Checkout hospedado: el formulario de tarjeta lo sirve
              Stripe en su dominio y ningun dato de pago pasa por el nuestro. */}
          <p className="text-sm text-piedra">
            El pago lo gestiona Stripe en su propia pagina. Aqui no se guarda ningun dato
            de tu tarjeta.
          </p>
        </section>
      )}

      {sePuedeCancelar && (
        <section className="space-y-2 border-t border-piedra/20 pt-4">
          {cancelar.isError && <Aviso tono="error">{cancelar.error.message}</Aviso>}

          <Boton
            type="button"
            variante="secundario"
            onClick={() => cancelar.mutate()}
            disabled={cancelar.isPending}
          >
            {cancelar.isPending ? 'Cancelando...' : 'Cancelar el pedido'}
          </Boton>

          {/* N12 — despues de pagar, la cancelacion se acuerda con la artista
              y la aplica ella desde el backoffice. */}
          <p className="text-sm text-piedra">
            Una vez pagado, escribenos y lo vemos.
          </p>
        </section>
      )}
    </div>
  )
}

function Linea({ linea }: { linea: OrderItem }) {
  return (
    <article className="flex flex-wrap gap-4 rounded-fefu bg-rosa-suave p-4">
      {linea.reference_media?.url && (
        <img
          src={linea.reference_media.url}
          alt={`Foto de referencia de ${linea.product_name}`}
          className="h-24 w-24 rounded-fefu object-cover"
        />
      )}

      <div className="min-w-48 flex-1 space-y-1">
        {/* El snapshot: lo que se compro, no lo que el catalogo valga hoy. */}
        <h3 className="text-apartado text-verde">{linea.product_name}</h3>

        <p className="text-sm text-piedra">
          {linea.variant_name} ·{' '}
          {linea.delivery_type === 'digital' ? 'Descarga digital' : 'Envio a domicilio'}
          {linea.quantity > 1 && ` · ${linea.quantity} copias`}
        </p>

        {linea.customer_notes && (
          <p className="text-base text-piedra italic">«{linea.customer_notes}»</p>
        )}
      </div>

      <p className="text-apartado text-verde">{euros(linea.line_total)}</p>
    </article>
  )
}

function Direccion({ direccion }: { direccion: ShippingAddress }) {
  return (
    <section aria-labelledby="envio" className="space-y-1">
      <h2 id="envio" className="text-seccion text-verde">
        Envio
      </h2>

      <address className="text-base not-italic text-piedra">
        {direccion.name && <p>{direccion.name}</p>}
        <p>{direccion.line1}</p>
        {direccion.line2 && <p>{direccion.line2}</p>}
        <p>
          {direccion.postal_code} {direccion.city}
          {direccion.province && `, ${direccion.province}`}
        </p>
        {direccion.phone && <p>Tel: {direccion.phone}</p>}
      </address>
    </section>
  )
}
