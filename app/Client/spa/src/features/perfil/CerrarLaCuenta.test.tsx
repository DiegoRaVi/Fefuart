import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import { api } from '@/shared/api/client'
import { renderConProviders, unUsuario } from '@/test/utils'

import { CerrarLaCuenta } from './CerrarLaCuenta'

vi.mock('@/features/auth/api', () => ({ obtenerSesion: vi.fn() }))
vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const post = vi.mocked(api.post)
const del = vi.mocked(api.delete)

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(obtenerSesion).mockResolvedValue(unUsuario())
  // `alSalir` recarga la pagina; en jsdom hay que sustituirlo.
  vi.stubGlobal('location', { assign: vi.fn() })
})

/**
 * D21 y D22 — la diferencia entre las dos tiene que estar escrita, no
 * implicita. Mucha gente que pide «borrar la cuenta» quiere dejar de recibir
 * correos, no perder el historial de sus encargos.
 */
it('explica que aparcar no pierde nada y suprimir si', async () => {
  renderConProviders(<CerrarLaCuenta />)

  expect(screen.getByText(/no se pierde nada/i)).toBeInTheDocument()
  expect(screen.getByText(/No se puede deshacer/)).toBeInTheDocument()
  expect(screen.getByText(/Tus pedidos se conservan/)).toBeInTheDocument()
})

it('aparca la cuenta sin pedir nada mas', async () => {
  post.mockResolvedValue({ data: null } as never)

  renderConProviders(<CerrarLaCuenta />)

  await userEvent.click(screen.getByRole('button', { name: 'Aparcar mi cuenta' }))

  expect(post).toHaveBeenCalledWith('/profile/deactivate')
})

/**
 * Lo que impide el accidente: pulsar «Suprimir» no suprime. Abre la
 * confirmacion, y la confirmacion pide la contrasena.
 */
it('no suprime con un solo clic', async () => {
  renderConProviders(<CerrarLaCuenta />)

  await userEvent.click(screen.getByRole('button', { name: 'Suprimir mi cuenta' }))

  expect(del).not.toHaveBeenCalled()
  expect(screen.getByLabelText('Tu contraseña')).toBeInTheDocument()
})

it('suprime con la contraseña', async () => {
  del.mockResolvedValue({ data: null } as never)

  renderConProviders(<CerrarLaCuenta />)

  await userEvent.click(screen.getByRole('button', { name: 'Suprimir mi cuenta' }))
  await userEvent.type(screen.getByLabelText('Tu contraseña'), 'unaclavelarga')
  await userEvent.click(
    screen.getByRole('button', { name: 'Suprimir mi cuenta para siempre' }),
  )

  expect(del).toHaveBeenCalledWith('/profile', { data: { password: 'unaclavelarga' } })
})
