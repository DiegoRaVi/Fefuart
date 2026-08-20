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
    quoted_amount: null,
    deposit_amount: null,
    quote_expires_at: null,
    quote_expired: false,
    deposit_paid: false,
    can: { update: true, cancel: true, accept_quote: false, quote: false },
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
  await userEvent.type(await screen.findByLabelText('Qué evento es'), 'Boda de Marta y Luis')
  await userEvent.type(screen.getByLabelText('Fecha'), '2027-06-12')
  await userEvent.type(screen.getByLabelText('Dónde'), 'Finca El Olivar, Toledo')
}

/**
 * N13 — el precio siempre es a medida: la artista revisa la solicitud y
 * emite un presupuesto. En esta pantalla no puede haber ninguna tarifa.
 */
describe('el precio no se publica', () => {
  it('no enseña ningun importe', async () => {
    renderConProviders(<LiveArt />)

    /*
     * Se espera al formulario y no al titular: desde que la pantalla es
     * publica hay dos paneles con el mismo «¿Tengo tu fecha libre?» —el
     * formulario y la invitacion a crear cuenta— y ese texto aparece antes
     * de que se resuelva la sesion, asi que no distingue uno de otro.
     */
    await screen.findByLabelText('Qué evento es')

    expect(document.body.textContent).not.toMatch(/\d+,\d{2}\s*€/)
    expect(screen.getByText(/a medida/i)).toBeInTheDocument()
  })
})

/**
 * N18 no se cae al abrir la pantalla: lo que cambia es donde se pide la
 * cuenta. Publica desde el 2026-08-20, porque detras del login quien llegaba
 * de Instagram se encontraba un formulario de acceso en vez de la pantalla
 * que vende.
 */
describe('sin cuenta', () => {
  beforeEach(() => {
    vi.mocked(obtenerSesion).mockResolvedValue(null)
  })

  it('enseña lo que vende y pide la cuenta al final', async () => {
    renderConProviders(<LiveArt />)

    expect(
      await screen.findByRole('link', { name: 'Crear cuenta y pedir presupuesto' }),
    ).toBeInTheDocument()

    // La propuesta se lee entera: no hay muro delante.
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument()
    expect(screen.queryByLabelText('Qué evento es')).not.toBeInTheDocument()
  })

  /** El endpoint responde 401: pedirlo igual solo llena la consola. */
  it('no pide las solicitudes', async () => {
    renderConProviders(<LiveArt />)

    await screen.findByRole('link', { name: 'Crear cuenta y pedir presupuesto' })

    expect(get.mock.calls.map(([ruta]) => ruta)).not.toContain('/events')
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

  it('los manda como número y no como texto', async () => {
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
    expect(screen.getByText('Dinos dónde es.')).toBeInTheDocument()
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
      unEvento({ id: 1, title: 'Cancelable', can: { update: true, cancel: true, accept_quote: false, quote: false } }),
      unEvento({ id: 2, title: 'Ya no', can: { update: false, cancel: false, accept_quote: false, quote: false } }),
    ])

    renderConProviders(<LiveArt />)

    await screen.findByText('Cancelable')

    expect(screen.getAllByRole('button', { name: 'Cancelar' })).toHaveLength(1)
  })
})

/**
 * D6, N15 — aceptar el presupuesto es reservar, y reservar es pagar.
 *
 * El importe se pinta pero no se manda: el servidor cobra la señal que
 * guardo al presupuestar (SEC-006).
 */
describe('el presupuesto', () => {
  const irA = vi.fn()

  beforeEach(() => {
    irA.mockClear()
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { ...window.location, assign: irA },
    })
  })

  function presupuestado(overrides: Partial<Evento> = {}) {
    return unEvento({
      status: 'quoted',
      quoted_amount: '1200.00',
      deposit_amount: '360.00',
      quote_expires_at: '2027-01-01T10:00:00+00:00',
      quote_expired: false,
      can: { update: false, cancel: true, accept_quote: true, quote: false },
      ...overrides,
    })
  }

  it('ensena el importe y la señal', async () => {
    servirEventos([presupuestado()])

    renderConProviders(<LiveArt />)

    expect(await screen.findByText(/1\.?200,00/)).toBeInTheDocument()
    expect(screen.getByText(/señal para reservar la fecha/i)).toBeInTheDocument()
    // La señal se repite en el boton a proposito: es lo que se va a cobrar.
    expect(
      screen.getByRole('button', { name: /aceptar y pagar la señal de 360,00/i }),
    ).toBeInTheDocument()
  })

  it('acepta sin mandar ningun importe', async () => {
    servirEventos([presupuestado()])
    post.mockResolvedValue({
      data: { url: 'https://checkout.stripe.com/c/pay/cs_test_senal', payment_id: 4 },
    } as never)

    renderConProviders(<LiveArt />)

    await userEvent.click(await screen.findByRole('button', { name: /aceptar y pagar/i }))

    await waitFor(() => expect(post).toHaveBeenCalled())

    expect(post.mock.calls[0][0]).toBe('/events/1/accept-quote')
    expect(post.mock.calls[0][1]).toBeUndefined()
    await waitFor(() =>
      expect(irA).toHaveBeenCalledWith('https://checkout.stripe.com/c/pay/cs_test_senal'),
    )
  })

  /** P1 — un presupuesto de hace meses no se acepta hoy a aquel precio. */
  it('no deja aceptar uno caducado', async () => {
    servirEventos([
      presupuestado({
        quote_expired: true,
        can: { update: false, cancel: true, accept_quote: true, quote: false },
      }),
    ])

    renderConProviders(<LiveArt />)

    expect(await screen.findByText(/ha caducado/i)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /aceptar y pagar/i })).not.toBeInTheDocument()
  })

  /** Quien puede aceptar lo dice el servidor, no el estado leido aqui. */
  it('no ofrece aceptar si el servidor no lo permite', async () => {
    servirEventos([
      presupuestado({ can: { update: false, cancel: true, accept_quote: false, quote: false } }),
    ])

    renderConProviders(<LiveArt />)

    await screen.findByText(/1\.?200,00/)

    expect(screen.queryByRole('button', { name: /aceptar y pagar/i })).not.toBeInTheDocument()
  })
})

/**
 * N21 — que la señal no se devuelve si cancela el cliente se dice **antes**
 * de pulsar, no despues. La fecha lleva reservada para el desde que la pago.
 */
it('avisa de que la señal no se devuelve antes de cancelar', async () => {
  servirEventos([
    unEvento({
      status: 'confirmed',
      quoted_amount: '1200.00',
      deposit_amount: '360.00',
      deposit_paid: true,
      can: { update: false, cancel: true, accept_quote: false, quote: false },
    }),
  ])

  renderConProviders(<LiveArt />)

  expect(await screen.findByText(/la señal no se devuelve/i)).toBeInTheDocument()
})

it('no lo avisa si todavía no hay señal pagada', async () => {
  servirEventos([unEvento({ status: 'requested' })])

  renderConProviders(<LiveArt />)

  await screen.findByRole('button', { name: /cancelar/i })

  expect(screen.queryByText(/la señal no se devuelve/i)).not.toBeInTheDocument()
})
