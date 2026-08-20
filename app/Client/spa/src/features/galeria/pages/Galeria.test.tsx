import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import { api } from '@/shared/api/client'
import { renderConProviders } from '@/test/utils'

import { Galeria } from './Galeria'

vi.mock('@/features/auth/api', () => ({ obtenerSesion: vi.fn() }))
vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const get = vi.mocked(api.get)

function unaPieza(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    title: 'Boda en Toledo',
    category: 'live-art',
    description: null,
    is_published: true,
    sort_order: 1,
    image: { id: 1, original_name: 'a.jpg', mime_type: 'image/jpeg', size_bytes: 900, url: '/g.jpg' },
    thumbnail: { id: 2, original_name: 'a.jpg', mime_type: 'image/jpeg', size_bytes: 90, url: '/m.jpg' },
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
  // La galeria es publica: se pinta igual sin sesion.
  vi.mocked(obtenerSesion).mockResolvedValue(null)
  get.mockResolvedValue({ data: { data: [unaPieza()] } } as never)
})

it('pinta la miniatura en la rejilla, no la imagen grande', async () => {
  renderConProviders(<Galeria />, { ruta: '/galeria' })

  const imagen = await screen.findByRole('img', { name: 'Boda en Toledo' })

  // Lo que justifica generar dos derivadas: entrar aqui no puede descargar
  // las piezas a tamano completo.
  expect(imagen).toHaveAttribute('src', '/m.jpg')
  expect(imagen).toHaveAttribute('loading', 'lazy')
})

it('avisa cuando la galeria esta vacía', async () => {
  get.mockResolvedValue({ data: { data: [] } } as never)

  renderConProviders(<Galeria />, { ruta: '/galeria' })

  expect(await screen.findByText(/Todavía no hay nada por aquí/)).toBeInTheDocument()
})

it('pide al servidor la categoria que se elige', async () => {
  renderConProviders(<Galeria />, { ruta: '/galeria' })

  await screen.findByRole('img', { name: 'Boda en Toledo' })
  await userEvent.click(screen.getByRole('button', { name: 'Papeleria' }))

  expect(get).toHaveBeenLastCalledWith('/gallery', { params: { category: 'papeleria' } })
})

/** El filtro activo tiene que anunciarse, no solo pintarse distinto. */
it('marca el filtro activo para quien no ve el color', async () => {
  renderConProviders(<Galeria />, { ruta: '/galeria' })

  await screen.findByRole('img', { name: 'Boda en Toledo' })

  expect(screen.getByRole('button', { name: 'Todo' })).toHaveAttribute('aria-pressed', 'true')

  await userEvent.click(screen.getByRole('button', { name: 'Ramos' }))

  expect(screen.getByRole('button', { name: 'Ramos' })).toHaveAttribute('aria-pressed', 'true')
  expect(screen.getByRole('button', { name: 'Todo' })).toHaveAttribute('aria-pressed', 'false')
})
