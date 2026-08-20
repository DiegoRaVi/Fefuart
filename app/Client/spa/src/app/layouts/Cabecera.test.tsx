import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { cerrarSesion, obtenerSesion } from '@/features/auth/api'
import { renderConProviders, unaAdministradora, unUsuario } from '@/test/utils'

import { Cabecera } from './Cabecera'

vi.mock('@/features/auth/api', () => ({
  obtenerSesion: vi.fn(),
  cerrarSesion: vi.fn(),
}))

const sesion = vi.mocked(obtenerSesion)
const salir = vi.mocked(cerrarSesion)

beforeEach(() => {
  vi.clearAllMocks()
  localStorage.clear()
})

describe('sin sesión', () => {
  it('ofrece entrar y crear cuenta', async () => {
    sesion.mockResolvedValue(null)

    renderConProviders(<Cabecera />)

    expect(await screen.findByRole('link', { name: 'Entrar' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Crear cuenta' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /salir/i })).not.toBeInTheDocument()
  })

  it('no ensena el backoffice', async () => {
    sesion.mockResolvedValue(null)

    renderConProviders(<Cabecera />)

    await screen.findByRole('link', { name: 'Entrar' })
    expect(screen.queryByRole('link', { name: 'Backoffice' })).not.toBeInTheDocument()
  })
})

describe('con sesión de cliente', () => {
  it('ensena el nombre y la salida', async () => {
    sesion.mockResolvedValue(unUsuario({ name: 'Marta' }))

    renderConProviders(<Cabecera />)

    expect(await screen.findByRole('link', { name: 'Marta' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Salir' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Entrar' })).not.toBeInTheDocument()
  })

  /**
   * N20 — el backoffice es de la administradora. En v1 el enlace estaba en
   * el HTML de todos (`<li id="admin">`), oculto con CSS y mostrado por
   * JavaScript segun el rol que el navegador leia del JWT de localStorage.
   */
  it('no ensena el backoffice a un cliente', async () => {
    sesion.mockResolvedValue(unUsuario())

    renderConProviders(<Cabecera />)

    await screen.findByRole('button', { name: 'Salir' })
    expect(screen.queryByRole('link', { name: 'Backoffice' })).not.toBeInTheDocument()
  })

  it('sigue sin ensenarlo aunque localStorage diga que es admin', async () => {
    sesion.mockResolvedValue(unUsuario({ role: 'customer' }))
    localStorage.setItem('role', 'admin')

    renderConProviders(<Cabecera />)

    await screen.findByRole('button', { name: 'Salir' })
    expect(screen.queryByRole('link', { name: 'Backoffice' })).not.toBeInTheDocument()
  })

  it('llama a la API al salir', async () => {
    sesion.mockResolvedValue(unUsuario())
    salir.mockResolvedValue(undefined)

    renderConProviders(<Cabecera />)

    await userEvent.click(await screen.findByRole('button', { name: 'Salir' }))

    expect(salir).toHaveBeenCalledOnce()
  })
})

describe('con sesión de administradora', () => {
  it('ensena el backoffice', async () => {
    sesion.mockResolvedValue(unaAdministradora())

    renderConProviders(<Cabecera />)

    expect(await screen.findByRole('link', { name: 'Backoffice' })).toBeInTheDocument()
  })
})

/**
 * La auditoria de UX del 2026-08-20 encontro que a 390 px la barra se salia
 * de la pantalla y la pagina scrolleaba en horizontal — con sesion iniciada,
 * la mitad de la navegacion quedaba fuera del viewport. Eran ocho enlaces en
 * `flex-wrap` sin menu compacto.
 *
 * Los tests no miden pixeles, asi que lo que se fija aqui es la estructura
 * que lo arregla: existe un boton de menu, la navegacion esta plegada al
 * cargar, y al abrirla se llega a todo.
 */
describe('la navegacion en pantalla estrecha', () => {
  it('ofrece un boton de menu', async () => {
    sesion.mockResolvedValue(null)

    renderConProviders(<Cabecera />)

    expect(await screen.findByRole('button', { name: /menu/i })).toBeInTheDocument()
  })

  it('empieza plegada y anuncia que lo esta', async () => {
    sesion.mockResolvedValue(null)

    renderConProviders(<Cabecera />)

    const boton = await screen.findByRole('button', { name: /menu/i })

    expect(boton).toHaveAttribute('aria-expanded', 'false')
  })

  it('despliega la navegacion al pulsarlo', async () => {
    sesion.mockResolvedValue(unUsuario({ name: 'Marta' }))

    renderConProviders(<Cabecera />)

    const boton = await screen.findByRole('button', { name: /menu/i })
    await userEvent.click(boton)

    expect(boton).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByRole('link', { name: 'Galeria' })).toBeVisible()
  })

  /** Navegar cierra el menu: dejarlo abierto tapa la pagina de destino. */
  it('se cierra al elegir un destino', async () => {
    sesion.mockResolvedValue(null)

    renderConProviders(<Cabecera />)

    const boton = await screen.findByRole('button', { name: /menu/i })
    await userEvent.click(boton)
    await userEvent.click(screen.getByRole('link', { name: 'Galeria' }))

    expect(boton).toHaveAttribute('aria-expanded', 'false')
  })
})

/**
 * La administradora no compra en su propia tienda, y esos dos enlaces eran
 * ademas cuatro elementos de mas en la barra que se desbordaba.
 */
it('no ensena carrito ni pedidos de cliente a la administradora', async () => {
  sesion.mockResolvedValue(unaAdministradora())

  renderConProviders(<Cabecera />)

  await screen.findByRole('link', { name: 'Backoffice' })

  expect(screen.queryByRole('link', { name: /^Carrito/ })).not.toBeInTheDocument()
  expect(screen.queryByRole('link', { name: 'Pedidos' })).not.toBeInTheDocument()
})
