import { screen } from '@testing-library/react'
import { Route, Routes } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import { api } from '@/shared/api/client'
import { renderConProviders, unUsuario } from '@/test/utils'

import { VueltaDelPago } from './VueltaDelPago'

vi.mock('@/features/auth/api', () => ({ obtenerSesion: vi.fn() }))
vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const get = vi.mocked(api.get)

function servirEstado(...estados: string[]) {
  let llamada = 0

  get.mockImplementation(async () => {
    const status = estados[Math.min(llamada, estados.length - 1)]
    llamada += 1

    return { data: { data: { id: 1, status } } } as never
  })
}

function montar(tipo: 'pedido' | 'evento', ruta: string) {
  const camino = tipo === 'pedido' ? '/pedidos/:id/pago' : '/live-art/:id/pago'

  return renderConProviders(
    <Routes>
      <Route path={camino} element={<VueltaDelPago tipo={tipo} />} />
    </Routes>,
    { ruta },
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(obtenerSesion).mockResolvedValue(unUsuario())
})

/**
 * D3 — la vuelta de Stripe **no es prueba de pago**.
 *
 * Esta URL se puede abrir a mano, con el `session_id` que a uno le apetezca
 * y sin haber pagado un euro. Quien mueve el pedido a `paid` es el webhook
 * con firma verificada; esta pantalla solo pregunta.
 *
 * Es la version en el navegador del mismo agujero que tenia v1, donde
 * «Pagar» era un `PATCH {status:'paid'}` que el servidor aceptaba tal cual
 * (SEC-003).
 */
describe('no se cree la URL', () => {
  it('no da por pagado un pedido que el servidor dice que no lo esta', async () => {
    servirEstado('pending_payment')

    montar('pedido', '/pedidos/1/pago?sesion=cs_test_inventada')

    expect(await screen.findByText(/confirmando el cobro/i)).toBeInTheDocument()
    expect(screen.queryByText(/pago recibido/i)).not.toBeInTheDocument()
  })

  it('no da por reservado un evento que el servidor dice que no lo esta', async () => {
    servirEstado('accepted')

    montar('evento', '/live-art/1/pago?sesion=cs_test_inventada')

    expect(await screen.findByText(/confirmando el cobro/i)).toBeInTheDocument()
    expect(screen.queryByText(/señal recibida/i)).not.toBeInTheDocument()
  })

  /** El `session_id` del enlace no se manda a ningun sitio: no autoriza nada. */
  it('no manda a la API el identificador de sesion del enlace', async () => {
    servirEstado('paid')

    montar('pedido', '/pedidos/1/pago?sesion=cs_test_inventada')

    await screen.findByText(/pago recibido/i)

    expect(get).toHaveBeenCalledWith('/orders/1')
  })
})

it('avisa cuando el pago ya consta', async () => {
  servirEstado('paid')

  montar('pedido', '/pedidos/1/pago')

  expect(await screen.findByText(/pago recibido/i)).toBeInTheDocument()
})

it('avisa cuando la reserva ya consta', async () => {
  servirEstado('confirmed')

  montar('evento', '/live-art/1/pago')

  expect(await screen.findByText(/señal recibida/i)).toBeInTheDocument()
})

/**
 * La carrera normal: el navegador vuelve de Stripe antes que el webhook. La
 * pantalla tiene que enterarse sola, sin que el cliente recargue.
 */
it('se entera cuando el webhook llega despues', async () => {
  servirEstado('pending_payment', 'paid')

  montar('pedido', '/pedidos/1/pago')

  await screen.findByText(/confirmando el cobro/i)

  expect(await screen.findByText(/pago recibido/i, {}, { timeout: 5000 })).toBeInTheDocument()
}, 10_000)
