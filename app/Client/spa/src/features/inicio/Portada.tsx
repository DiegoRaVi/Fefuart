import { Link } from 'react-router'

import { useCatalogo } from '@/features/catalog/api'
import { useGaleria } from '@/features/galeria/api'

/**
 * N1 — los cuatro servicios del negocio, cada uno enlazado a donde se
 * contrata.
 *
 * Eran `<li>` sin enlace y la pantalla de entrada no llevaba a ninguna parte:
 * lo destapo la auditoria de UX del 2026-08-20. `slug` enlaza con el producto
 * del catalogo, que es de donde sale su foto.
 */
const SERVICIOS = [
  {
    nombre: 'Live Art en tu boda',
    descripcion:
      'Dibujo durante el evento y los invitados se llevan su retrato puesto. Cada boda es distinta, así que el precio se hace a medida.',
    reclamo: 'Presupuesto a medida',
    destino: '/live-art',
    slug: null,
  },
  {
    nombre: 'Dibujo por encargo',
    descripcion: 'Retratos dibujados a mano a partir de tu fotografía, en tres estilos.',
    reclamo: 'Ver estilos y precios',
    destino: '/encargos/dibujo-por-encargo',
    slug: 'dibujo-por-encargo',
  },
  {
    nombre: 'Tu ramo, en lámina',
    descripcion: 'El ramo se marchita. Dibujado a partir de tu foto, no.',
    reclamo: 'Ver tamaños y precios',
    destino: '/encargos/ramos-dibujados',
    slug: 'ramos-dibujados',
  },
  {
    nombre: 'Letras infantiles',
    descripcion: 'La inicial de los peques, ilustrada para su habitación.',
    reclamo: 'Ver tamaños y precios',
    destino: '/encargos/letras-infantiles',
    slug: 'letras-infantiles',
  },
]

/**
 * Los cuatro pasos de un encargo de Live Art.
 *
 * Van numerados porque **son una secuencia de verdad**: es el recorrido que
 * implementan `QuoteService` y `CheckoutService`, en ese orden y sin saltarse
 * ninguno. Numerar una lista que no lo fuera seria decorar.
 */
const PASOS = [
  {
    titulo: 'Me cuentas tu evento.',
    detalle: 'Fecha, sitio, cuántos sois y cuántas horas. Miro si tengo el día libre.',
  },
  {
    titulo: 'Te paso un presupuesto.',
    detalle: 'Cerrado, sin sorpresas, con lo que incluye y lo que no.',
  },
  {
    titulo: 'Reservas con una señal.',
    detalle: 'Ese día queda bloqueado solo para ti.',
  },
  {
    titulo: 'Dibujo en tu boda.',
    detalle: 'Monto mi mesa y trabajo durante el evento.',
  },
]

/**
 * La pantalla de entrada.
 *
 * **Ensena obra antes que texto**, y esa es toda la diferencia con la version
 * anterior: quien llega aqui viene de Instagram, de ver dibujos, y aterrizaba
 * en una pagina sin una sola imagen. Las fotos salen del catalogo y de la
 * galeria, asi que cambian cuando ella las cambia — sin tocar codigo.
 *
 * Si todavia no hay ninguna, la pagina sigue funcionando: se cae a texto, que
 * es lo que habia antes, en vez de dejar huecos rotos.
 *
 * **No hay testimonios.** Los llevaba la propuesta de diseno y se han dejado
 * fuera a proposito: no tenemos ninguno real, y escribirlos yo seria
 * inventarme clientas que no han dicho eso.
 */
