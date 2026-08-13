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

describe('sin sesion', () => {
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

describe('con sesion de cliente', () => {
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

describe('con sesion de administradora', () => {
  it('ensena el backoffice', async () => {
    sesion.mockResolvedValue(unaAdministradora())

    renderConProviders(<Cabecera />)

    expect(await screen.findByRole('link', { name: 'Backoffice' })).toBeInTheDocument()
  })
})
