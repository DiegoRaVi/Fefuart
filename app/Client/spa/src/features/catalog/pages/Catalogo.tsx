import { Link } from 'react-router'

import { Aviso } from '@/shared/ui/Aviso'
import { Cargando } from '@/shared/ui/Cargando'

import { useCatalogo } from '../api'
import { PrecioDesde } from '../components/PrecioDesde'

/**
 * En v1 no habia catalogo que listar: `galeria.html` era HTML estatico y
 * ninguna tabla decia que se podia encargar ni a que precio (DB-002).
 */
export function Catalogo() {
  const { data: productos, isPending, isError, error } = useCatalogo()

  if (isPending) {
    return <Cargando texto="Cargando el catalogo..." />
  }

  if (isError) {
    return <Aviso tono="error">{error.message}</Aviso>
  }

  return (
    <div className="space-y-8">
      <header className="space-y-2">
        <h1 className="text-titulo text-verde">Encargos</h1>
        <p className="max-w-2xl">
          Todos se dibujan a mano. Los precios llevan el IVA incluido.
        </p>
      </header>

      {productos.length === 0 ? (
        <Aviso tono="informacion">
          Ahora mismo no hay nada en el catalogo. Vuelve dentro de un rato.
        </Aviso>
      ) : (
        <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {productos.map((producto) => (
            <li key={producto.id}>
              <Link
                to={`/encargos/${producto.slug}`}
                className="group flex h-full flex-col overflow-hidden rounded-fefu bg-rosa-suave transition-shadow duration-300 hover:shadow-[0_2px_8px_rgba(0,0,0,0.15)]"
              >
                {/*
                  El producto antes que el precio. La tienda vendia dibujos
                  sin enseñar ninguno, asi que se juzgaba el coste sin nada
                  con lo que compararlo (auditoria de UX, 2026-08-20).
                */}
                {producto.image?.url && (
                  <img
                    src={producto.image.url}
                    alt=""
                    loading="lazy"
                    className="aspect-[4/3] w-full object-cover transition-transform duration-300 group-hover:scale-105"
                  />
                )}

                <div className="flex flex-1 flex-col gap-3 p-6">
                <h2 className="text-apartado text-verde">{producto.name}</h2>

                {producto.description && (
                  <p className="flex-1 text-base text-piedra">{producto.description}</p>
                )}

                <PrecioDesde variantes={producto.variants} />

                <p className="text-sm text-piedra">
                  Listo en unos {producto.delivery_days} dias
                  {producto.requires_reference_image && ' · a partir de tu foto'}
                </p>
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
