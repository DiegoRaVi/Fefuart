import { api } from '@/shared/api/client'
import type { Envelope, User } from '@/shared/api/types'

export interface DatosDePerfil {
  name: string
  email: string
}

export async function actualizarPerfil(datos: DatosDePerfil): Promise<User> {
  const { data } = await api.patch<Envelope<User>>('/profile', datos)

  return data.data
}

export interface CambioDeContrasena {
  current_password: string
  password: string
  password_confirmation: string
}

export async function cambiarContrasena(datos: CambioDeContrasena): Promise<void> {
  await api.put('/profile/password', datos)
}
