import { useState } from 'react'

import type { Evento, EventStatus } from '@/features/eventos/api'
import { nombreDelEstado } from '@/features/eventos/api'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Cargando } from '@/shared/ui/Cargando'

import { useCambiarEstadoDeEvento, useEventosDeAdmin } from '../api'
import type { CampoDeBusqueda } from '../components/BusquedaPrecisa'
import { Filtros } from '../components/Filtros'
import { Paginacion } from '../components/Paginacion'

const ESTADOS: Record<string, string> = {
  requested: 'Pendiente de revisar',
  quoted: 'Presupuesto enviado',
  accepted: 'Presupuesto aceptado',
  confirmed: 'Confirmado',
  completed: 'Celebrado',
  rejected: 'No disponible',
  cancelled: 'Cancelado',
}

/**
 * En eventos no se busca por numero —nadie llama diciendo «el evento numero
 * siete»— y si por titulo y lugar, que se parecen lo bastante como para que
 * mezclarlos confunda: «Toledo» puede ser el nombre de la boda o donde se
 * celebra.
 */
const CAMPOS: CampoDeBusqueda[] = [
  { valor: 'titulo', etiqueta: 'Titulo', ayuda: 'Boda de Marta y Luis' },
  { valor: 'lugar', etiqueta: 'Lugar', ayuda: 'Finca El Olivar, Toledo' },
  { valor: 'nombre', etiqueta: 'Nombre', ayuda: 'Quien lo pidio' },
  { valor: 'email', etiqueta: 'Correo', ayuda: 'marta@ejemplo.com', tipo: 'email' },
  { valor: 'telefono', etiqueta: 'Telefono', ayuda: '600123456', tipo: 'tel' },
]

/**
 * D27 — presupuestar y confirmar necesitan importe y señal, que son columnas
 * de la Fase 5. Desde aqui solo se puede rechazar, cancelar y dar por
 * celebrado; el backend rechaza lo demas con 422.
 */
const SIGUIENTES: Record<string, EventStatus[]> = {
  requested: ['rejected', 'cancelled'],
  quoted: ['rejected', 'cancelled'],
  accepted: ['cancelled'],
  confirmed: ['completed', 'cancelled'],
  completed: [],
  rejected: [],
  cancelled: [],
}

export function EventosDeAdmin() {
  const [q, setQ] = useState('')
  const [campo, setCampo] = useState<string | null>(null)
  const [estado, setEstado] = useState('')
  const [desde, setDesde] = useState('')
  const [hasta, setHasta] = useState('')
  const [pagina, setPagina] = useState(1)

  const busqueda = useDebounce(q)
  const { data, isPending, isError, error, isFetching } = useEventosDeAdmin({
    q: busqueda,
    buscar_por: campo ?? '',
    status: estado,
    desde,
    hasta,
    page: pagina,
  })

  const hayFiltros = Boolean(q || estado || desde || hasta)

  function cambiar(setter: (v: string) => void) {
    return (valor: string) => {
      setter(valor)
      setPagina(1)
    }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-titulo text-verde">Eventos</h1>

      <Filtros
        q={q}
        onQ={(valor) => {
          setQ(valor)
          setCampo(null)
          setPagina(1)
        }}
        campo={campo}
        onCampo={(nuevoCampo, termino) => {
          setCampo(nuevoCampo)
          setQ(termino)
          setPagina(1)
        }}
        campos={CAMPOS}
        estado={estado}
        onEstado={cambiar(setEstado)}
        estados={ESTADOS}
        desde={desde}
        onDesde={cambiar(setDesde)}
        hasta={hasta}
        onHasta={cambiar(setHasta)}
        ayudaBusqueda="Titulo, lugar, nombre, correo o telefono"
        hayFiltros={hayFiltros}
        onLimpiar={() => {
          setQ('')
          setCampo(null)
          setEstado('')
          setDesde('')
          setHasta('')
          setPagina(1)
        }}
      />

      {isError && <Aviso tono="error">{error.message}</Aviso>}

      {isPending || !data ? (
        <Cargando texto="Cargando los eventos..." />
      ) : data.data.length === 0 ? (
        <Aviso tono="informacion">
          {hayFiltros ? 'Ningun evento cuadra con lo que buscas.' : 'Todavia no hay eventos.'}
        </Aviso>
      ) : (
        <>
          <ul
            aria-busy={isFetching}
            className={`space-y-3 ${isFetching ? 'opacity-60' : ''}`}
          >
            {data.data.map((evento) => (
              <li key={evento.id}>
                <Solicitud evento={evento} />
              </li>
            ))}
          </ul>

          <Paginacion
            pagina={data.meta.current_page}
            ultima={data.meta.last_page}
            total={data.meta.total}
            onPagina={setPagina}
          />
        </>
      )}
    </div>
  )
}

function Solicitud({ evento }: { evento: Evento }) {
  const cambiar = useCambiarEstadoDeEvento()
  const siguientes = SIGUIENTES[evento.status] ?? []

  return (
    <article className="space-y-3 rounded-fefu bg-rosa-suave p-4">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-apartado text-verde">{evento.title}</h2>
        <span className="rounded-fefu bg-rosa px-3 py-1 text-sm text-verde">
          {nombreDelEstado(evento.status)}
        </span>
      </div>

      <p className="text-base text-piedra">
        {new Date(evento.event_date).toLocaleDateString('es-ES', {
          weekday: 'long',
          day: 'numeric',
          month: 'long',
          year: 'numeric',
        })}
        {' · '}
        {evento.schedule === 'morning' ? 'Manana' : 'Tarde'}
        {' · '}
        {evento.location}
      </p>

      {/* N14 — los datos con los que se calcula la tarifa. Si faltan, se dice:
          sin ellos no se puede presupuestar y hay que preguntar. */}
      <dl className="flex flex-wrap gap-x-6 gap-y-1 text-base">
        <div className="flex gap-1">
          <dt className="text-piedra">Invitados:</dt>
          <dd>{evento.guest_count ?? 'sin decir'}</dd>
        </div>
        <div className="flex gap-1">
          <dt className="text-piedra">Horas:</dt>
          <dd>{evento.duration_hours ?? 'sin decir'}</dd>
        </div>
        <div className="flex gap-1">
          <dt className="text-piedra">Tipo:</dt>
          <dd>{evento.event_type ?? 'sin decir'}</dd>
        </div>
      </dl>

      {evento.description && (
        <p className="text-base italic text-piedra">«{evento.description}»</p>
      )}

      <p className="text-base">
        {evento.customer?.name}
        {evento.customer && ' · '}
        {evento.customer && (
          <a className="underline underline-offset-4" href={`mailto:${evento.customer.email}`}>
            {evento.customer.email}
          </a>
        )}
        {evento.phone && ` · ${evento.phone}`}
      </p>

      {cambiar.isError && <Aviso tono="error">{cambiar.error.message}</Aviso>}

      {siguientes.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {siguientes.map((destino) => (
            <Boton
              key={destino}
              type="button"
              variante="secundario"
              onClick={() => cambiar.mutate({ id: evento.id, status: destino })}
              disabled={cambiar.isPending}
            >
              {nombreDelEstado(destino)}
            </Boton>
          ))}
        </div>
      )}

      {/* D27 — presupuestar y cobrar la señal llegan en la Fase 5. */}
      {evento.status === 'requested' && (
        <p className="text-sm text-piedra">
          El presupuesto y la señal llegan con los pagos. De momento, respondele
          por correo.
        </p>
      )}
    </article>
  )
}
