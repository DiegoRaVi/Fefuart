import { Link } from 'react-router'

import { euros } from '@/shared/lib/dinero'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Cargando } from '@/shared/ui/Cargando'

import { useCambiarCantidad, useCarrito, useQuitarLinea, type OrderItem } from '../api'

export function Carrito() {
  const { data: carrito, isPending, isError, error } = useCarrito()

  if (isPending) {
    return <Cargando texto="Cargando el carrito..." />
  }

  if (isError) {
    return <Aviso tono="error">{error.message}</Aviso>
  }

  if (carrito.items.length === 0) {
    return (
      <div className="mx-auto max-w-2xl space-y-4">
        <h1 className="text-titulo text-verde">Tu carrito</h1>
        <Aviso tono="informacion">Todavia no has anadido ningun encargo.</Aviso>
        <Link className="text-verde underline underline-offset-4" to="/encargos">
          Ver los encargos
        </Link>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-3xl space-y-8">
      <h1 className="text-titulo text-verde">Tu carrito</h1>

      <ul className="space-y-4">
        {carrito.items.map((linea) => (
          <li key={linea.id}>
            <Linea linea={linea} />
          </li>
        ))}
      </ul>

      <Totales
        subtotal={carrito.subtotal}
        envio={carrito.shipping_total}
        total={carrito.total}
        metodo={carrito.shipping_method?.name}
      />

      {/* El checkout llega en la tanda siguiente. */}
    </div>
  )
}

function Linea({ linea }: { linea: OrderItem }) {
  const cambiar = useCambiarCantidad()
  const quitar = useQuitarLinea()

  const esDigital = linea.delivery_type === 'digital'

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
        <h2 className="text-apartado text-verde">{linea.product_name}</h2>

        <p className="text-sm text-piedra">
          {linea.variant_name} · {esDigital ? 'Descarga digital' : 'Envio a domicilio'}
        </p>

        {linea.customer_notes && (
          <p className="text-base text-piedra italic">«{linea.customer_notes}»</p>
        )}

        <div className="flex items-center gap-3 pt-2">
          <label className="text-sm text-piedra">
            Copias{' '}
            <input
              type="number"
              min={1}
              value={linea.quantity}
              // N3/D26 — una entrega digital es un unico archivo.
              disabled={esDigital || cambiar.isPending}
              onChange={(e) => cambiar.mutate({ id: linea.id, quantity: Number(e.target.value) })}
              className="ml-1 w-20 rounded-fefu border border-piedra/40 px-2 py-1 disabled:bg-white/50"
              aria-label={`Copias de ${linea.product_name}`}
            />
          </label>

          <Boton
            type="button"
            variante="secundario"
            onClick={() => quitar.mutate(linea.id)}
            disabled={quitar.isPending}
          >
            {quitar.isPending ? 'Quitando...' : 'Quitar'}
          </Boton>
        </div>

        {cambiar.isError && <Aviso tono="error">{cambiar.error.message}</Aviso>}
      </div>

      <div className="text-right">
        {/* El importe lo calcula el servidor. Aqui solo se enseña. */}
        <p className="text-apartado text-verde">{euros(linea.line_total)}</p>

        {linea.quantity > 1 && (
          <p className="text-sm text-piedra">
            {euros(linea.unit_price)} + {euros(linea.additional_copy_price)} ×{' '}
            {linea.quantity - 1}
          </p>
        )}
      </div>
    </article>
  )
}

function Totales({
  subtotal,
  envio,
  total,
  metodo,
}: {
  subtotal: string
  envio: string
  total: string
  metodo?: string
}) {
  return (
    <section aria-labelledby="totales" className="space-y-2 border-t border-piedra/20 pt-4">
      <h2 id="totales" className="sr-only">
        Importes
      </h2>

      <div className="flex justify-between text-base">
        <span>Encargos</span>
        <span>{euros(subtotal)}</span>
      </div>

      {/* N5 — el envio se cobra una vez por pedido, no por linea. En v1 tres
          articulos costaban 15 EUR de envio en vez de 5. */}
      <div className="flex justify-between text-base">
        <span>Envio{metodo && ` · ${metodo}`}</span>
        <span>{euros(envio)}</span>
      </div>

      <div className="flex justify-between text-seccion text-verde">
        <span>Total</span>
        <span>{euros(total)}</span>
      </div>

      {/* N2 — el precio ya lleva el IVA. */}
      <p className="text-sm text-piedra">IVA incluido.</p>
    </section>
  )
}
