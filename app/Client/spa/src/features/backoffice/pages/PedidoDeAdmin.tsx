import { Link, useParams } from 'react-router'

import type { OrderItem } from '@/features/cart/api'
import { nombreDelEstado } from '@/features/orders/api'
import { urlDeDescarga, useSubirEntrega } from '@/features/orders/entregas'
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
              <Linea pedidoId={Number(id)} linea={linea} />
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
          <span>Envío</span>
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
            Envío
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
            Este pedido ya no se mueve de aquí.
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

function Linea({ pedidoId, linea }: { pedidoId: number; linea: OrderItem }) {
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
          {linea.delivery_type === 'digital' ? 'Descarga digital' : 'Envío a domicilio'}
          {linea.quantity > 1 && ` · ${linea.quantity} copias`}
        </p>

        {linea.customer_notes && (
          <p className="text-base italic text-piedra">«{linea.customer_notes}»</p>
        )}

        {/* N11 — solo las lineas digitales se entregan por descarga. */}
        {linea.delivery_type === 'digital' && <SubirEntrega pedidoId={pedidoId} linea={linea} />}
      </div>

      <p className="text-apartado text-verde">{euros(linea.line_total)}</p>
    </article>
  )
}

/**
 * D20, N11 — la artista sube la obra terminada de una linea digital.
 *
 * El fichero se guarda **tal cual**: es el que el cliente va a imprimir, y
 * recomprimirlo lo estropearia. Lo que el servidor comprueba es que sea de
 * verdad un JPEG, un PNG o un PDF, mirando su contenido.
 */
function SubirEntrega({ pedidoId, linea }: { pedidoId: number; linea: OrderItem }) {
  const subir = useSubirEntrega(pedidoId, linea.id)
  const idCampo = `entrega-${linea.id}`

  return (
    <div className="mt-3 space-y-1 rounded-fefu bg-rosa-suave/60 p-3">
      <label htmlFor={idCampo} className="block text-sm font-bold text-verde">
        {linea.delivered ? 'Sustituir la entrega' : 'Subir la entrega'}
      </label>

      {linea.delivered && (
        <p className="text-sm text-piedra">
          Ya entregada.{' '}
          <a
            className="underline underline-offset-4"
            href={urlDeDescarga(pedidoId, linea.id)}
          >
            Descargar para comprobarla
          </a>
        </p>
      )}

      <input
        id={idCampo}
        type="file"
        accept="image/jpeg,image/png,application/pdf"
        disabled={subir.isPending}
        onChange={(e) => {
          const archivo = e.target.files?.[0]

          if (archivo) {
            subir.mutate(archivo)
          }

          // Se limpia para poder volver a elegir el mismo fichero si la
          // subida fallo: sin esto, `change` no se dispara la segunda vez.
          e.target.value = ''
        }}
        className="block w-full text-sm text-piedra"
      />

      <p className="text-sm text-piedra">JPG, PNG o PDF, hasta 40 MB.</p>

      {subir.isPending && <p className="text-sm text-piedra">Subiendo...</p>}
      {subir.isError && <Aviso tono="error">{subir.error.message}</Aviso>}
    </div>
  )
}
