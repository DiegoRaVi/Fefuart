import { createContext, useContext } from 'react'

import type { User } from '@/shared/api/types'

export interface Sesion {
  usuario: User | null
  /** Todavia no sabemos si hay sesion: la primera consulta esta en vuelo. */
  cargando: boolean
  autenticado: boolean
  /** N20 — dos roles. Lo dice el servidor, no el cliente. */
  esAdmin: boolean
  /** El correo esta verificado. Lo necesita el aviso de N19. */
  verificado: boolean
}

export const SesionContext = createContext<Sesion | null>(null)

export function useSesion(): Sesion {
  const sesion = useContext(SesionContext)

  if (!sesion) {
    throw new Error('useSesion tiene que usarse dentro de <AuthProvider>.')
  }

  return sesion
}

export const CLAVE_SESION = ['auth', 'me'] as const
