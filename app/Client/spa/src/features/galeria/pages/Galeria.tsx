import { useState } from 'react'

import { Aviso } from '@/shared/ui/Aviso'
import { Cargando } from '@/shared/ui/Cargando'
import { Modal } from '@/shared/ui/Modal'

import {
  CATEGORIAS,
  nombreDeCategoria,
  useGaleria,
  type Categoria,
  type PiezaDeGaleria,
} from '../api'

/**
 * D33 — el escaparate.
 *
 * Sin sesion: es lo que ve quien todavia no es cliente, y para un negocio que
 * vende dibujos es probablemente lo que mas encargos trae.
 *
 * La rejilla pinta **miniaturas** y el visor la imagen grande. Sin esa
 * separacion, entrar aqui descargaria treinta fotos a tamano completo antes
 * de que nadie pulse nada.
 */
export function Galeria() {
  const [categoria, setCategoria] = useState<Categoria | undefined>()
  const [abierta, setAbierta] = useState<PiezaDeGaleria | null>(null)

  const { data, isPending, isError, error } = useGaleria(categoria)

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="space-y-2">
        <h1 className="text-titulo text-verde">Galería</h1>
        <p className="text-base text-piedra">
          Algunos de los trabajos de Felicitas: dibujo en directo, retratos por
          encargo, letras, ramos y papeleria.
        </p>
      </div>

      <div className="flex flex-wrap gap-2" role="group" aria-label="Filtrar por categoria">
        <Filtro activa={categoria === undefined} onClick={() => setCategoria(undefined)}>
          Todo
        </Filtro>

        {CATEGORIAS.map((c) => (
          <Filtro
            key={c.valor}
            activa={categoria === c.valor}
            onClick={() => setCategoria(c.valor)}
          >
            {c.etiqueta}
          </Filtro>
        ))}
      </div>

      {isPending && <Cargando texto="Cargando la galeria..." />}
      {isError && <Aviso tono="error">{error.message}</Aviso>}

      {data && data.length === 0 && (
        <Aviso tono="informacion">
          Todavía no hay nada por aquí. Felicitas está preparando la galería.
        </Aviso>
      )}

      {data && data.length > 0 && (
        <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          {data.map((pieza) => (
            <li key={pieza.id}>
              <button
                type="button"
                onClick={() => setAbierta(pieza)}
                className="group block w-full overflow-hidden rounded-fefu bg-rosa-suave"
              >
                {pieza.thumbnail?.url && (
                  <img
                    src={pieza.thumbnail.url}
                    alt={pieza.title}
                    /* Diferida: la rejilla puede tener muchas piezas y solo
                       se ven las primeras sin bajar. */
                    loading="lazy"
                    className="aspect-square w-full object-cover transition-transform duration-300 group-hover:scale-105"
                  />
                )}

                <span className="block px-2 py-2 text-left text-sm text-piedra">
                  {pieza.title}
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}

      {/* El visor. `Modal` usa `<dialog>` nativo, asi que cierra con Escape y
          atrapa el foco sin que haya que escribirlo. */}
      <Modal
        titulo={abierta?.title ?? ''}
        abierto={abierta !== null}
        onCerrar={() => setAbierta(null)}
      >
        {abierta?.image?.url && (
          <img
            src={abierta.image.url}
            alt={abierta.title}
            className="max-h-[70vh] w-auto rounded-fefu"
          />
        )}

        <p className="mt-2 text-sm text-piedra">{nombreDeCategoria(abierta?.category ?? '')}</p>

        {abierta?.description && <p className="mt-1 text-base">{abierta.description}</p>}
      </Modal>
    </div>
  )
}

function Filtro({
  activa,
  onClick,
  children,
}: {
  activa: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={activa}
      className={`rounded-fefu px-4 py-2 transition-colors duration-300 ${
        activa ? 'bg-verde text-white' : 'border border-piedra text-piedra hover:bg-rosa-suave'
      }`}
    >
      {children}
    </button>
  )
}
