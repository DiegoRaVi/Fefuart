import { screen } from '@testing-library/react'
import { beforeEach, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import { api } from '@/shared/api/client'
import { renderConProviders } from '@/test/utils'

import { Portada } from './Portada'

vi.mock('@/features/auth/api', () => ({ obtenerSesion: vi.fn() }))
vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(obtenerSesion).mockResolvedValue(null)
  vi.mocked(api.get).mockResolvedValue({ data: { data: [] } } as never)
})

/**
 * Los cuatro servicios eran `<li>` sin enlace, asi que la pantalla de entrada
 * no llevaba a ninguna parte salvo por el menu superior. Lo destapo la
 * auditoria de UX del 2026-08-20: era el arreglo mas barato de todo el
 * informe y bloqueaba el unico camino hacia adelante de la portada.
 */
it('lleva a cada servicio desde su tarjeta', async () => {
  renderConProviders(<Portada />)

  const destinos = [
    ['Live Art en tu boda', '/live-art'],
    ['Dibujo por encargo', '/encargos/dibujo-por-encargo'],
    ['Letras infantiles', '/encargos/letras-infantiles'],
    ['Tu ramo, en lámina', '/encargos/ramos-dibujados'],
  ]

  for (const [nombre, destino] of destinos) {
    expect(await screen.findByRole('link', { name: new RegExp(nombre) })).toHaveAttribute(
      'href',
      destino,
    )
  }
})

/** El titular dice que hace, no como se llama: la marca ya esta en la barra. */
it('abre con la propuesta y no con el nombre', async () => {
  renderConProviders(<Portada />)

  const titulo = await screen.findByRole('heading', { level: 1 })

  expect(titulo.textContent).not.toBe('Fefuart')
  expect(titulo.textContent).toMatch(/dibujo|dibuja/i)
})
