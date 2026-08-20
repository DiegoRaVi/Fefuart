import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { useAceptarPresupuesto } from '@/features/pagos/api'
import { aplicarErroresDeApi } from '@/shared/api/formulario'
import { euros } from '@/shared/lib/dinero'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Campo } from '@/shared/ui/Campo'

import {
  type Evento,
  nombreDelEstado,
  useCancelarEvento,
  useEventos,
  usePedirEvento,
} from '../api'

/**
 * N13 — el precio siempre es a medida, asi que en esta pantalla no hay
 * ningun importe: se pide, la artista revisa y presupuesta.
 *
 * N14 — invitados y duracion son los que determinan la tarifa, y v1 no los
 * pedia. Van marcados como lo que son: lo que hace falta para poder dar un
 * numero.
 */
const esquema = z.object({
  title: z.string().min(1, 'Ponle un nombre al evento.').max(255),
  event_date: z.string().min(1, 'Dinos que dia es.'),
  schedule: z.enum(['morning', 'evening']),
  location: z.string().min(1, 'Dinos donde es.').max(255),
  guest_count: z.coerce.number().int().min(1).max(5000).optional().or(z.literal('')),
  duration_hours: z.coerce.number().int().min(1).max(24).optional().or(z.literal('')),
  event_type: z.string().max(50).optional(),
  phone: z.string().max(30).optional(),
  description: z.string().max(2000).optional(),
})

type Datos = z.infer<typeof esquema>

const CAMPOS = [
  'title',
  'event_date',
  'schedule',
  'location',
  'guest_count',
  'duration_hours',
  'event_type',
  'phone',
  'description',
] as const

export function LiveArt() {
  return (
    <div className="mx-auto max-w-2xl space-y-10">
      <header className="space-y-3">
        <h1 className="text-titulo text-verde">Live Art</h1>
        <p>
          Felicitas dibuja en directo durante tu evento y los invitados se
          llevan el dibujo. Cada evento es distinto, así que el precio se hace
          a medida: cuéntanos como es el tuyo y te preparamos un presupuesto.
        </p>
      </header>

      <Formulario />
      <MisSolicitudes />
    </div>
  )
}

function Formulario() {
  const pedir = usePedirEvento()
  const [enviada, setEnviada] = useState(false)
  const [errorGeneral, setErrorGeneral] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<Datos>({
    resolver: zodResolver(esquema),
    defaultValues: { schedule: 'evening' },
  })

  const enviar = handleSubmit(async (datos) => {
    setErrorGeneral(null)
    setEnviada(false)

    try {
      await pedir.mutateAsync({
        ...datos,
        // Los opcionales vacios van como null, no como cadena vacia.
        guest_count: datos.guest_count === '' ? null : Number(datos.guest_count),
        duration_hours: datos.duration_hours === '' ? null : Number(datos.duration_hours),
        event_type: datos.event_type || null,
        phone: datos.phone || null,
        description: datos.description || null,
      })

      setEnviada(true)
      reset({ schedule: 'evening' })
    } catch (error) {
      setErrorGeneral(aplicarErroresDeApi(error, setError, CAMPOS))
    }
  })

  return (
    <section aria-labelledby="solicitud" className="space-y-4">
      <h2 id="solicitud" className="text-seccion text-verde">
        Cuéntanos tu evento
      </h2>

      {enviada && (
        <Aviso tono="exito">
          Solicitud enviada. Felicitas la revisa y te prepara un presupuesto.
        </Aviso>
      )}

      {errorGeneral && <Aviso tono="error">{errorGeneral}</Aviso>}

      <form onSubmit={enviar} noValidate className="space-y-4">
        <Campo
          etiqueta="Que evento es"
          ayuda="Por ejemplo: boda de Marta y Luis."
          error={errors.title?.message}
          {...register('title')}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <Campo
            etiqueta="Fecha"
            type="date"
            error={errors.event_date?.message}
            {...register('event_date')}
          />

          <div className="space-y-1">
            <label htmlFor="schedule" className="block font-bold text-verde">
              Franja
            </label>
            <select
              id="schedule"
              {...register('schedule')}
              className="w-full rounded-fefu border border-piedra/40 px-3 py-2 text-base"
            >
              <option value="morning">Manana</option>
              <option value="evening">Tarde</option>
            </select>
          </div>
        </div>

        <Campo
          etiqueta="Donde"
          ayuda="Sitio y población."
          error={errors.location?.message}
          {...register('location')}
        />

        {/* N14 — los dos numeros con los que se calcula la tarifa. */}
        <div className="grid gap-4 sm:grid-cols-3">
          <Campo
            etiqueta="Invitados"
            type="number"
            min={1}
            ayuda="Aproximado."
            error={errors.guest_count?.message}
            {...register('guest_count')}
          />
          <Campo
            etiqueta="Horas"
            type="number"
            min={1}
            ayuda="De servicio."
            error={errors.duration_hours?.message}
            {...register('duration_hours')}
          />
          <Campo
            etiqueta="Tipo"
            ayuda="Boda, comunión..."
            error={errors.event_type?.message}
            {...register('event_type')}
          />
        </div>

        <Campo
          etiqueta="Teléfono"
          type="tel"
          autoComplete="tel"
          error={errors.phone?.message}
          {...register('phone')}
        />

        <div className="space-y-1">
          <label htmlFor="description" className="block font-bold text-verde">
            Algo mas que debamos saber
          </label>
          <textarea
            id="description"
            rows={4}
            maxLength={2000}
            {...register('description')}
            className="w-full rounded-fefu border border-piedra/40 px-3 py-2 text-base"
          />
        </div>

        <Boton type="submit" disabled={isSubmitting} className="w-full">
          {isSubmitting ? 'Enviando...' : 'Pedir presupuesto'}
        </Boton>
      </form>
    </section>
  )
}

