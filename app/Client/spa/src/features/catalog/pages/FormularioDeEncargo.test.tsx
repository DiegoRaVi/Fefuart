import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import { subirFoto } from '@/features/media/api'
import { api } from '@/shared/api/client'
import { ApiError } from '@/shared/api/errors'
import type { Product } from '@/shared/api/types'
import { renderConProviders, unUsuario } from '@/test/utils'

import { FormularioDeEncargo } from './FormularioDeEncargo'

vi.mock('@/features/auth/api', () => ({ obtenerSesion: vi.fn() }))
vi.mock('@/features/media/api', () => ({ subirFoto: vi.fn(), borrarFoto: vi.fn() }))

/**
 * Se sustituye el cliente HTTP y no las funciones de cada `api.ts`: los
 * hooks las llaman dentro del propio modulo, asi que un `vi.mock` sobre el
 * export no llega a interceptarlas. Ademas asi corren los hooks de verdad.
 */
vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const get = vi.mocked(api.get)
const post = vi.mocked(api.post)
const subir = vi.mocked(subirFoto)

const producto = {
  mockResolvedValue: (p: Product) =>
    get.mockImplementation(async () => ({ data: { data: p } }) as never),
}

const anadir = {
  mockResolvedValue: () =>
    post.mockImplementation(async () => ({ data: { data: {} } }) as never),
  mockRejectedValue: (error: unknown) => post.mockRejectedValue(error),
  get llamadas() {
    return post.mock.calls.filter(([url]) => url === '/cart/items')
  },
}

/** El cuerpo del primer POST al carrito. */
function loEnviadoAlCarrito(): Record<string, unknown> {
  return anadir.llamadas[0][1] as Record<string, unknown>
}

const FISICO = { id: 1, code: 'physical' as const, name: 'Envio a domicilio', price: '5.00' }
const DIGITAL = { id: 2, code: 'digital' as const, name: 'Descarga digital', price: '0.00' }

function unDibujo(overrides: Partial<Product> = {}): Product {
  return {
    id: 7,
    slug: 'dibujo-por-encargo',
    name: 'Dibujo por encargo',
    description: null,
    category: 'dibujo',
    image: null,
    requires_reference_image: true,
    requires_notes: true,
    max_quantity: 10,
    delivery_days: 15,
    variants: [
      {
        id: 11,
        name: 'Acuarela',
        price: '40.00',
        additional_copy_price: '10.00',
        shipping_methods: [FISICO],
      },
      {
        id: 13,
        name: 'Digital',
        price: '20.00',
        additional_copy_price: '10.00',
        shipping_methods: [FISICO, DIGITAL],
      },
    ],
    ...overrides,
  }
}

function renderFormulario() {
  return renderConProviders(
    <Routes>
      <Route path="/encargos/:slug/encargar" element={<FormularioDeEncargo />} />
      <Route path="/carrito" element={<p>Tu carrito</p>} />
    </Routes>,
    { ruta: '/encargos/dibujo-por-encargo/encargar' },
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(obtenerSesion).mockResolvedValue(unUsuario())
  producto.mockResolvedValue(unDibujo())
})

/**
 * N7 — el tipo de entrega lo limita la variante. En v1 esto era un `if`
 * dentro de un `<script>`, de modo que el servidor no lo comprobaba en
 * absoluto; ahora lo comprueba, y el formulario tiene que reflejarlo para
 * que el cliente no elija algo que le van a rechazar.
 */
describe('la entrega depende de la opcion elegida', () => {
  it('solo ofrece las entregas que admite la opcion', async () => {
    renderFormulario()

    await screen.findByText('Acuarela')

    // Acuarela viene seleccionada y solo admite fisico.
    expect(screen.getByLabelText(/Envio a domicilio/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/Descarga digital/)).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('radio', { name: /Digital/ }))

    expect(screen.getByLabelText(/Descarga digital/)).toBeInTheDocument()
  })
})

/**
 * N3/D26 — la cantidad son copias de la misma lamina. En entrega digital es
 * el mismo fichero, asi que no hay copias que pedir.
 */
describe('las copias', () => {
  it('deja pedir varias en entrega fisica', async () => {
    renderFormulario()

    expect(await screen.findByLabelText('Copias')).toBeEnabled()
  })

  it('las bloquea en entrega digital y explica por que', async () => {
    renderFormulario()

    await userEvent.click(await screen.findByRole('radio', { name: /Digital/ }))
    await userEvent.click(screen.getByLabelText(/Descarga digital/))

    expect(screen.getByLabelText('Copias')).toBeDisabled()
    expect(screen.getByText(/un unico archivo/i)).toBeInTheDocument()
  })

  it('vuelve a una copia al pasar a digital', async () => {
    renderFormulario()

    const copias = await screen.findByLabelText('Copias')
    await userEvent.clear(copias)
    await userEvent.type(copias, '4')

    await userEvent.click(screen.getByRole('radio', { name: /Digital/ }))
    await userEvent.click(screen.getByLabelText(/Descarga digital/))

    expect(screen.getByLabelText('Copias')).toHaveValue(1)
  })
})

