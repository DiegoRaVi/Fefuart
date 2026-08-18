import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import { api } from '@/shared/api/client'
import { renderConProviders, unUsuario } from '@/test/utils'

import { Avisos } from './Avisos'

vi.mock('@/features/auth/api', () => ({ obtenerSesion: vi.fn() }))
vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const get = vi.mocked(api.get)
const patch = vi.mocked(api.patch)

function unAviso(overrides: Record<string, unknown> = {}) {
  return {
    id: '9f1c1e5a-0000-4000-8000-000000000001',
    tipo: 'presupuesto_listo',
    titulo: 'Ya tienes el presupuesto de tu evento',
    cuerpo: 'Hemos preparado el presupuesto de «Boda de Marta» para el 12/09/2026: 1.200,00 €.',
    enlace: '/live-art#mias',
    leido: false,
    creado_en: '2026-08-19T10:00:00+00:00',
    ...overrides,
  }
}

function servir(avisos: ReturnType<typeof unAviso>[], noLeidos = avisos.length) {
  get.mockResolvedValue({
    data: {
      data: avisos,
      meta: { current_page: 1, last_page: 1, per_page: 15, total: avisos.length, no_leidos: noLeidos },
    },
  } as never)
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(obtenerSesion).mockResolvedValue(unUsuario())
})

it('enseña el titulo y el cuerpo de cada aviso', async () => {
  servir([unAviso()])

  renderConProviders(<Avisos />, { ruta: '/avisos' })

  expect(await screen.findByText('Ya tienes el presupuesto de tu evento')).toBeInTheDocument()
  expect(screen.getByText(/Hemos preparado el presupuesto/)).toBeInTheDocument()
})

it('avisa cuando no hay nada', async () => {
  servir([])

  renderConProviders(<Avisos />, { ruta: '/avisos' })

  expect(await screen.findByText(/no tienes avisos/i)).toBeInTheDocument()
})

/**
 * El enlace es una ruta relativa de la SPA, asi que tiene que navegar con
 * React Router y no recargar la pagina entera.
 */
it('enlaza al sitio que dice el aviso', async () => {
  servir([unAviso()])

  renderConProviders(<Avisos />, { ruta: '/avisos' })

  const enlace = await screen.findByRole('link', { name: /Ya tienes el presupuesto/ })

  expect(enlace).toHaveAttribute('href', '/live-art#mias')
})

describe('marcar como leido', () => {
  it('llama al endpoint del aviso', async () => {
    servir([unAviso()])
    patch.mockResolvedValue({ data: { data: unAviso({ leido: true }) } } as never)

    renderConProviders(<Avisos />, { ruta: '/avisos' })

    await userEvent.click(await screen.findByRole('button', { name: /marcar como leido/i }))

    await waitFor(() => {
      expect(patch).toHaveBeenCalledWith(
        '/notifications/9f1c1e5a-0000-4000-8000-000000000001/read',
      )
    })
  })

  /** Un aviso ya leido no ofrece el boton: no hay nada que marcar. */
  it('no ofrece el boton si ya estaba leido', async () => {
    servir([unAviso({ leido: true })], 0)

    renderConProviders(<Avisos />, { ruta: '/avisos' })

    await screen.findByText('Ya tienes el presupuesto de tu evento')

    expect(screen.queryByRole('button', { name: /marcar como leido/i })).not.toBeInTheDocument()
  })
})