export function Portada() {
  const { data: piezas } = useGaleria()
  const { data: productos } = useCatalogo()

  /*
   * La foto de cada tarjeta sale del producto al que enlaza, no de la
   * galeria: si se tomara de la galeria, una categoria sin piezas dejaria esa
   * tarjeta sin imagen al lado de otras que si la tienen. Live Art no es un
   * producto de catalogo (N13), asi que esa si viene de la galeria.
   */
  const enDirecto = piezas?.filter((pieza) => pieza.category === 'live-art') ?? []

  const fotoDe = (slug: string | null) =>
    slug === null
      ? enDirecto[0]?.thumbnail?.url
      : productos?.find((producto) => producto.slug === slug)?.image?.url

  const principal = enDirecto[0] ?? piezas?.[0]

  return (
    /*
     * Esta ruta va fuera de `Centrado` (ver `App.tsx`), asi que aqui no hay
     * limite de ancho: las bandas de color llegan de borde a borde y es cada
     * seccion la que vuelve a centrar su contenido en `max-w-6xl`.
     */
    <div>
      <Entradilla foto={principal?.image?.url} pie={principal?.title} />

      <section aria-labelledby="servicios" className="bg-rosa-suave px-6 py-16">
        <div className="mx-auto max-w-6xl space-y-8">
          <div className="flex flex-wrap items-baseline justify-between gap-4">
            <h2 id="servicios" className="text-seccion">
              Lo que hago
            </h2>

            <Link className="text-verde underline underline-offset-4" to="/galeria">
              Ver la galería completa
            </Link>
          </div>

          <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {SERVICIOS.map((servicio) => (
              <li key={servicio.nombre}>
                <Servicio servicio={servicio} foto={fotoDe(servicio.slug)} />
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* Una pieza distinta a la de la entradilla cuando la hay: repetir la
          misma foto a media pantalla de distancia se nota. */}
      <ComoFunciona foto={(enDirecto[1] ?? enDirecto[0])?.image?.url} />

      <section aria-labelledby="cierre" className="bg-rosa-suave px-6 py-20 text-center">
        <div className="mx-auto flex max-w-xl flex-col items-center gap-5">
          <h2 id="cierre" className="text-titulo">
            ¿Te dibujo la tuya?
          </h2>

          <p className="text-lg">
            Cuéntame la fecha y el sitio. Te digo en un día si la tengo libre y
            cuánto costaría.
          </p>

          <Link
            to="/live-art"
            className="rounded-fefu bg-piedra px-9 py-4 text-white transition-colors duration-300 hover:bg-verde"
          >
            Pedir presupuesto
          </Link>
        </div>
      </section>
    </div>
  )
}

/**
 * La entradilla: obra a un lado, propuesta al otro.
 *
 * En movil la foto va **primera** —es lo que ha venido a ver— y en escritorio
 * pasa a la derecha, que es donde cae la vista despues de leer el titular. De
 * ahi el `order` cruzado.
 */
function Entradilla({ foto, pie }: { foto?: string; pie?: string }) {
  return (
    /* Sin foto no hay dos columnas: dejar media pantalla en blanco es peor
       que una entradilla a un ancho. Pasa en cuanto la galeria esta vacia. */
    <section
      className={`mx-auto grid max-w-6xl items-center gap-10 px-6 py-14 ${
        foto ? 'lg:grid-cols-2 lg:gap-14' : ''
      }`}
    >
      {foto && (
        <img
          src={foto}
          alt={pie ?? ''}
          /* La primera imagen de la pagina no se difiere: es lo que el
             visitante ha venido a ver. */
          className="aspect-[4/5] w-full rounded-fefu object-cover lg:order-2"
        />
      )}

      <div className="flex flex-col gap-6 lg:order-1">
        <p className="antetitulo text-verde">Live Art · Valencia</p>

        <h1 className="titular-portada text-verde">Dibujo tu boda mientras ocurre</h1>

        <p className="max-w-[34ch] text-lg">
          Pinto en acuarela durante el evento y cada invitado se lleva su
          retrato con el look que eligió esa noche. Sin poses, sin esperas: el
          dibujo nace delante de ellos.
        </p>

        <div className="flex flex-wrap gap-3 pt-1">
          <Link
            to="/live-art"
            className="rounded-fefu bg-piedra px-8 py-4 text-white transition-colors duration-300 hover:bg-verde"
          >
            Pedir presupuesto
          </Link>

          <Link
            to="/galeria"
            className="rounded-fefu border border-piedra px-8 py-4 text-piedra transition-colors duration-300 hover:bg-rosa-suave"
          >
            Ver mi trabajo
          </Link>
        </div>
      </div>
    </section>
  )
}

function Servicio({
  servicio,
  foto,
}: {
  servicio: (typeof SERVICIOS)[number]
  foto?: string
}) {
  return (
    /* Toda la tarjeta es el enlace, no solo el titulo: es un objetivo grande
       y facil de acertar con el pulgar. */
    <Link
      to={servicio.destino}
      className="group flex h-full flex-col overflow-hidden rounded-fefu bg-white text-piedra transition-shadow duration-300 hover:shadow-[0_2px_16px_rgba(0,0,0,0.12)]"
    >
      {foto && (
        <img
          src={foto}
          alt=""
          loading="lazy"
          className="aspect-[4/3] w-full object-cover transition-transform duration-300 group-hover:scale-105"
        />
      )}

      <div className="flex flex-1 flex-col gap-2 p-6">
        <h3 className="text-apartado text-verde">{servicio.nombre}</h3>

        <p className="text-base">{servicio.descripcion}</p>

        {/* `mt-auto` empuja el reclamo al fondo para que quede a la misma
            altura en las cuatro tarjetas aunque la descripcion sea mas larga
            en unas que en otras. */}
        <p className="mt-auto pt-3 text-base text-verde">
          {servicio.reclamo}{' '}
          <span
            aria-hidden="true"
            className="inline-block transition-transform duration-300 group-hover:translate-x-1"
          >
            &rsaquo;
          </span>
        </p>
      </div>
    </Link>
  )
}

function ComoFunciona({ foto }: { foto?: string }) {
  return (
    <section
      aria-labelledby="como-funciona"
      className={`mx-auto grid max-w-6xl items-center gap-10 px-6 py-16 ${
        foto ? 'lg:grid-cols-2 lg:gap-14' : ''
      }`}
    >
      {foto && (
        <img
          src={foto}
          alt=""
          loading="lazy"
          className="aspect-[4/3] w-full rounded-fefu object-cover"
        />
      )}

      <div className="space-y-7">
        <h2 id="como-funciona" className="text-titulo">
          Cómo funciona una boda con Live Art
        </h2>

        <ol className="space-y-5">
          {PASOS.map((paso, indice) => (
            <li key={paso.titulo} className="flex gap-4">
              {/* El numero es decorativo: el `<ol>` ya dice el orden a un
                  lector de pantalla, y leerlo dos veces solo estorba. */}
              <span
                aria-hidden="true"
                className="font-display text-seccion leading-none text-rosa-hondo"
              >
                {indice + 1}
              </span>

              <p className="text-base">
                <strong className="font-medium text-verde">{paso.titulo}</strong>{' '}
                {paso.detalle}
              </p>
            </li>
          ))}
        </ol>
      </div>
    </section>
  )
}
