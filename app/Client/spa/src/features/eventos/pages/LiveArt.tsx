import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router'
import { z } from 'zod'

import { useGaleria } from '@/features/galeria/api'
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
  event_date: z.string().min(1, 'Dinos qué día es.'),
  schedule: z.enum(['morning', 'evening']),
  location: z.string().min(1, 'Dinos dónde es.').max(255),
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

/**
 * Lo que suelen preguntar antes de pedir presupuesto.
 *
 * **Solo hay dos, y es a proposito.** La propuesta de diseno llevaba cuatro,
 * pero las otras dos —que material hace falta el dia del evento y hasta donde
 * se desplaza— no las se: no estan en el roadmap, ni en el legacy, ni me las
 * ha dicho nadie. Escribirlas yo seria poner en boca de Felicitas condiciones
 * comerciales que no ha fijado. Estas dos si estan respaldadas: la primera
 * por N14 —invitados y horas son lo que determina la tarifa— y la segunda por
 * N21, que es la regla de la señal tal y como la aplica `QuoteService`.
 */
const PREGUNTAS = [
  {
    pregunta: '¿Te da tiempo a dibujar a todos?',
    respuesta:
      'Depende de las horas y de cuántos seáis. Por eso te los pregunto: con esos dos datos te digo en el presupuesto cuántos retratos salen.',
  },
  {
    pregunta: '¿Y si al final cancelamos?',
    respuesta:
      'La señal reserva tu fecha y bloquea la agenda, así que no se devuelve. Si quien cancela soy yo, te la devuelvo entera.',
  },
]

export function LiveArt() {
  const { data: piezas } = useGaleria()

  const enDirecto = piezas?.filter((pieza) => pieza.category === 'live-art') ?? []
  const portada = enDirecto[0] ?? piezas?.[0]
  const muestra = enDirecto.slice(1, 7)

  return (
    /* Igual que la portada: fuera de `Centrado`, con las bandas a sangre y el
       ancho puesto seccion a seccion. */
    <div>
      {/* Sin foto no hay dos columnas: dejar media pantalla en blanco es
          peor que una entradilla a un ancho. */}
      <section
        className={`mx-auto grid max-w-6xl items-center gap-10 px-6 py-14 ${
          portada?.image?.url ? 'lg:grid-cols-2 lg:gap-14' : ''
        }`}
      >
        <div className="flex flex-col gap-6 lg:order-1">
          <p className="antetitulo text-verde">Live Art</p>

          <h1 className="titular-portada text-verde">
            Tus invitados se van con su retrato puesto
          </h1>

          <p className="max-w-[36ch] text-lg">
            Monto mi mesa en tu boda y dibujo durante el evento. Cada invitado
            ve cómo nace su ilustración y se la lleva a casa esa misma noche.
            No es un photocall: es un recuerdo que no tiene nadie más.
          </p>

          {/*
            No hay ninguna cifra aqui. La propuesta llevaba una fila de datos
            —horas de servicio, retratos por hora, precio de partida— y se ha
            quitado entera: el precio es a medida por N13, y los otros dos
            numeros no los tengo. Una cifra inventada en la pantalla que
            vende es justo la que luego no se puede cumplir.
          */}
          <a
            href="#solicitud"
            className="self-start rounded-fefu bg-piedra px-8 py-4 text-white transition-colors duration-300 hover:bg-verde"
          >
            Consultar mi fecha
          </a>
        </div>

        {portada?.image?.url && (
          <img
            src={portada.image.url}
            alt={portada.title}
            className="aspect-[4/5] w-full rounded-fefu object-cover lg:order-2"
          />
        )}
      </section>

      {muestra.length > 0 && (
        <section aria-labelledby="como-queda" className="bg-rosa-suave px-6 py-14">
          <div className="mx-auto max-w-6xl space-y-6">
            <div className="flex flex-wrap items-baseline justify-between gap-4">
              <h2 id="como-queda" className="text-seccion">
                Cómo queda
              </h2>

              <Link className="text-verde underline underline-offset-4" to="/galeria">
                Ver la galería completa
              </Link>
            </div>

            <ul className="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(240px,1fr))]">
              {muestra.map((pieza) => (
                <li key={pieza.id}>
                  <img
                    src={pieza.thumbnail?.url ?? pieza.image?.url}
                    alt={pieza.title}
                    loading="lazy"
                    className="aspect-[4/3] w-full rounded-fefu object-cover"
                  />
                </li>
              ))}
            </ul>
          </div>
        </section>
      )}

      <div className="mx-auto grid max-w-6xl gap-12 px-6 py-14 lg:grid-cols-2 lg:gap-14">
        <section aria-labelledby="preguntas" className="space-y-6">
          <h2 id="preguntas" className="text-titulo">
            Lo que suelen preguntarme
          </h2>

          <dl className="space-y-5">
            {PREGUNTAS.map((entrada) => (
              <div key={entrada.pregunta} className="space-y-1">
                <dt className="font-bold text-verde">{entrada.pregunta}</dt>
                <dd className="text-base">{entrada.respuesta}</dd>
              </div>
            ))}
          </dl>
        </section>

        <Formulario />
      </div>

      <div className="mx-auto max-w-6xl px-6 pb-14">
        <MisSolicitudes />
      </div>
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
    <section
      id="solicitud"
      aria-labelledby="titulo-solicitud"
      /* `scroll-mt` para que el ancla de la entradilla no deje el titulo
         pegado al borde de arriba ni debajo de la cabecera. */
      className="scroll-mt-24 space-y-5 rounded-fefu bg-rosa-suave p-6 sm:p-8"
    >
      <div className="space-y-2">
        <h2 id="titulo-solicitud" className="text-seccion">
          ¿Tengo tu fecha libre?
        </h2>

        <p className="text-base">
          Cuéntame el evento y te contesto con un presupuesto. El precio se
          hace a medida: depende de las horas y de cuántos seáis.
        </p>
      </div>

      {enviada && (
        <Aviso tono="exito">
          Solicitud enviada. Felicitas la revisa y te prepara un presupuesto.
        </Aviso>
      )}

      {errorGeneral && <Aviso tono="error">{errorGeneral}</Aviso>}

      <form onSubmit={enviar} noValidate className="space-y-4">
        <Campo
          etiqueta="Qué evento es"
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
              className="w-full rounded-fefu border border-piedra/40 bg-white px-3 py-2 text-base"
            >
              <option value="morning">Mañana</option>
              <option value="evening">Tarde</option>
            </select>
          </div>
        </div>

        <Campo
          etiqueta="Dónde"
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
            Algo más que debamos saber
          </label>
          <textarea
            id="description"
            rows={4}
            maxLength={2000}
            {...register('description')}
            className="w-full rounded-fefu border border-piedra/40 bg-white px-3 py-2 text-base"
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
      <h2 id="mias" className="text-seccion">
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
                  {evento.schedule === 'morning' ? 'Mañana' : 'Tarde'}
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
                Si cancelas tú, la señal no se devuelve: la fecha lleva reservada para ti
                desde que la pagaste. Si tuviera que cancelar Felicitas, te la devolvería
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
        <p className="text-sm text-piedra">Válido hasta el {caduca}.</p>
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
