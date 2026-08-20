import { useState } from 'react'

import type { Evento, EventStatus } from '@/features/eventos/api'
import { nombreDelEstado } from '@/features/eventos/api'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Cargando } from '@/shared/ui/Cargando'
import { Modal } from '@/shared/ui/Modal'
import { euros } from '@/shared/lib/dinero'

import { useCambiarEstadoDeEvento, useEventosDeAdmin, usePresupuestarEvento } from '../api'
import type { CampoDeBusqueda } from '../components/BusquedaPrecisa'
import { Filtros } from '../components/Filtros'
import { Paginacion } from '../components/Paginacion'

const ESTADOS: Record<string, string> = {
  requested: 'Pendiente de revisar',
  quoted: 'Presupuesto enviado',
  accepted: 'Pendiente de la señal',
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
  { valor: 'titulo', etiqueta: 'Título', ayuda: 'Boda de Marta y Luis' },
  { valor: 'lugar', etiqueta: 'Lugar', ayuda: 'Finca El Olivar, Toledo' },
  { valor: 'nombre', etiqueta: 'Nombre', ayuda: 'Quien lo pidio' },
  { valor: 'email', etiqueta: 'Correo', ayuda: 'marta@ejemplo.com', tipo: 'email' },
  { valor: 'telefono', etiqueta: 'Teléfono', ayuda: '600123456', tipo: 'tel' },
]

/**
 * Las transiciones que no necesitan datos de mas. Presupuestar tiene su
 * propio boton porque exige un importe, y confirmar no esta: lo hace el
 * webhook cuando la señal se cobra (N15). El backend rechaza con 422
 * cualquier otra cosa que llegue por `/status`.
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
        ayudaBusqueda="Titulo, lugar, nombre, correo o teléfono"
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
          {hayFiltros ? 'Ningun evento cuadra con lo que buscas.' : 'Todavía no hay eventos.'}
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
  const [presupuestando, setPresupuestando] = useState(false)
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

      {/* D6, N15 — el presupuesto emitido y la señal calculada. */}
      {evento.quoted_amount && (
        <dl className="flex flex-wrap gap-x-6 gap-y-1 border-t border-piedra/20 pt-3 text-base">
          <div className="flex gap-1">
            <dt className="text-piedra">Presupuesto:</dt>
            <dd className="text-verde">{euros(evento.quoted_amount)}</dd>
          </div>
          {evento.deposit_amount && (
            <div className="flex gap-1">
              <dt className="text-piedra">Señal:</dt>
              <dd className="text-verde">{euros(evento.deposit_amount)}</dd>
            </div>
          )}
          {evento.quote_expires_at && (
            <div className="flex gap-1">
              <dt className="text-piedra">{evento.quote_expired ? 'Caduco el:' : 'Vale hasta:'}</dt>
              <dd>{new Date(evento.quote_expires_at).toLocaleDateString('es-ES')}</dd>
            </div>
          )}
        </dl>
      )}

      {cambiar.isError && <Aviso tono="error">{cambiar.error.message}</Aviso>}

      {(siguientes.length > 0 || evento.can.quote) && (
        <div className="flex flex-wrap gap-2">
          {/* Quien puede presupuestar lo dice el servidor; el estado en el
              que tiene sentido, la maquina de estados. */}
          {evento.can.quote && evento.status === 'requested' && (
            <Boton type="button" onClick={() => setPresupuestando(true)}>
              Presupuestar
            </Boton>
          )}

          {siguientes.map((destino) => (
            <Boton
              key={destino}
              type="button"
              variante="secundario"
              onClick={() => cambiar.mutate({ id: evento.id, status: destino })}
              disabled={cambiar.isPending}
            >
              {/* N21 — devolver dinero no puede ser un efecto secundario
                  silencioso: si el boton lo va a hacer, lo dice. */}
              {destino === 'cancelled' && evento.deposit_paid
                ? `Cancelar y devolver la señal${
                    evento.deposit_amount ? ` (${euros(evento.deposit_amount)})` : ''
                  }`
                : nombreDelEstado(destino)}
            </Boton>
          ))}
        </div>
      )}

      {evento.status === 'accepted' && (
        <p className="text-sm text-piedra">
          Aceptado. La fecha se reserva sola en cuanto la señal se cobre.
        </p>
      )}

      <FormularioDePresupuesto
        evento={evento}
        abierto={presupuestando}
        onCerrar={() => setPresupuestando(false)}
      />
    </article>
  )
}

/**
 * D6, N13 — el presupuesto que emite la artista.
 *
 * Solo se escribe el importe total. La señal no es un campo: la calcula el
 * servidor con el porcentaje de `Ajustes`, igual que ningun precio del
 * catalogo llega del cliente (SEC-006).
 */
function FormularioDePresupuesto({
  evento,
  abierto,
  onCerrar,
}: {
  evento: Evento
  abierto: boolean
  onCerrar: () => void
}) {
  const presupuestar = usePresupuestarEvento()
  const [importe, setImporte] = useState('')

  return (
    <Modal titulo={`Presupuestar «${evento.title}»`} abierto={abierto} onCerrar={onCerrar}>
      <form
        className="space-y-4"
        onSubmit={(e) => {
          e.preventDefault()

          presupuestar.mutate(
            { id: evento.id, quoted_amount: importe },
            {
              onSuccess: () => {
                setImporte('')
                onCerrar()
              },
            },
          )
        }}
      >
        <div className="space-y-1">
          <label className="block text-base text-piedra" htmlFor={`importe-${evento.id}`}>
            Importe total, IVA incluido
          </label>

          <div className="flex items-center gap-2">
            <input
              id={`importe-${evento.id}`}
              type="number"
              inputMode="decimal"
              step="0.01"
              min="1"
              max="99999.99"
              required
              value={importe}
              onChange={(e) => setImporte(e.target.value)}
              className="w-40 rounded-fefu border border-piedra/40 px-3 py-2 text-piedra focus:border-verde focus:outline-none"
            />
            <span className="text-base text-piedra">EUR</span>
          </div>

          <p className="text-sm text-piedra">
            La señal se calcula sola con el porcentaje de Ajustes y se guarda con el
            presupuesto: cambiarlo después no toca los ya enviados.
          </p>
        </div>

        {presupuestar.isError && <Aviso tono="error">{presupuestar.error.message}</Aviso>}

        <div className="flex gap-2">
          <Boton type="submit" disabled={presupuestar.isPending || importe === ''}>
            {presupuestar.isPending ? 'Enviando...' : 'Enviar el presupuesto'}
          </Boton>
          <Boton type="button" variante="secundario" onClick={onCerrar}>
            Cancelar
          </Boton>
        </div>
      </form>
    </Modal>
  )
}
