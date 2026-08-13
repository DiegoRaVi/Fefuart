import { QueryClientProvider } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'

import { crearQueryClient } from './queryClient'

export function QueryProvider({ children }: { children: ReactNode }) {
  // En estado, no en modulo: dos tests o dos montajes no deben compartir
  // cache.
  const [client] = useState(crearQueryClient)

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}