function MisSolicitudes() {
  const { data, isPending } = useEventos()
  const cancelar = useCancelarEvento()

  if (isPending || !data || data.data.length === 0) {
    return null
  }

  return (
    <section aria-labelledby="mias" className="space-y-3">
      <h2 id="mias" className="text-seccion text-verde">
        Tus solicitudes
      </h2>

      <ul className="space-y-3">
        {data.data.map((evento) => (
          <li key={evento.id} className="space-y-3 rounded-fefu bg-rosa-suave p-4">
            <div className="flex flex-wrap items-baseline justify-between gap-3">
              <div>
                <h3 className="text-apartado text-verde">{evento.title}</h3>
                <p className="text-sm text-piedra">
                  {new Date(evento.event_date).toLocaleDateString('es-ES', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                  })}
                  {' · '}
                  {evento.schedule === 'morning' ? 'Manana' : 'Tarde'}
                  {' · '}
                  {evento.location}
                </p>
              </div>

              <div className="flex items-center gap-3">
                <span className="rounded-fefu bg-rosa px-3 py-1 text-sm text-verde">
                  {nombreDelEstado(evento.status)}
                </span>

                {/* Lo dice el servidor, no lo deduce esta pantalla del estado. */}
                {evento.can.cancel && (
                  <Boton
                    type="button"
                    variante="secundario"
                    onClick={() => cancelar.mutate(evento.id)}
                    disabled={cancelar.isPending}
                  >
                    Cancelar
                  </Boton>
                )}
              </div>
            </div>

            {evento.quoted_amount && <Presupuesto evento={evento} />}

            {/* N21 — se dice antes de pulsar, no despues. La señal reserva la
                fecha y bloquea la agenda: quien se echa atras compensa el
                hueco. */}
            {evento.deposit_paid && evento.can.cancel && (
              <p className="text-sm text-piedra">
                Si cancelas tu, la señal no se devuelve: la fecha lleva reservada para ti
                desde que la pagaste. Si tuviera que cancelar Felicitas, te la devolveria
                entera.
              </p>
            )}
          </li>
        ))}
      </ul>
    </section>
  )
}

/**
 * D6, N15 — el presupuesto de la artista y la señal que reserva la fecha.
 *
 * Aceptar y pagar son un solo boton porque para el cliente son un solo
 * gesto: aceptar es reservar, y reservar es pagar. El importe se pinta, no
 * se manda: el servidor cobra el que guardo al presupuestar (SEC-006).
 */
function Presupuesto({ evento }: { evento: Evento }) {
  const aceptar = useAceptarPresupuesto()

  const caduca = evento.quote_expires_at
    ? new Date(evento.quote_expires_at).toLocaleDateString('es-ES', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      })
    : null

  return (
    <div className="space-y-2 border-t border-piedra/20 pt-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <span className="text-base text-piedra">Presupuesto</span>
        <span className="text-apartado text-verde">{euros(evento.quoted_amount ?? '0')}</span>
      </div>

      {evento.deposit_amount && (
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <span className="text-sm text-piedra">Señal para reservar la fecha</span>
          <span className="text-base text-verde">{euros(evento.deposit_amount)}</span>
        </div>
      )}

      {/* P1 — un presupuesto no vale para siempre, y el plazo se dice antes
          de que se acabe, no cuando ya ha caducado. */}
      {caduca && !evento.quote_expired && (
        <p className="text-sm text-piedra">Valido hasta el {caduca}.</p>
      )}

      {evento.quote_expired && (
        <Aviso tono="informacion">
          Este presupuesto ha caducado. Escríbenos y te preparamos uno nuevo.
        </Aviso>
      )}

      {aceptar.isError && <Aviso tono="error">{aceptar.error.message}</Aviso>}

      {/* Quien puede aceptar lo dice el servidor. Un evento ya confirmado o
          de otra persona no llega aqui con el permiso puesto. */}
      {evento.can.accept_quote && !evento.quote_expired && (
        <Boton
          type="button"
          onClick={() => aceptar.mutate(evento.id)}
          disabled={aceptar.isPending}
        >
          {aceptar.isPending
            ? 'Abriendo la pasarela...'
            : `Aceptar y pagar la señal de ${euros(evento.deposit_amount ?? '0')}`}
        </Boton>
      )}

      {evento.status === 'accepted' && (
        <p className="text-sm text-piedra">
          La fecha se reserva en cuanto la señal se confirme.
        </p>
      )}
    </div>
  )
}
