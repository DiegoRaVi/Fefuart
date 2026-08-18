import { useQuery } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { Link, useParams } from 'react-router'

import { obtenerEvento, clavesDeEventos } from '@/features/eventos/api'
import { clavesDePedidos, obtenerPedido } from '@/features/orders/api'
import { Aviso } from '@/shared/ui/Aviso'
import { Cargando } from '@/shared/ui/Cargando'

type Tipo = 'pedido' | 'evento'

/**
 * Cuanto se espera antes de dejar de preguntar. El webhook suele llegar en
 * un segundo o dos; pasado este rato, insistir no aporta nada y lo honesto
 * es decir que sigue en curso.
 */
const ESPERA_MAXIMA = 45_000

const INTERVALO = 2_000

interface Config {
  /** El estado al que se llega **solo** cuando el cobro esta confirmado. */
  confirmado: string
  /** Mientras siga aqui, el cobro no ha terminado de cuajar. */
  esperando: string
  titulo: string
  hecho: string
  volver: { a: string; texto: string }
}

const CONFIG: Record<Tipo, Config> = {
  pedido: {
    confirmado: 'paid',
    esperando: 'pending_payment',
    titulo: 'Confirmando tu pago',
    hecho: 'Pago recibido. Felicitas ya tiene tu encargo.',
    volver: { a: '/pedidos', texto: 'Ver mis pedidos' },
  },
  evento: {
    confirmado: 'confirmed',
    esperando: 'accepted',
    titulo: 'Confirmando tu reserva',
    hecho: 'Señal recibida. La fecha es tuya.',
    volver: { a: '/live-art', texto: 'Ver mis solicitudes' },
  },
}

/**
 * La vuelta de Stripe.
 *
 * **Esta pantalla no da nada por pagado.** Llegar aqui solo significa que el
 * navegador volvio de la pasarela, y esta URL se puede abrir a mano sin
 * haber pagado un euro. Quien mueve el estado es el webhook con firma
 * verificada; lo unico que hace esto es preguntarle al servidor hasta que lo
 * haya hecho.
 *
 * Por eso se pregunta en vez de leer el `session_id` del enlace: ese
 * parametro es del cliente, y el estado es del servidor.
 */
export function VueltaDelPago({ tipo }: { tipo: Tipo }) {
  const { id = '' } = useParams()
  const config = CONFIG[tipo]
  const [seAgotoLaEspera, setSeAgotoLaEspera] = useState(false)
  const desde = useRef(Date.now())

  // Lo unico que mira esta pantalla es el estado, asi que no le hace falta
  // saber si detras hay un pedido o un evento.
  const { data, isPending, isError, error } = useQuery<{ status: string }>({
    queryKey:
      tipo === 'pedido' ? clavesDePedidos.detalle(id) : clavesDeEventos.detalle(Number(id)),
    queryFn: () => (tipo === 'pedido' ? obtenerPedido(id) : obtenerEvento(Number(id))),
    refetchInterval: (query) => {
      const estado = query.state.data?.status

      if (estado !== config.esperando || seAgotoLaEspera) {
        return false
      }

      return INTERVALO
    },
  })

  useEffect(() => {
    const reloj = setTimeout(
      () => setSeAgotoLaEspera(true),
      ESPERA_MAXIMA - (Date.now() - desde.current),
    )

    return () => clearTimeout(reloj)
  }, [])

  if (isPending) {
    return <Cargando texto="Buscando tu pago..." />
  }

  if (isError) {
    return (
      <div className="mx-auto max-w-xl space-y-4">
        <Aviso tono="error">{error.message}</Aviso>
        <Link className="text-verde underline underline-offset-4" to={config.volver.a}>
          {config.volver.texto}
        </Link>
      </div>
    )
  }

  const listo = data.status === config.confirmado
  const enCurso = data.status === config.esperando && !seAgotoLaEspera

  return (
    <div className="mx-auto max-w-xl space-y-6 text-center">
      <h1 className="text-titulo text-verde">{listo ? '¡Listo!' : config.titulo}</h1>

      {listo && <Aviso tono="exito">{config.hecho}</Aviso>}

      {enCurso && (
        <>
          {/* `Cargando` ya lleva su propio `role="status"`, asi que un lector
              de pantalla anuncia el cambio sin que haya que repetirlo. */}
          <Cargando texto="Estamos confirmando el cobro con la pasarela..." />
          <p className="text-base text-piedra">
            Puedes cerrar esta pagina: la confirmacion no depende de que sigas aqui.
          </p>
        </>
      )}

      {!listo && !enCurso && (
        <Aviso tono="informacion">
          El cobro sigue en curso. En cuanto la pasarela lo confirme lo veras aqui; si
          pasan unos minutos y no cambia, escribenos.
        </Aviso>
      )}

      <Link className="inline-block text-verde underline underline-offset-4" to={config.volver.a}>
        {config.volver.texto}
      </Link>
    </div>
  )
}
