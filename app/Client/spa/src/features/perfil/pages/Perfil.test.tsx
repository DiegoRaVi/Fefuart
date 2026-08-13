import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { obtenerSesion, reenviarVerificacion } from '@/features/auth/api'
import { actualizarPerfil, cambiarContrasena } from '@/features/perfil/api'
import { ApiError } from '@/shared/api/errors'
import { renderConProviders, unUsuario } from '@/test/utils'

import { Perfil } from './Perfil'

vi.mock('@/features/auth/api', () => ({
  obtenerSesion: vi.fn(),
  reenviarVerificacion: vi.fn(),
}))

vi.mock('@/features/perfil/api', () => ({
  actualizarPerfil: vi.fn(),
  cambiarContrasena: vi.fn(),
}))

const sesion = vi.mocked(obtenerSesion)
const reenviar = vi.mocked(reenviarVerificacion)
const guardar = vi.mocked(actualizarPerfil)
const cambiar = vi.mocked(cambiarContrasena)

beforeEach(() => {
  vi.clearAllMocks()
})

describe('los datos de la cuenta', () => {
  it('los rellena con lo que devuelve el servidor', async () => {
    sesion.mockResolvedValue(unUsuario({ name: 'Marta', email: 'marta@fefuart.test' }))

    renderConProviders(<Perfil />)

    expect(await screen.findByLabelText('Nombre')).toHaveValue('Marta')
    expect(screen.getByLabelText('Correo')).toHaveValue('marta@fefuart.test')
  })

  it('avisa de que cambiar el correo obliga a verificarlo', async () => {
    sesion.mockResolvedValue(unUsuario())

    renderConProviders(<Perfil />)

    expect(
      await screen.findByText(/tendras que verificar la direccion nueva/i),
    ).toBeInTheDocument()
  })

  it('pinta en el campo el 422 de un correo ya cogido', async () => {
    sesion.mockResolvedValue(unUsuario())
    guardar.mockRejectedValue(
      new ApiError(422, 'Los datos no son validos.', {
        email: ['Ese correo ya esta en uso.'],
      }),
    )

    renderConProviders(<Perfil />)

    await userEvent.click(await screen.findByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Ese correo ya esta en uso.')).toBeInTheDocument()
  })
})

describe('la verificacion del correo', () => {
  it('avisa cuando la direccion no esta verificada', async () => {
    sesion.mockResolvedValue(unUsuario({ email_verified_at: null }))

    renderConProviders(<Perfil />)

    expect(await screen.findByText(/todavia no esta verificado/i)).toBeInTheDocument()
  })

  it('no avisa cuando ya lo esta', async () => {
    sesion.mockResolvedValue(unUsuario())

    renderConProviders(<Perfil />)

    await screen.findByLabelText('Nombre')
    expect(screen.queryByText(/todavia no esta verificado/i)).not.toBeInTheDocument()
  })

  it('reenvia el correo cuando se pide', async () => {
    sesion.mockResolvedValue(unUsuario({ email_verified_at: null }))
    reenviar.mockResolvedValue(undefined)

    renderConProviders(<Perfil />)

    await userEvent.click(
      await screen.findByRole('button', { name: 'Reenviar el correo' }),
    )

    expect(reenviar).toHaveBeenCalledOnce()
    expect(await screen.findByText(/te hemos enviado un correo nuevo/i)).toBeInTheDocument()
  })

  /** El backend redirige aqui con ?verificado=1 tras marcar la direccion. */
  it('confirma la verificacion al volver del enlace del correo', async () => {
    sesion.mockResolvedValue(unUsuario())

    renderConProviders(<Perfil />, { ruta: '/perfil?verificado=1' })

    expect(await screen.findByText(/ha quedado verificado/i)).toBeInTheDocument()
  })
})

describe('el cambio de contrasena', () => {
  it('exige la actual', async () => {
    sesion.mockResolvedValue(unUsuario())

    renderConProviders(<Perfil />)

    await userEvent.click(
      await screen.findByRole('button', { name: 'Cambiar la contrasena' }),
    )

    expect(await screen.findByText('Escribe tu contrasena actual.')).toBeInTheDocument()
    expect(cambiar).not.toHaveBeenCalled()
  })

  it('pinta el 422 cuando la actual no es correcta', async () => {
    sesion.mockResolvedValue(unUsuario())
    cambiar.mockRejectedValue(
      new ApiError(422, 'Los datos no son validos.', {
        current_password: ['La contrasena actual no es correcta.'],
      }),
    )

    renderConProviders(<Perfil />)

    await userEvent.type(
      await screen.findByLabelText('Contrasena actual'),
      'la-que-no-es',
    )
    await userEvent.type(screen.getByLabelText('Contrasena nueva'), 'contrasena-larga')
    await userEvent.type(
      screen.getByLabelText('Repite la contrasena nueva'),
      'contrasena-larga',
    )
    await userEvent.click(screen.getByRole('button', { name: 'Cambiar la contrasena' }))

    expect(
      await screen.findByText('La contrasena actual no es correcta.'),
    ).toBeInTheDocument()
  })
})
