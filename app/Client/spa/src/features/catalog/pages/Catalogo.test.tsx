import { screen } from '@testing-library/react'
import { Route, Routes } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import { api } from '@/shared/api/client'
import { ApiError } from '@/shared/api/errors'
import type { Product } from '@/shared/api/types'
import { renderConProviders } from '@/test/utils'

import { Catalogo } from './Catalogo'
import { FichaProducto } from './FichaProducto'

vi.mock('@/features/auth/api', () => ({ obtenerSesion: vi.fn() }))

/**
 * Se sustituye el cliente HTTP y no las funciones de `catalog/api`, para que
 * los hooks reales sigan corriendo: la clave de cache, el `staleTime` y el
 * desempaquetado del sobre `data` forman parte de lo que hay que probar.
 */
vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn() },
}))

const get = vi.mocked(api.get)

/** Responde al endpoint que toque segun la ruta pedida. */
const catalogo = {
  mockResolvedValue: (productos: Product[]) =>
    get.mockImplementation(async () => ({ data: { data: productos } }) as never),
  mockRejectedValue: (error: unknown) => get.mockRejectedValue(error),
}

const ficha = {
  mockResolvedValue: (producto: Product) =>
    get.mockImplementation(async () => ({ data: { data: producto } }) as never),
  mockRejectedValue: (error: unknown) => get.mockRejectedValue(error),
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(obtenerSesion).mockResolvedValue(null)
})

function unProducto(overrides: Partial<Product> = {}): Product {
  return {
    id: 1,
    slug: 'dibujo-por-encargo',
    name: 'Dibujo por encargo',
    description: 'Retrato dibujado a partir de tu fotografia.',
    category: 'dibujo',
    image: null,
    requires_reference_image: true,
    requires_notes: true,
    max_quantity: 10,
    delivery_days: 15,
    variants: [
      {
        id: 1,
        name: 'Diseno de moda',
        price: '30.00',
        additional_copy_price: '10.00',
        shipping_methods: [
          { id: 1, code: 'physical', name: 'Envio a domicilio', price: '5.00' },
        ],
      },
      {
        id: 3,
        name: 'Digital',
        price: '20.00',
        additional_copy_price: '10.00',
        shipping_methods: [
          { id: 1, code: 'physical', name: 'Envio a domicilio', price: '5.00' },
          { id: 2, code: 'digital', name: 'Descarga digital', price: '0.00' },
        ],
      },
    ],
    ...overrides,
  }
}

describe('el listado', () => {
  it('no exige sesion', async () => {
    catalogo.mockResolvedValue([unProducto()])

    renderConProviders(<Catalogo />)

    expect(await screen.findByText('Dibujo por encargo')).toBeInTheDocument()
  })

  /**
   * DB-002 — en v1 la galeria era HTML estatico porque ninguna tabla decia
   * que se podia encargar ni a que precio. El precio sale del servidor.
   */
  it('enseña el precio mas barato cuando hay varias opciones', async () => {
    catalogo.mockResolvedValue([unProducto()])

    renderConProviders(<Catalogo />)

    expect(await screen.findByText(/Desde/)).toBeInTheDocument()
    expect(screen.getByText(/20,00/)).toBeInTheDocument()
  })

  it('no dice «desde» cuando solo hay una opcion', async () => {
    catalogo.mockResolvedValue([
      unProducto({ variants: [unProducto().variants[0]] }),
    ])

    renderConProviders(<Catalogo />)

    expect(await screen.findByText(/30,00/)).toBeInTheDocument()
    expect(screen.queryByText(/Desde/)).not.toBeInTheDocument()
  })

  /** BUG-008 — en v1 una coleccion vacia respondia 404. */
  it('dice que no hay nada en vez de romperse con el catalogo vacio', async () => {
    catalogo.mockResolvedValue([])

    renderConProviders(<Catalogo />)

    expect(await screen.findByText(/no hay nada en el catalogo/i)).toBeInTheDocument()
  })

  it('avisa si el catalogo no se puede cargar', async () => {
    catalogo.mockRejectedValue(new ApiError(500, 'Algo ha fallado por nuestra parte.'))

    renderConProviders(<Catalogo />)

    expect(await screen.findByRole('alert')).toHaveTextContent('Algo ha fallado')
  })
})

describe('la ficha', () => {
  function renderFicha(slug = 'dibujo-por-encargo') {
    return renderConProviders(
      <Routes>
        <Route path="/encargos/:slug" element={<FichaProducto />} />
      </Routes>,
      { ruta: `/encargos/${slug}` },
    )
  }

  it('enseña cada opcion con su precio', async () => {
    ficha.mockResolvedValue(unProducto())

    renderFicha()

    expect(await screen.findByText('Diseno de moda')).toBeInTheDocument()
    expect(screen.getByText(/30,00.*10,00.*copia adicional/)).toBeInTheDocument()
  })

  /**
   * N7 — solo el estilo digital admite descarga, y el cliente tiene que
   * verlo antes de elegir porque el servidor lo va a rechazar.
   */
  it('enseña que entregas admite cada opcion', async () => {
    ficha.mockResolvedValue(unProducto())

    renderFicha()

    await screen.findByText('Diseno de moda')

    const moda = screen.getByText('Diseno de moda').closest('li')
    const digital = screen.getByText('Digital').closest('li')

    expect(moda).toHaveTextContent('Envio a domicilio')
    expect(moda).not.toHaveTextContent('Descarga digital')
    expect(digital).toHaveTextContent('Descarga digital')
  })

  /** N9 — la foto es el material de partida y hay que decirlo antes. */
  it('avisa de que se dibuja a partir de tu foto cuando toca', async () => {
    ficha.mockResolvedValue(unProducto())

    renderFicha()

    expect(await screen.findByText(/a partir de una foto que subes tu/i)).toBeInTheDocument()
  })

  it('no lo avisa cuando el producto no la necesita', async () => {
    ficha.mockResolvedValue(unProducto({ requires_reference_image: false }))

    renderFicha()

    await screen.findByText('Opciones')
    expect(screen.queryByText(/a partir de una foto que subes tu/i)).not.toBeInTheDocument()
  })

  /** BUG-007 — v1 devolvia 200 con el 404 dentro del cuerpo. */
  it('dice que no existe en vez de quedarse cargando', async () => {
    ficha.mockRejectedValue(new ApiError(404, 'No hemos encontrado lo que buscabas.'))

    renderFicha('no-existe')

    expect(await screen.findByText(/no existe o ya no esta disponible/i)).toBeInTheDocument()
  })
})
