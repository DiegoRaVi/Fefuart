import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import type { Order } from '@/features/cart/api'
import { api } from '@/shared/api/client'
import { renderConProviders, unaAdministradora } from '@/test/utils'

import { PedidosDeAdmin } from './PedidosDeAdmin'

vi.mock('@/features/auth/api', () => ({ obtenerSesion: vi.fn() }))
vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const get = vi.mocked(api.get)

function unPedido(overrides: Partial<Order> = {}): Order {
  return {
    id: 1,
    status: 'paid',
    subtotal: '60.00',
    shipping_total: '5.00',
    total: '65.00',
    placed_at: '2026-08-13T10:00:00+00:00',
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
    items: [],
    items_count: 2,
    customer: { id: 2, name: 'Marta Ruiz', email: 'marta@fefuart.test' },
    ...overrides,
  }
}

function servir(pedidos: Order[]) {
  get.mockImplementation(
    async () =>
      ({
        data: {
          data: pedidos,
          meta: { current_page: 1, last_page: 1, per_page: 20, total: pedidos.length },
        },
      }) as never,
  )
}

/** Los parametros del ultimo GET a /admin/orders. */
function ultimosFiltros(): Record<string, unknown> {
  const llamadas = get.mock.calls.filter(([url]) => url === '/admin/orders')
  const ultima = llamadas[llamadas.length - 1]

  return (ultima?.[1] as { params?: Record<string, unknown> })?.params ?? {}
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(obtenerSesion).mockResolvedValue(unaAdministradora())
  servir([unPedido()])
})

describe('el listado', () => {
  it('enseña el cliente, el estado y el total', async () => {
    renderConProviders(<PedidosDeAdmin />)

    const fila = within(await screen.findByRole('row', { name: /Marta Ruiz/ }))

    expect(fila.getByText('marta@fefuart.test')).toBeInTheDocument()
    expect(fila.getByText('Pagado')).toBeInTheDocument()
    expect(fila.getByText(/65,00/)).toBeInTheDocument()
  })

  /**
   * El listado ya no trae las lineas, solo cuantas son: cargarlas con su
   * foto para veinte pedidos por pagina eran datos que se tiraban.
   */
  it('enseña cuantos encargos tiene sin traerlos', async () => {
    renderConProviders(<PedidosDeAdmin />)

    expect(await screen.findByText('2 encargos')).toBeInTheDocument()
  })

  it('lo dice en singular cuando es uno', async () => {
    servir([unPedido({ items_count: 1 })])

    renderConProviders(<PedidosDeAdmin />)

    expect(await screen.findByText('1 encargo')).toBeInTheDocument()
  })
})

describe('el buscador', () => {
  /**
   * Sin debounce, escribir «marta» son cinco peticiones de las que solo
   * importa la ultima. Cada una escanea con comodin por delante y cuenta
   * contra el limitador de la API (SEC-007).
   */
  it('no dispara una peticion por tecla', async () => {
    renderConProviders(<PedidosDeAdmin />)

    await screen.findByRole('table')
    const antes = get.mock.calls.filter(([url]) => url === '/admin/orders').length

    await userEvent.type(screen.getByLabelText('Buscar'), 'marta')

    // Se espera a que el termino llegue de verdad al servidor...
    await waitFor(() => expect(ultimosFiltros().q).toBe('marta'), { timeout: 2000 })

    const despues = get.mock.calls.filter(([url]) => url === '/admin/orders').length

    // ...y aun asi no ha habido una peticion por cada una de las cinco letras.
    expect(despues - antes).toBeLessThan(5)
  })

  it('manda el termino tal cual lo escribe', async () => {
    renderConProviders(<PedidosDeAdmin />)

    await screen.findByRole('table')
    await userEvent.type(screen.getByLabelText('Buscar'), '600999')

    await waitFor(() => expect(ultimosFiltros().q).toBe('600999'), { timeout: 2000 })
  })

  it('no manda los filtros vacios', async () => {
    renderConProviders(<PedidosDeAdmin />)

    await screen.findByRole('table')

    const filtros = ultimosFiltros()

    expect(filtros).not.toHaveProperty('q')
    expect(filtros).not.toHaveProperty('desde')
    expect(filtros).not.toHaveProperty('status')
  })

  it('avisa de que no hay resultados sin confundirlo con no haber pedidos', async () => {
    renderConProviders(<PedidosDeAdmin />)

    await screen.findByRole('table')
    servir([])

    await userEvent.type(screen.getByLabelText('Buscar'), 'no-existe')

    expect(await screen.findByText(/Ningun pedido cuadra/i)).toBeInTheDocument()
  })

  it('dice otra cosa cuando sencillamente no hay pedidos', async () => {
    servir([])

    renderConProviders(<PedidosDeAdmin />)

    expect(await screen.findByText(/Todavía no hay pedidos/i)).toBeInTheDocument()
  })
})

describe('los filtros', () => {
  it('manda el estado elegido', async () => {
    renderConProviders(<PedidosDeAdmin />)

    await screen.findByRole('table')
    await userEvent.selectOptions(screen.getByLabelText('Estado'), 'shipped')

    await waitFor(() => expect(ultimosFiltros().status).toBe('shipped'))
  })

  /** Un carrito abierto no es un pedido y no se puede filtrar por el. */
  it('no ofrece filtrar por carrito', async () => {
    renderConProviders(<PedidosDeAdmin />)

    const estado = await screen.findByLabelText('Estado')

    expect(within(estado).queryByText('En el carrito')).not.toBeInTheDocument()
  })

  it('manda el rango de fechas', async () => {
    renderConProviders(<PedidosDeAdmin />)

    await screen.findByRole('table')
    await userEvent.type(screen.getByLabelText('Desde'), '2026-01-01')

    await waitFor(() => expect(ultimosFiltros().desde).toBe('2026-01-01'))
  })

  /** El backend valida el rango; esto evita llegar a mandarlo al reves. */
  it('no deja elegir un hasta anterior al desde', async () => {
    renderConProviders(<PedidosDeAdmin />)

    await userEvent.type(await screen.findByLabelText('Desde'), '2026-06-01')

    expect(screen.getByLabelText('Hasta')).toHaveAttribute('min', '2026-06-01')
  })

  it('deja quitar los filtros de una vez', async () => {
    renderConProviders(<PedidosDeAdmin />)

    await screen.findByRole('table')
    await userEvent.selectOptions(screen.getByLabelText('Estado'), 'paid')

    await userEvent.click(await screen.findByRole('button', { name: /Quitar los filtros/i }))

    expect(screen.getByLabelText('Estado')).toHaveValue('')
    await waitFor(() => expect(ultimosFiltros()).not.toHaveProperty('status'))
  })
})