/** N9 — la foto es el material de partida, no un adjunto opcional. */
describe('la foto de referencia', () => {
  it('no deja encargar sin ella cuando el producto la exige', async () => {
    renderFormulario()

    await userEvent.click(await screen.findByRole('button', { name: 'Anadir al carrito' }))

    expect(await screen.findByText(/se dibuja a partir de tu foto/i)).toBeInTheDocument()
    expect(anadir.llamadas).toHaveLength(0)
  })

  it('ni siquiera la pide cuando el producto no la necesita', async () => {
    producto.mockResolvedValue(
      unDibujo({ requires_reference_image: false, requires_notes: false }),
    )
    anadir.mockResolvedValue()

    renderFormulario()

    await userEvent.click(await screen.findByRole('button', { name: 'Anadir al carrito' }))

    await waitFor(() => expect(anadir.llamadas).toHaveLength(1))
    expect(loEnviadoAlCarrito().reference_media_id).toBeNull()
  })

  it('la sube en cuanto se elige y enseña la miniatura', async () => {
    subir.mockResolvedValue({
      id: 99,
      original_name: 'boda.jpg',
      mime_type: 'image/jpeg',
      size_bytes: 1000,
      url: 'http://localhost/storage/referencias/x.jpg',
    })

    renderFormulario()

    const fichero = new File(['bytes'], 'boda.jpg', { type: 'image/jpeg' })
    await userEvent.upload(await screen.findByLabelText('Tu foto'), fichero)

    expect(await screen.findByText('boda.jpg')).toBeInTheDocument()
    expect(screen.getByAltText(/Vista previa/)).toBeInTheDocument()
    // Solo el primer argumento: TanStack Query anade su propio contexto.
    expect(subir.mock.calls[0][0]).toBe(fichero)
  })
})

/**
 * SEC-006 — la regresion por el lado del cliente. El cuerpo dice *que* se
 * encarga; ningun importe sale de aqui.
 */
describe('lo que se manda al servidor', () => {
  it('no incluye ningun precio', async () => {
    subir.mockResolvedValue({
      id: 99,
      original_name: 'boda.jpg',
      mime_type: 'image/jpeg',
      size_bytes: 1000,
    })
    anadir.mockResolvedValue()

    renderFormulario()

    await userEvent.upload(
      await screen.findByLabelText('Tu foto'),
      new File(['bytes'], 'boda.jpg', { type: 'image/jpeg' }),
    )
    await screen.findByText('boda.jpg')

    await userEvent.type(screen.getByLabelText('Como lo quieres'), 'En blanco y negro.')
    await userEvent.click(screen.getByRole('button', { name: 'Anadir al carrito' }))

    await waitFor(() => expect(anadir.llamadas).toHaveLength(1))

    const enviado = loEnviadoAlCarrito()

    expect(enviado).toEqual({
      product_id: 7,
      variant_id: 11,
      shipping_method_id: 1,
      quantity: 1,
      customer_notes: 'En blanco y negro.',
      reference_media_id: 99,
    })

    // Ni price, ni unit_price, ni line_total, ni total.
    expect(Object.keys(enviado).join(' ')).not.toMatch(/price|total/)
  })

  it('lleva al carrito cuando el encargo entra', async () => {
    producto.mockResolvedValue(unDibujo({ requires_reference_image: false }))
    anadir.mockResolvedValue()

    renderFormulario()

    await userEvent.click(await screen.findByRole('button', { name: 'Anadir al carrito' }))

    expect(await screen.findByText('Tu carrito')).toBeInTheDocument()
  })

  /**
   * Las reglas de verdad viven en el servidor: lo del formulario es
   * comodidad. Si el backend rechaza, el mensaje se pinta en su campo.
   */
  it('pinta en su sitio el 422 que devuelve el servidor', async () => {
    producto.mockResolvedValue(unDibujo({ requires_reference_image: false }))
    anadir.mockRejectedValue(
      new ApiError(422, 'Los datos no son validos.', {
        quantity: ['No se pueden encargar mas de 10 copias.'],
      }),
    )

    renderFormulario()

    await userEvent.click(await screen.findByRole('button', { name: 'Anadir al carrito' }))

    expect(
      await screen.findByText('No se pueden encargar mas de 10 copias.'),
    ).toBeInTheDocument()
  })
})
