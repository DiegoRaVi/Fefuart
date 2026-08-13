import { useId, useState } from 'react'

import { Boton } from '@/shared/ui/Boton'

import { BusquedaPrecisa, type CampoDeBusqueda } from './BusquedaPrecisa'

interface Props {
  q: string
  onQ: (valor: string) => void
  /** Campo al que esta acotada la busqueda, o null si mira en todo. */
  campo: string | null
  onCampo: (campo: string | null, termino: string) => void
  campos: CampoDeBusqueda[]
  estado: string
  onEstado: (valor: string) => void
  estados: Record<string, string>
  desde: string
  onDesde: (valor: string) => void
  hasta: string
  onHasta: (valor: string) => void
  ayudaBusqueda: string
  hayFiltros: boolean
  onLimpiar: () => void
}

/**
 * Dos formas de buscar, porque son dos situaciones distintas.
 *
 * La caja mira en todo y es para el caso de todos los dias: alguien escribe
 * preguntando y se pega en la caja lo que haya dado. El modal acota a un
 * campo y es para cuando eso no basta —«600» en la caja mezcla telefonos,
 * numeros de pedido y nombres con esas cifras—.
 *
 * El termino es uno solo: lo que cambia es si va acotado o no. Escribir en
 * la caja quita la acotacion, y acotar desde el modal sustituye lo escrito.
 */
export function Filtros({
  q,
  onQ,
  campo,
  onCampo,
  campos,
  estado,
  onEstado,
  estados,
  desde,
  onDesde,
  hasta,
  onHasta,
  ayudaBusqueda,
  hayFiltros,
  onLimpiar,
}: Props) {
  const id = useId()
  const [modalAbierto, setModalAbierto] = useState(false)

  const nombreDelCampo = campos.find((c) => c.valor === campo)?.etiqueta

  return (
    <section aria-label="Filtros" className="space-y-3 rounded-fefu bg-rosa-suave p-4">
      <div className="space-y-1">
        <label htmlFor={`${id}-q`} className="block font-bold text-verde">
          Buscar
        </label>

        <div className="flex flex-wrap gap-2">
          <input
            id={`${id}-q`}
            type="search"
            value={q}
            // Escribir aqui deshace la acotacion: la caja es «busca en todo».
            onChange={(e) => onQ(e.target.value)}
            placeholder={ayudaBusqueda}
            className="min-w-48 flex-1 rounded-fefu border border-piedra/40 px-3 py-2 text-base"
          />

          <Boton type="button" variante="secundario" onClick={() => setModalAbierto(true)}>
            Buscar por un dato
          </Boton>
        </div>

        {campo && (
          <p role="status" className="text-sm text-verde">
            Buscando solo por <strong>{nombreDelCampo?.toLowerCase()}</strong>.{' '}
            <button
              type="button"
              onClick={() => onCampo(null, q)}
              className="underline underline-offset-4"
            >
              Buscar en todo
            </button>
          </p>
        )}
      </div>

      <div className="grid gap-3 sm:grid-cols-3">
        <div className="space-y-1">
          <label htmlFor={`${id}-estado`} className="block font-bold text-verde">
            Estado
          </label>
          <select
            id={`${id}-estado`}
            value={estado}
            onChange={(e) => onEstado(e.target.value)}
            className="w-full rounded-fefu border border-piedra/40 px-3 py-2 text-base"
          >
            <option value="">Todos</option>
            {Object.entries(estados).map(([valor, nombre]) => (
              <option key={valor} value={valor}>
                {nombre}
              </option>
            ))}
          </select>
        </div>

        <div className="space-y-1">
          <label htmlFor={`${id}-desde`} className="block font-bold text-verde">
            Desde
          </label>
          <input
            id={`${id}-desde`}
            type="date"
            value={desde}
            onChange={(e) => onDesde(e.target.value)}
            className="w-full rounded-fefu border border-piedra/40 px-3 py-2 text-base"
          />
        </div>

        <div className="space-y-1">
          <label htmlFor={`${id}-hasta`} className="block font-bold text-verde">
            Hasta
          </label>
          <input
            id={`${id}-hasta`}
            type="date"
            value={hasta}
            // El backend valida que no sea anterior a `desde`; esto evita
            // llegar a mandarlo.
            min={desde || undefined}
            onChange={(e) => onHasta(e.target.value)}
            className="w-full rounded-fefu border border-piedra/40 px-3 py-2 text-base"
          />
        </div>
      </div>

      {hayFiltros && (
        <button
          type="button"
          onClick={onLimpiar}
          className="text-base text-verde underline underline-offset-4"
        >
          Quitar los filtros
        </button>
      )}

      <BusquedaPrecisa
        abierto={modalAbierto}
        onCerrar={() => setModalAbierto(false)}
        campos={campos}
        busqueda={campo ? { campo, termino: q } : null}
        onBuscar={(busqueda) => onCampo(busqueda?.campo ?? null, busqueda?.termino ?? '')}
      />
    </section>
  )
}
