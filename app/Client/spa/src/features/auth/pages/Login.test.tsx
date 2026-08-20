import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { iniciarSesion, obtenerSesion } from '@/features/auth/api'
import { ApiError } from '@/shared/api/errors'
import { renderConProviders, unUsuario } from '@/test/utils'

import { Login } from './Login'

vi.mock('@/features/auth/api', () => ({
  obtenerSesion: vi.fn(),
  iniciarSesion: vi.fn(),
}))

const sesion = vi.mocked(obtenerSesion)
const entrar = vi.mocked(iniciarSesion)

beforeEach(() => {
  vi.clearAllMocks()
  sesion.mockResolvedValue(null)
})

async function rellenar(email: string, password: string) {
  await userEvent.type(screen.getByLabelText('Correo'), email)
  await userEvent.type(screen.getByLabelText('Contraseña'), password)
  await userEvent.click(screen.getByRole('button', { name: 'Entrar' }))
}

describe('validacion antes de salir del navegador', () => {
  it('no llama a la API con los campos vacios', async () => {
    renderConProviders(<Login />)

    await userEvent.click(screen.getByRole('button', { name: 'Entrar' }))

    expect(await screen.findByText('Escribe tu correo.')).toBeInTheDocument()
    expect(screen.getByText('Escribe tu contraseña.')).toBeInTheDocument()
    expect(entrar).not.toHaveBeenCalled()
  })
})

describe('el servidor es quien decide', () => {
  it('envia las credenciales tal cual', async () => {
    entrar.mockResolvedValue(unUsuario())

    renderConProviders(<Login />)
    await rellenar('cliente@fefuart.test', 'contrasena-larga')

    // El primer argumento y solo ese: TanStack Query anade un segundo con su
    // propio contexto.
    expect(entrar.mock.calls[0][0]).toEqual({
      email: 'cliente@fefuart.test',
      password: 'contrasena-larga',
    })
  })

  /**
   * El 422 de Laravel llega como `errors.email`, tambien cuando la
   * contrasena es la que falla: el login no puede servir de oraculo de
   * cuentas, asi que el backend no dice cual de los dos campos es.
   */
  it('pinta el 422 en el campo que dice el backend', async () => {
    entrar.mockRejectedValue(
      new ApiError(422, 'Los datos no son validos.', {
        email: ['Las credenciales no son correctas.'],
      }),
    )

    renderConProviders(<Login />)
    await rellenar('cliente@fefuart.test', 'no-es-esta')

    expect(
      await screen.findByText('Las credenciales no son correctas.'),
    ).toBeInTheDocument()
    expect(screen.getByLabelText('Correo')).toHaveAttribute('aria-invalid', 'true')
  })

  /**
   * SEC-007 — el limitador existe y el usuario tiene que enterarse de que ha
   * saltado, no ver un fallo generico.
   */
  it('ensena el mensaje del throttle', async () => {
    entrar.mockRejectedValue(new ApiError(429, 'Demasiados intentos. Espera un momento.'))

    renderConProviders(<Login />)
    await rellenar('cliente@fefuart.test', 'no-es-esta')

    expect(await screen.findByRole('alert')).toHaveTextContent('Demasiados intentos')
  })

  it('avisa cuando no hay conexion en vez de culpar al servidor', async () => {
    entrar.mockRejectedValue(
      new ApiError(0, 'No hemos podido conectar. Comprueba tu conexion.'),
    )

    renderConProviders(<Login />)
    await rellenar('cliente@fefuart.test', 'contrasena-larga')

    expect(await screen.findByRole('alert')).toHaveTextContent('conexion')
  })
})

describe('la sesión no toca el almacenamiento del navegador', () => {
  /**
   * SEC-005 / SEC-011 — en v1 el JWT acababa en localStorage al entrar.
   */
  it('no guarda nada tras un login correcto', async () => {
    entrar.mockResolvedValue(unUsuario())

    renderConProviders(<Login />)
    await rellenar('cliente@fefuart.test', 'contrasena-larga')

    expect(localStorage.length).toBe(0)
    expect(sessionStorage.length).toBe(0)
  })
})
