import { screen } from '@testing-library/react'
import { beforeEach, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import { api } from '@/shared/api/client'
import { renderConProviders, unUsuario } from '@/test/utils'

import { EnlaceDeAvisos } from './EnlaceDeAvisos'

vi.mock('@/features/auth/api', () => ({ obtenerSesion: vi.fn() }))
vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const get = vi.mocked(api.get)

function servirContador(noLeidos: number) {
  get.mockResolvedValue({
    data: {
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0, no_leidos: noLeidos },
    },
  } as never)
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(obtenerSesion).mockResolvedValue(unUsuario())
})

it('ensena cuantos avisos hay sin leer', async () => {
  servirContador(3)

  renderConProviders(<EnlaceDeAvisos />)

  expect(await screen.findByRole('link', { name: /avisos \(3\)/i })).toBeInTheDocument()
})

/** Con todo leido no se pinta el numero: un «(0)» solo es ruido. */
it('no ensena el número si no hay nada sin leer', async () => {
  servirContador(0)

  renderConProviders(<EnlaceDeAvisos />)

  const enlace = await screen.findByRole('link', { name: 'Avisos' })

  expect(enlace).toBeInTheDocument()
  expect(enlace).toHaveAttribute('href', '/avisos')
})

/**
 * Sin sesion no se piden avisos: el endpoint responde 401 y pedirlo igual
 * llenaria la consola de errores en cada pagina publica.
 */
it('no pide nada sin sesión', async () => {
  vi.mocked(obtenerSesion).mockResolvedValue(null)
  servirContador(3)

  renderConProviders(<EnlaceDeAvisos />)

  await screen.findByRole('link', { name: 'Avisos' })

  expect(get).not.toHaveBeenCalled()
})
