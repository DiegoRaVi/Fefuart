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

/**
 * D21 — aparcar la cuenta. Reversible: los datos quedan intactos y se
 * recupera escribiendo.
 */
export async function desactivarCuenta(): Promise<void> {
  await api.post('/profile/deactivate')
}

/**
 * D22 — el derecho de supresion del art. 17, por anonimizacion.
 *
 * Pide la contrasena porque es irreversible y se lleva por delante las fotos
 * que se subieron. El servidor la comprueba contra la sesion, no contra el
 * cuerpo.
 */
export async function suprimirCuenta(password: string): Promise<void> {
  await api.delete('/profile', { data: { password } })
}
