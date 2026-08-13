import { useId } from 'react'

interface Props {
  q: string
  onQ: (valor: string) => void
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
 * Una sola caja de busqueda, no una por campo: quien pregunta por su encargo
 * da lo que tiene a mano —un numero, un nombre, un telefono— y Felicitas no
 * deberia tener que decidir en cual de cuatro casillas escribirlo.
 */
export function Filtros({
  q,
  onQ,
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

  return (
    <section aria-label="Filtros" className="space-y-3 rounded-fefu bg-rosa-suave p-4">
      <div className="space-y-1">
        <label htmlFor={`${id}-q`} className="block font-bold text-verde">
          Buscar
        </label>
        <input
          id={`${id}-q`}
          type="search"
          value={q}
          onChange={(e) => onQ(e.target.value)}
          placeholder={ayudaBusqueda}
          className="w-full rounded-fefu border border-piedra/40 px-3 py-2 text-base"
        />
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
    </section>
  )
}
