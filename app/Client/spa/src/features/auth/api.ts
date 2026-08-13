import { api } from '@/shared/api/client'
import { ApiError } from '@/shared/api/errors'
import type { Credentials, Envelope, RegistrationData, User } from '@/shared/api/types'

/**
 * La sesion se resuelve preguntando al servidor.
 *
 * Nunca contra `localStorage`: en v1 el frontend decidia si eras admin
 * leyendo el payload del JWT que el propio navegador guardaba, de modo que
 * el rol lo elegia el cliente. Aqui la unica fuente es la cookie HttpOnly,
 * que JavaScript no puede leer, y lo que el servidor responda.
 *
 * Un 401 no es un fallo: es la respuesta correcta a «no hay sesion».
 */
export async function obtenerSesion(): Promise<User | null> {
  try {
    const { data } = await api.get<Envelope<User>>('/auth/me')

    return data.data
  } catch (error) {
    if (error instanceof ApiError && error.isUnauthenticated) {
      return null
    }

    throw error
  }
}

export async function iniciarSesion(credenciales: Credentials): Promise<User> {
  const { data } = await api.post<Envelope<User>>('/auth/login', credenciales)

  return data.data
}

export async function registrarse(datos: RegistrationData): Promise<User> {
  const { data } = await api.post<Envelope<User>>('/auth/register', datos)

  return data.data
}

export async function cerrarSesion(): Promise<void> {
  await api.post('/auth/logout')
}

export async function pedirEnlaceDeRecuperacion(email: string): Promise<string> {
  const { data } = await api.post<{ message: string }>('/auth/forgot-password', { email })

  return data.message
}

export interface ResetPasswordData {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export async function restablecerContrasena(datos: ResetPasswordData): Promise<string> {
  const { data } = await api.post<{ message: string }>('/auth/reset-password', datos)

  return data.message
}

export async function reenviarVerificacion(): Promise<void> {
  await api.post('/auth/email/verification-notification')
}
