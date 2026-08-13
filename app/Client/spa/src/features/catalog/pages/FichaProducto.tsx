import { Link, useParams } from 'react-router'

import { ApiError } from '@/shared/api/errors'
import type { ProductVariant } from '@/shared/api/types'
import { euros, precioConCopias } from '@/shared/lib/dinero'
import { Aviso } from '@/shared/ui/Aviso'
import { Cargando } from '@/shared/ui/Cargando'

import { useProducto } from '../api'

export function FichaProducto() {
  const { slug = '' } = useParams()
  const { data: producto, isPending, isError, error } = useProducto(slug)

  if (isPending) {
    return <Cargando texto="Cargando el encargo..." />
  }

  if (isError) {
    const noExiste = error instanceof ApiError && error.status === 404

    return (
      <div className="space-y-4">
        <Aviso tono="error">
          {noExiste ? 'Ese encargo no existe o ya no esta disponible.' : error.message}
        </Aviso>

        <Link className="text-verde underline underline-offset-4" to="/encargos">
          Volver al catalogo
        </Link>
      </div>
    )
  }

  return (
    <article className="max-w-3xl space-y-8">
      <header className="space-y-3">
        <Link className="text-base text-piedra underline underline-offset-4" to="/encargos">
          Volver al catalogo
        </Link>

        <h1 className="text-titulo text-verde">{producto.name}</h1>

        {producto.description && <p>{producto.description}</p>}
      </header>

      <section aria-labelledby="opciones" className="space-y-4">
        <h2 id="opciones" className="text-seccion text-verde">
          Opciones
        </h2>

        <ul className="space-y-3">
          {producto.variants.map((variante) => (
            <li key={variante.id} className="rounded-fefu bg-rosa-suave p-4">
              <Variante variante={variante} />
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="como-funciona" className="space-y-3">
        <h2 id="como-funciona" className="text-seccion text-verde">
          Como funciona
        </h2>

        <ul className="list-inside list-disc space-y-1 text-base">
          {/* N9 — la foto no es un adjunto opcional: es el material de partida. */}
          {producto.requires_reference_image && (
            <li>Se dibuja a partir de una foto que subes tu.</li>
          )}
          {producto.requires_notes && (
            <li>Puedes contarnos como lo quieres al hacer el encargo.</li>
          )}
          <li>Tarda unos {producto.delivery_days} dias en estar listo.</li>
          {/* N3 — la cantidad son copias de la misma lamina. */}
          <li>
            Si quieres varias copias de la misma lamina, puedes pedir hasta{' '}
            {producto.max_quantity}.
          </li>
        </ul>

      </section>

      {/* N18 — encargar exige cuenta. Si no la hay, RutaProtegida manda al
          login y devuelve aqui despues. */}
      <Link
        to={`/encargos/${producto.slug}/encargar`}
        className="inline-block rounded-fefu bg-piedra px-6 py-3 text-white transition-colors duration-300 hover:bg-verde"
      >
        Encargar
      </Link>
    </article>
  )
}

function Variante({ variante }: { variante: ProductVariant }) {
  return (
    <div className="flex flex-wrap items-baseline justify-between gap-2">
      <div>
        <h3 className="text-apartado text-verde">{variante.name}</h3>

        {/* N7 — que entregas admite esta variante en concreto. Solo el estilo
            digital admite descarga, y eso el cliente tiene que verlo antes de
            elegir porque el servidor lo va a rechazar. */}
        <p className="text-sm text-piedra">
          {variante.shipping_methods
            .map((metodo) =>
              Number(metodo.price) === 0
                ? metodo.name
                : `${metodo.name} (${euros(metodo.price)})`,
            )
            .join(' · ')}
        </p>
      </div>

      <p className="text-base text-piedra">
        {precioConCopias(variante.price, variante.additional_copy_price)}
      </p>
    </div>
  )
}
