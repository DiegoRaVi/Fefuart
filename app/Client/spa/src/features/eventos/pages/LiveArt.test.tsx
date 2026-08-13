import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import { api } from '@/shared/api/client'
import { ApiError } from '@/shared/api/errors'
import { renderConProviders, unUsuario } from '@/test/utils'

import type { Evento } from '../api'
import { LiveArt } from './LiveArt'

vi.mock('@/features/auth/api', () => ({ obtenerSesion: vi.fn() }))
vi.mock('@/shared/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

const get = vi.mocked(api.get)
const post = vi.mocked(api.post)

function unEvento(overrides: Partial<Evento> = {}): Evento {
  return {
    id: 1,
    title: 'Boda de Marta y Luis',
    description: null,
    phone: '600123456',
    event_date: '2027-06-12',
    schedule: 'evening',
    location: 'Finca El Olivar, Toledo',
    guest_count: 120,
    duration_hours: 4,
    event_type: 'boda',
    status: 'requested',
    can: { update: true, cancel: true },
    created_at: '2026-08-13T10:00:00+00:00',
    ...overrides,
  }
}

function servirEventos(eventos: Evento[]) {
  get.mockImplementation(
    async () =>
      ({
        data: {
          data: eventos,
          meta: { current_page: 1, last_page: 1, per_page: 15, total: eventos.length },
        },
      }) as never,
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(obtenerSesion).mockResolvedValue(unUsuario())
  servirEventos([])
})

async function rellenarLoMinimo() {
  await userEvent.type(await screen.findByLabelText('Que evento es'), 'Boda de Marta y Luis')
  await userEvent.type(screen.getByLabelText('Fecha'), '2027-06-12')
  await userEvent.type(screen.getByLabelText('Donde'), 'Finca El Olivar, Toledo')
}

/**
 * N13 — el precio siempre es a medida: la artista revisa la solicitud y
 * emite un presupuesto. En esta pantalla no puede haber ninguna tarifa.
 */
describe('el precio no se publica', () => {
  it('no enseña ningun importe', async () => {
    renderConProviders(<LiveArt />)

    await screen.findByText('Cuentanos tu evento')

    expect(document.body.textContent).not.toMatch(/\d+,\d{2}\s*€/)
    expect(screen.getByText(/a medida/i)).toBeInTheDocument()
  })
})

/**
 * N14 — invitados y duracion son los que determinan la tarifa, y v1 no los
 * pedia. Sin ellos la artista no puede presupuestar.
 */
describe('los datos con los que se presupuesta', () => {
  it('pide invitados, horas y tipo de evento', async () => {
    renderConProviders(<LiveArt />)

    expect(await screen.findByLabelText('Invitados')).toBeInTheDocument()
    expect(screen.getByLabelText('Horas')).toBeInTheDocument()
    expect(screen.getByLabelText('Tipo')).toBeInTheDocument()
  })

  it('los manda como numero y no como texto', async () => {
    post.mockResolvedValue({ data: { data: unEvento() } } as never)

    renderConProviders(<LiveArt />)

    await rellenarLoMinimo()
    await userEvent.type(screen.getByLabelText('Invitados'), '120')
    await userEvent.type(screen.getByLabelText('Horas'), '4')
    await userEvent.click(screen.getByRole('button', { name: 'Pedir presupuesto' }))

    await waitFor(() => expect(post).toHaveBeenCalled())

    expect(post.mock.calls[0][1]).toMatchObject({ guest_count: 120, duration_hours: 4 })
  })

  /** Quien todavia no lo sepa no deberia quedarse sin poder preguntar. */
  it('deja enviarlos vacios, como null', async () => {
    post.mockResolvedValue({ data: { data: unEvento() } } as never)

    renderConProviders(<LiveArt />)

    await rellenarLoMinimo()
    await userEvent.click(screen.getByRole('button', { name: 'Pedir presupuesto' }))

    await waitFor(() => expect(post).toHaveBeenCalled())

    const enviado = post.mock.calls[0][1] as Record<string, unknown>

    expect(enviado.guest_count).toBeNull()
    expect(enviado.duration_hours).toBeNull()
  })
})

/**
 * SEC-010 — el cuerpo no lleva `status`, igual que el backend no lo acepta.
 * En v1 el propietario podia pasar su evento a `confirmed`.
 */
describe('lo que se manda', () => {
  it('no incluye el estado', async () => {
    post.mockResolvedValue({ data: { data: unEvento() } } as never)

    renderConProviders(<LiveArt />)

    await rellenarLoMinimo()
    await userEvent.click(screen.getByRole('button', { name: 'Pedir presupuesto' }))

    await waitFor(() => expect(post).toHaveBeenCalled())

    expect(post.mock.calls[0][1]).not.toHaveProperty('status')
  })

  it('no llama al servidor sin lo imprescindible', async () => {
    renderConProviders(<LiveArt />)

    await userEvent.click(await screen.findByRole('button', { name: 'Pedir presupuesto' }))

    expect(await screen.findByText('Ponle un nombre al evento.')).toBeInTheDocument()
    expect(screen.getByText('Dinos donde es.')).toBeInTheDocument()
    expect(post).not.toHaveBeenCalled()
  })

  it('pinta en su campo el 422 del servidor', async () => {
    post.mockRejectedValue(
      new ApiError(422, 'Los datos no son validos.', {
        event_date: ['La fecha del evento debe ser posterior o igual a hoy.'],
      }),
    )

    renderConProviders(<LiveArt />)

    await rellenarLoMinimo()
    await userEvent.click(screen.getByRole('button', { name: 'Pedir presupuesto' }))

    expect(
      await screen.findByText(/debe ser posterior o igual a hoy/i),
    ).toBeInTheDocument()
  })

  it('confirma que la solicitud ha salido', async () => {
    post.mockResolvedValue({ data: { data: unEvento() } } as never)

    renderConProviders(<LiveArt />)

    await rellenarLoMinimo()
    await userEvent.click(screen.getByRole('button', { name: 'Pedir presupuesto' }))

    expect(await screen.findByText(/Solicitud enviada/i)).toBeInTheDocument()
  })
})

describe('las solicitudes propias', () => {
  it('las lista con su estado en castellano', async () => {
    servirEventos([unEvento({ status: 'quoted' })])

    renderConProviders(<LiveArt />)

    expect(await screen.findByText('Boda de Marta y Luis')).toBeInTheDocument()
    expect(screen.getByText('Presupuesto enviado')).toBeInTheDocument()
  })

  /**
   * Quien puede cancelar lo dice el servidor en `can`, no lo deduce esta
   * pantalla del estado: la regla vive en EventPolicy y duplicarla aqui es
   * pedir que las dos se separen.
   */
  it('ofrece cancelar solo cuando el servidor lo permite', async () => {
    servirEventos([
      unEvento({ id: 1, title: 'Cancelable', can: { update: true, cancel: true } }),
      unEvento({ id: 2, title: 'Ya no', can: { update: false, cancel: false } }),
    ])

    renderConProviders(<LiveArt />)

    await screen.findByText('Cancelable')

    expect(screen.getAllByRole('button', { name: 'Cancelar' })).toHaveLength(1)
  })
})
