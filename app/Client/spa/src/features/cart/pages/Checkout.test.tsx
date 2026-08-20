import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import { api } from '@/shared/api/client'
import { ApiError } from '@/shared/api/errors'
import { renderConProviders, unUsuario } from '@/test/utils'

import type { Order, OrderItem } from '../api'
import { Checkout } from './Checkout'

vi.mock('@/features/auth/api', () => ({ obtenerSesion: vi.fn() }))
vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const get = vi.mocked(api.get)
const post = vi.mocked(api.post)

function unaLinea(overrides: Partial<OrderItem> = {}): OrderItem {
  return {
    id: 1,
    product_id: 3,
    variant_id: 5,
    product_name: 'Ramos dibujados',
    variant_name: 'Lamina del ramo',
    delivery_type: 'physical',
    quantity: 1,
    customer_notes: null,
    unit_price: '40.00',
    additional_copy_price: '10.00',
    line_total: '40.00',
    delivered: false,
    ...overrides,
  }
}

function unCarrito(items: OrderItem[], total = '45.00'): Order {
  return {
    id: 9,
    status: 'cart',
    subtotal: '40.00',
    shipping_total: '5.00',
    total,
    placed_at: null,
    shipping_address: {
      name: null,
      phone: null,
      line1: null,
      line2: null,
      city: null,
      province: null,
      postal_code: null,
      country: null,
    },
    items,
  }
}

function servirCarrito(carrito: Order) {
  get.mockImplementation(async () => ({ data: { data: carrito } }) as never)
}

function renderCheckout() {
  return renderConProviders(
    <Routes>
      <Route path="/carrito/confirmar" element={<Checkout />} />
      <Route path="/pedidos/:id" element={<p>Detalle del pedido</p>} />
    </Routes>,
    { ruta: '/carrito/confirmar' },
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(obtenerSesion).mockResolvedValue(unUsuario())
})

describe('la dirección', () => {
  /** N6 — un pedido con alguna linea fisica hay que enviarlo a alguna parte. */
  it('se pide cuando hay una linea fisica', async () => {
    servirCarrito(unCarrito([unaLinea()]))

    renderCheckout()

    expect(await screen.findByLabelText('Dirección')).toBeInTheDocument()
    expect(screen.getByLabelText('Código postal')).toBeInTheDocument()
  })

  /**
   * Si todo es digital, pedir la direccion seria pedir datos personales sin
   * motivo.
   */
  it('no se pide cuando el pedido es enteramente digital', async () => {
    servirCarrito(unCarrito([unaLinea({ delivery_type: 'digital' })], '20.00'))

    renderCheckout()

    expect(await screen.findByText(/no hace falta dirección/i)).toBeInTheDocument()
    expect(screen.queryByLabelText('Direccion')).not.toBeInTheDocument()
  })
})

describe('el resumen', () => {
  it('enseña los importes que ha calculado el servidor', async () => {
    servirCarrito(unCarrito([unaLinea()]))

    renderCheckout()

    await screen.findByText('Resumen')

    expect(screen.getByText(/45,00/)).toBeInTheDocument()
    expect(screen.getByText('IVA incluido.')).toBeInTheDocument()
  })

  it('manda al catalogo si el carrito esta vacío', async () => {
    servirCarrito(unCarrito([]))

    renderCheckout()

    expect(await screen.findByText(/carrito esta vacío/i)).toBeInTheDocument()
  })
})

describe('confirmar el encargo', () => {
  /** SEC-006 — ningun importe sale del cliente, tampoco aqui. */
  it('manda solo la dirección', async () => {
    servirCarrito(unCarrito([unaLinea()]))
    post.mockResolvedValue({ data: { data: { id: 9 } } } as never)

    renderCheckout()

    await userEvent.type(await screen.findByLabelText('Nombre y apellidos'), 'Marta Ruiz')
    await userEvent.type(screen.getByLabelText('Dirección'), 'Calle Mayor 1')
    await userEvent.type(screen.getByLabelText('Código postal'), '45001')
    await userEvent.type(screen.getByLabelText('Ciudad'), 'Toledo')
    await userEvent.click(screen.getByRole('button', { name: 'Confirmar el encargo' }))

    await waitFor(() => expect(post).toHaveBeenCalled())

    const [url, cuerpo] = post.mock.calls[0]

    expect(url).toBe('/cart/checkout')
    expect(Object.keys(cuerpo as object).join(' ')).not.toMatch(/price|total|status/)
    expect(cuerpo).toMatchObject({
      shipping_name: 'Marta Ruiz',
      shipping_line1: 'Calle Mayor 1',
      shipping_postal_code: '45001',
      shipping_city: 'Toledo',
    })
  })

  it('lleva al detalle del pedido cuando entra', async () => {
    servirCarrito(unCarrito([unaLinea({ delivery_type: 'digital' })], '20.00'))
    post.mockResolvedValue({ data: { data: { id: 9 } } } as never)

    renderCheckout()

    await userEvent.click(
      await screen.findByRole('button', { name: 'Confirmar el encargo' }),
    )

    expect(await screen.findByText('Detalle del pedido')).toBeInTheDocument()
  })

  it('pinta en su campo el 422 que devuelve el servidor', async () => {
    servirCarrito(unCarrito([unaLinea()]))
    post.mockRejectedValue(
      new ApiError(422, 'Los datos no son validos.', {
        shipping_postal_code: ['Hace falta el codigo postal.'],
      }),
    )

    renderCheckout()

    await userEvent.click(
      await screen.findByRole('button', { name: 'Confirmar el encargo' }),
    )

    expect(await screen.findByText('Hace falta el codigo postal.')).toBeInTheDocument()
  })

  /**
   * D5 tiene esta consecuencia: Felicitas puede cambiar un precio con alguien
   * teniendo el carrito abierto. El servidor responde 409 con los importes ya
   * actualizados, y el cliente tiene que ver la diferencia antes de volver a
   * confirmar — ni cobrarle el nuevo en silencio ni el viejo.
   */
  it('avisa y enseña el importe nuevo si el precio ha cambiado', async () => {
    servirCarrito(unCarrito([unaLinea()]))
    post.mockRejectedValue(
      new ApiError(409, 'Los precios han cambiado desde que anadiste estos encargos.'),
    )

    renderCheckout()

    await userEvent.click(
      await screen.findByRole('button', { name: 'Confirmar el encargo' }),
    )

    expect(await screen.findByText(/precios han cambiado/i)).toBeInTheDocument()
    expect(screen.getByText(/confirma otra vez/i)).toBeInTheDocument()
  })

  it('vuelve a pedir el carrito tras el 409 para enseñar lo nuevo', async () => {
    servirCarrito(unCarrito([unaLinea()]))
    post.mockRejectedValue(new ApiError(409, 'Los precios han cambiado.'))

    renderCheckout()

    await screen.findByText('Resumen')
    const antes = get.mock.calls.length

    // Ahora el servidor ya devuelve el importe nuevo.
    servirCarrito(unCarrito([unaLinea({ line_total: '55.00' })], '60.00'))

    await userEvent.click(screen.getByRole('button', { name: 'Confirmar el encargo' }))

    await waitFor(() => expect(get.mock.calls.length).toBeGreaterThan(antes))
    expect(await screen.findByText(/60,00/)).toBeInTheDocument()
  })
})
