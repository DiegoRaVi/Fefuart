import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { beforeEach, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import type { Order } from '@/features/cart/api'
import { api } from '@/shared/api/client'
import { renderConProviders, unUsuario } from '@/test/utils'

import { DetalleDePedido } from './DetalleDePedido'

vi.mock('@/features/auth/api', () => ({ obtenerSesion: vi.fn() }))
vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const get = vi.mocked(api.get)
const post = vi.mocked(api.post)
const irA = vi.fn()

function unPedido(overrides: Partial<Order> = {}): Order {
  return {
    id: 7,
    status: 'pending_payment',
    subtotal: '40.00',
    shipping_total: '5.00',
    total: '45.00',
    placed_at: '2026-08-18T10:00:00+00:00',
    shipping_address: {
      name: 'Marta Ruiz',
      phone: '600123456',
      line1: 'Calle Mayor 1',
      line2: null,
      city: 'Toledo',
      province: 'Toledo',
      postal_code: '45001',
      country: 'ES',
    },
    items: [],
    ...overrides,
  }
}

function montar() {
  return renderConProviders(
    <Routes>
      <Route path="/pedidos/:id" element={<DetalleDePedido />} />
    </Routes>,
    { ruta: '/pedidos/7' },
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(obtenerSesion).mockResolvedValue(unUsuario())

  // jsdom no navega. Se sustituye para poder afirmar a donde se iria.
  Object.defineProperty(window, 'location', {
    configurable: true,
    value: { ...window.location, assign: irA },
  })
})

/**
 * SEC-006 — el cuerpo va vacio.
 *
 * En v1 pagar era un `PATCH /orders/{id}` que aceptaba `total`, `address` y
 * `status` del navegador. Aqui lo unico que viaja es el id en la URL: el
 * importe lo tiene el pedido y lo calculo el servidor.
 */
it('pide la sesion de pago sin mandar ningun importe', async () => {
  get.mockResolvedValue({ data: { data: unPedido() } } as never)
  post.mockResolvedValue({
    data: { url: 'https://checkout.stripe.com/c/pay/cs_test_1', payment_id: 3 },
  } as never)

  montar()

  await userEvent.click(await screen.findByRole('button', { name: /pagar 45/i }))

  await waitFor(() => expect(post).toHaveBeenCalled())

  expect(post.mock.calls[0][0]).toBe('/orders/7/pay')
  expect(post.mock.calls[0][1]).toBeUndefined()
})

it('lleva a la pasarela con la URL que da el servidor', async () => {
  get.mockResolvedValue({ data: { data: unPedido() } } as never)
  post.mockResolvedValue({
    data: { url: 'https://checkout.stripe.com/c/pay/cs_test_1', payment_id: 3 },
  } as never)

  montar()

  await userEvent.click(await screen.findByRole('button', { name: /pagar 45/i }))

  await waitFor(() =>
    expect(irA).toHaveBeenCalledWith('https://checkout.stripe.com/c/pay/cs_test_1'),
  )
})

/** Pagar dos veces el mismo pedido no es un caso de uso. */
it('no ofrece pagar un pedido ya pagado', async () => {
  get.mockResolvedValue({ data: { data: unPedido({ status: 'paid' }) } } as never)

  montar()

  await screen.findByText(/pedido #7/i)

  expect(screen.queryByRole('button', { name: /pagar/i })).not.toBeInTheDocument()
})
