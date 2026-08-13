import { useQuery } from '@tanstack/react-query'
import { useMemo, type ReactNode } from 'react'

import { obtenerSesion } from '@/features/auth/api'
import { CLAVE_SESION, SesionContext, type Sesion } from '@/features/auth/sesion'

/**
 * D2 — la sesion vive en una cookie HttpOnly que JavaScript no puede leer,
 * asi que la unica forma de saber quien eres es preguntarselo al servidor.
 *
 * Eso no es una limitacion, es el arreglo: en v1 el frontend decidia si eras
 * administrador leyendo el JWT que el propio navegador guardaba en
 * `localStorage`, de modo que el rol lo elegia el cliente. Aqui `esAdmin`
 * sale de `GET /api/auth/me` y de ningun otro sitio.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const consulta = useQuery({
    queryKey: CLAVE_SESION,
    queryFn: obtenerSesion,
    // La sesion se comprueba al montar y tras cada mutacion de auth; no hace
    // falta que caduque sola.
    staleTime: Infinity,
  })

  const sesion = useMemo<Sesion>(() => {
    const usuario = consulta.data ?? null

    return {
      usuario,
      cargando: consulta.isPending,
      autenticado: usuario !== null,
      esAdmin: usuario?.role === 'admin',
      verificado: usuario?.email_verified_at !== null && usuario !== null,
    }
  }, [consulta.data, consulta.isPending])

  return <SesionContext.Provider value={sesion}>{children}</SesionContext.Provider>
}
