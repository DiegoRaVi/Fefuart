import { Link, useParams } from 'react-router'

import { ApiError } from '@/shared/api/errors'
import type { Product, ProductVariant } from '@/shared/api/types'
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
          {noExiste ? 'Ese encargo no existe o ya no está disponible.' : error.message}
        </Aviso>

        <Link className="text-verde underline underline-offset-4" to="/encargos">
          Volver al catálogo
        </Link>
      </div>
    )
  }

  return (
    <article className="mx-auto max-w-6xl space-y-8">
      <nav aria-label="Migas de pan" className="text-base text-piedra">
        <Link className="underline underline-offset-4" to="/encargos">
          Encargos
        </Link>{' '}
        <span aria-hidden="true">&rsaquo;</span>{' '}
        <span className="text-verde">{producto.name}</span>
      </nav>

      {/*
        La obra a un lado y la decision al otro. En movil se apilan y la foto
        queda arriba, que es el orden natural: primero se ve, luego se elige.
      */}
      <div
        className={`grid gap-10 ${
          producto.image?.url ? 'lg:grid-cols-2 lg:gap-14' : ''
        }`}
      >
        {producto.image?.url && (
          <img
            src={producto.image.url}
            alt={producto.name}
            className="aspect-[4/5] w-full rounded-fefu object-cover"
          />
        )}

        <div className="space-y-8">
          <div className="space-y-4">
            <h1 className="text-titulo">{producto.name}</h1>

            {producto.description && <p className="text-lg">{producto.description}</p>}
          </div>

          <Estilos producto={producto} />

          {/* N18 — encargar exige cuenta. Si no la hay, RutaProtegida manda al
              login y devuelve aqui despues. */}
          <Link
            to={`/encargos/${producto.slug}/encargar`}
            className="inline-block rounded-fefu bg-piedra px-9 py-4 text-white transition-colors duration-300 hover:bg-verde"
          >
            Empezar mi encargo
          </Link>

          <QuePasaDespues producto={producto} />
        </div>
      </div>
    </article>
  )
}

/**
 * Los estilos del producto.
 *
 * **Con foto se reparten en rejilla y sin foto se apilan**, y no es un
 * capricho: la foto es justo lo que separa «Acuarela» de «Diseño de moda»
 * —dos dibujos que no se parecen en nada—, asi que cuando existe manda ella
 * y las tarjetas se ponen una al lado de otra para poder compararlas de un
 * vistazo. Cuando no existe, una rejilla de cajas de texto solo seria aire:
 * la lista apilada se lee mejor.
 *
 * `auto-fit` en vez de un numero de columnas fijo porque hay productos de un
 * solo estilo —el ramo, las letras— y una rejilla de tres con uno dentro deja
 * dos huecos.
 */
function Estilos({ producto }: { producto: Product }) {
  const conFoto = producto.variants.some((variante) => variante.image?.url)
  const varios = producto.variants.length > 1

  return (
    <section aria-labelledby="opciones" className="space-y-3">
      <h2 id="opciones" className="antetitulo text-verde">
        {varios ? 'Elige el estilo' : 'El encargo'}
      </h2>

      {/*
        Las opciones se enseñan, no se eligen aqui: la eleccion se hace en
        `/encargar`, que es donde ademas se sube la foto y se calcula el
        total. Pintarlas como si fueran seleccionables cuando no lo son solo
        prometeria un gesto que esta pantalla no cumple.
      */}
      <ul
        className={
          conFoto
            ? 'grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(180px,1fr))]'
            : 'space-y-3'
        }
      >
        {producto.variants.map((variante) => (
          <li
            key={variante.id}
            className="overflow-hidden rounded-fefu border border-rosa bg-rosa-suave"
          >
            <Variante variante={variante} enRejilla={conFoto} />
          </li>
        ))}
      </ul>

      <p className="text-sm text-piedra">
        IVA incluido. El envío se cobra una vez por pedido, no por lámina.
      </p>
    </section>
  )
}

function Variante({
  variante,
  enRejilla,
}: {
  variante: ProductVariant
  enRejilla: boolean
}) {
  /* N7 — que entregas admite esta variante en concreto. Solo el estilo
     digital admite descarga, y eso el cliente tiene que verlo antes de
     elegir porque el servidor lo va a rechazar. */
  const entregas = variante.shipping_methods
    .map((metodo) =>
      Number(metodo.price) === 0 ? metodo.name : `${metodo.name} (${euros(metodo.price)})`,
    )
    .join(' · ')

  const precio = precioConCopias(variante.price, variante.additional_copy_price)

  if (!enRejilla) {
    return (
      <div className="flex flex-wrap items-baseline justify-between gap-2 p-4">
        <div>
          <h3 className="text-apartado text-verde">{variante.name}</h3>
          <p className="text-sm text-piedra">{entregas}</p>
        </div>

        <p className="text-base text-piedra">{precio}</p>
      </div>
    )
  }

  return (
    <div className="flex h-full flex-col">
      {variante.image?.url && (
        <img
          src={variante.image.url}
          alt={variante.name}
          loading="lazy"
          className="aspect-[4/3] w-full object-cover"
        />
      )}

      <div className="flex flex-1 flex-col gap-1 p-4">
        <h3 className="text-apartado text-verde">{variante.name}</h3>
        <p className="text-base text-piedra">{precio}</p>
        {/* Al fondo para que las tarjetas cuadren aunque una admita descarga
            y las otras no. */}
        <p className="mt-auto pt-1 text-sm text-piedra">{entregas}</p>
      </div>
    </div>
  )
}

/**
 * Lo que pasa despues de pulsar, contado antes de pulsar.
 *
 * Era una lista de puntos bajo el titulo «Como funciona» y se queda en el
 * mismo sitio de la pantalla que ocupa la decision, porque es justo lo que
 * hace falta saber para tomarla: cuanto tarda, que hay que subir y cuantas
 * copias caben. Todo sale del producto, asi que no hay ningun plazo escrito
 * a mano que pueda quedarse viejo.
 */
function QuePasaDespues({ producto }: { producto: Product }) {
  return (
    <section
      aria-labelledby="que-pasa"
      className="space-y-2 rounded-fefu bg-rosa-suave p-5"
    >
      <h2 id="que-pasa" className="antetitulo text-verde">
        Qué pasa después
      </h2>

      <ul className="space-y-1 text-base">
        {/* N9 — la foto no es un adjunto opcional: es el material de partida. */}
        {producto.requires_reference_image && (
          <li>Se dibuja a partir de una foto que subes tú.</li>
        )}
        {producto.requires_notes && (
          <li>Puedes contarnos cómo lo quieres al hacer el encargo.</li>
        )}
        <li>Tarda unos {producto.delivery_days} días en estar listo.</li>
        {/* N3 — la cantidad son copias de la misma lamina. */}
        <li>
          Si quieres varias copias de la misma lámina, puedes pedir hasta{' '}
          {producto.max_quantity}.
        </li>
      </ul>
    </section>
  )
}
