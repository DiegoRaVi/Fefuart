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
    nombre: 'Live Art',
    descripcion:
      'Dibujo en directo durante tu evento, y los invitados se llevan su retrato. Cada boda es distinta, asi que el precio se hace a medida.',
    destino: '/live-art',
    slug: null,
  },
  {
    nombre: 'Dibujo por encargo',
    descripcion: 'Retratos dibujados a mano a partir de tu fotografía, en tres estilos.',
    destino: '/encargos/dibujo-por-encargo',
    slug: 'dibujo-por-encargo',
  },
  {
    nombre: 'Letras infantiles',
    descripcion: 'Letras ilustradas para la habitación de los peques.',
    destino: '/encargos/letras-infantiles',
    slug: 'letras-infantiles',
  },
  {
    nombre: 'Ramos dibujados',
    descripcion: 'Tu ramo de novia convertido en lámina para siempre.',
    destino: '/encargos/ramos-dibujados',
    slug: 'ramos-dibujados',
  },
]

/**
 * La pantalla de entrada.
 *
 * **Enseña obra antes que texto**, y esa es toda la diferencia con la version
 * anterior: quien llega aqui viene de Instagram, de ver dibujos, y aterrizaba
 * en una pagina sin una sola imagen. Las fotos salen del catalogo y de la
 * galeria, asi que cambian cuando ella las cambia — sin tocar codigo.
 *
 * Si todavia no hay ninguna, la pagina sigue funcionando: se cae a texto, que
 * es lo que habia antes, en vez de dejar huecos rotos.
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
  const fotoDe = (slug: string | null) =>
    slug === null
      ? piezas?.find((pieza) => pieza.category === 'live-art')?.thumbnail?.url
      : productos?.find((producto) => producto.slug === slug)?.image?.url

  const principal = piezas?.find((pieza) => pieza.category === 'live-art') ?? piezas?.[0]

  return (
    <div className="space-y-16">
      <section className="space-y-6">
        {principal?.image?.url && (
          <img
            src={principal.image.url}
            alt={principal.title}
            /* La primera imagen de la pagina no se difiere: es lo que el
               visitante ha venido a ver. */
            className="aspect-[16/9] w-full rounded-fefu object-cover"
          />
        )}

        <div className="space-y-4">
          <h1 className="text-titulo text-verde">
            Dibujo tu boda mientras ocurre
          </h1>

          <p className="max-w-2xl">
            Me llamo Feli y pinto en directo en bodas y eventos: los invitados
            ven como nace su retrato y se lo llevan puesto. También dibujo por
            encargo a partir de tus fotografías.
          </p>

          <div className="flex flex-wrap gap-3 pt-2">
            <Link
              to="/galeria"
              className="rounded-fefu bg-piedra px-6 py-3 text-white transition-colors duration-300 hover:bg-verde"
            >
              Ver mi trabajo
            </Link>

            <Link
              to="/live-art"
              className="rounded-fefu border border-piedra px-6 py-3 text-piedra transition-colors duration-300 hover:bg-rosa-suave"
            >
              Pedir presupuesto
            </Link>
          </div>
        </div>
      </section>

      <section aria-labelledby="servicios" className="space-y-6">
        <h2 id="servicios" className="text-seccion text-verde">
          Que hacemos
        </h2>

        <ul className="grid gap-6 sm:grid-cols-2">
          {SERVICIOS.map((servicio) => {
            const foto = fotoDe(servicio.slug)

            return (
              <li key={servicio.nombre}>
                {/* Toda la tarjeta es el enlace, no solo el titulo: es un
                    objetivo grande y facil de acertar con el pulgar. */}
                <Link
                  to={servicio.destino}
                  className="group block h-full overflow-hidden rounded-fefu bg-rosa-suave text-piedra transition-shadow duration-300 hover:shadow-[0_2px_12px_rgba(0,0,0,0.12)]"
                >
                  {foto && (
                    <img
                      src={foto}
                      alt=""
                      loading="lazy"
                      className="aspect-[4/3] w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                  )}

                  <div className="p-6">
                    <h3 className="text-apartado text-verde">{servicio.nombre}</h3>
                    <p className="mt-2 text-base">{servicio.descripcion}</p>
                  </div>
                </Link>
              </li>
            )
          })}
        </ul>
      </section>
    </div>
  )
}
