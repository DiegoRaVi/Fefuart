import { QueryClient } from '@tanstack/react-query'

import { ApiError } from '@/shared/api/errors'

/**
 * D12 — TanStack Query. Cache, estados de carga y error, reintentos e
 * invalidacion tras mutaciones sin escribirlo a mano en cada pantalla.
 */
export function crearQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        refetchOnWindowFocus: false,

        /**
         * Reintentar un 401 o un 403 no arregla nada y ademas empeora las
         * cosas: contra las rutas con throttle (SEC-007) tres reintentos
         * automaticos acercan al usuario al limite por un fallo que ya era
         * definitivo.
         */
        retry: (intentos, error) => {
          if (error instanceof ApiError && error.status >= 400 && error.status < 500) {
            return false
          }

          return intentos < 2
        },
      },
      mutations: {
        // Una mutacion no se reintenta sola: puede haber creado algo.
        retry: false,
      },
    },
  })
}
