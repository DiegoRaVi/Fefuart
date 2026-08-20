import { screen, waitFor } from '@testing-library/react'
import { Route, Routes } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { obtenerSesion } from '@/features/auth/api'
import { renderConProviders, unaAdministradora, unUsuario } from '@/test/utils'

import { RutaDeAdmin, RutaDeInvitado, RutaProtegida } from './RutaProtegida'

vi.mock('@/features/auth/api', () => ({
  obtenerSesion: vi.fn(),
}))

const sesion = vi.mocked(obtenerSesion)

beforeEach(() => {
  vi.clearAllMocks()
  localStorage.clear()
})

function arbol() {
  return (
    <Routes>
      <Route path="/" element={<p>Portada</p>} />
      <Route path="/login" element={<p>Formulario de acceso</p>} />
      <Route element={<RutaProtegida />}>
        <Route path="/perfil" element={<p>Mi perfil</p>} />
      </Route>
      <Route element={<RutaDeAdmin />}>
        <Route path="/backoffice" element={<p>Panel de la artista</p>} />
      </Route>
      <Route element={<RutaDeInvitado />}>
        <Route path="/registro" element={<p>Crear cuenta</p>} />
      </Route>
    </Routes>
  )
}

describe('rutas que exigen sesión', () => {
  it('no decide nada mientras no sabe si hay sesión', async () => {
    // Consulta que nunca resuelve: es el estado del primer render.
    sesion.mockReturnValue(new Promise(() => {}))

    renderConProviders(arbol(), { ruta: '/perfil' })

    expect(screen.getByRole('status')).toBeInTheDocument()
    // Lo que no puede pasar es echar fuera a quien si tiene sesion solo
    // porque la comprobacion todavia esta en vuelo.
    expect(screen.queryByText('Formulario de acceso')).not.toBeInTheDocument()
  })

  it('manda al login a quien no tiene sesión', async () => {
    sesion.mockResolvedValue(null)

    renderConProviders(arbol(), { ruta: '/perfil' })

    expect(await screen.findByText('Formulario de acceso')).toBeInTheDocument()
    expect(screen.queryByText('Mi perfil')).not.toBeInTheDocument()
  })

  it('deja pasar a quien tiene sesión', async () => {
    sesion.mockResolvedValue(unUsuario())

    renderConProviders(arbol(), { ruta: '/perfil' })

    expect(await screen.findByText('Mi perfil')).toBeInTheDocument()
  })
})

describe('el backoffice', () => {
  it('deja entrar a la administradora', async () => {
    sesion.mockResolvedValue(unaAdministradora())

    renderConProviders(arbol(), { ruta: '/backoffice' })

    expect(await screen.findByText('Panel de la artista')).toBeInTheDocument()
  })

  it('devuelve a la portada a un cliente con sesión', async () => {
    sesion.mockResolvedValue(unUsuario())

    renderConProviders(arbol(), { ruta: '/backoffice' })

    expect(await screen.findByText('Portada')).toBeInTheDocument()
    expect(screen.queryByText('Panel de la artista')).not.toBeInTheDocument()
  })

  it('manda al login a quien no tiene sesión', async () => {
    sesion.mockResolvedValue(null)

    renderConProviders(arbol(), { ruta: '/backoffice' })

    expect(await screen.findByText('Formulario de acceso')).toBeInTheDocument()
  })

  /**
   * SEC-001, por el lado del navegador.
   *
   * En v1 el frontend decidia si eras administrador leyendo el payload del
   * JWT que el propio navegador guardaba en `localStorage`, asi que bastaba
   * con editarlo desde la consola. Aqui el rol solo puede venir de
   * `GET /api/auth/me`: da igual lo que haya en `localStorage`.
   */
  it('ignora un rol falsificado en localStorage', async () => {
    sesion.mockResolvedValue(unUsuario({ role: 'customer' }))

    localStorage.setItem('role', 'admin')
    localStorage.setItem('user', JSON.stringify({ role: 'admin' }))
    localStorage.setItem('token', 'lo-que-sea')

    renderConProviders(arbol(), { ruta: '/backoffice' })

    expect(await screen.findByText('Portada')).toBeInTheDocument()
    expect(screen.queryByText('Panel de la artista')).not.toBeInTheDocument()
  })
})

describe('rutas solo para invitados', () => {
  it('deja registrarse a quien no tiene sesión', async () => {
    sesion.mockResolvedValue(null)

    renderConProviders(arbol(), { ruta: '/registro' })

    expect(await screen.findByText('Crear cuenta')).toBeInTheDocument()
  })

  it('saca del registro a quien ya ha entrado', async () => {
    sesion.mockResolvedValue(unUsuario())

    renderConProviders(arbol(), { ruta: '/registro' })

    await waitFor(() => {
      expect(screen.getByText('Portada')).toBeInTheDocument()
    })
  })
})
