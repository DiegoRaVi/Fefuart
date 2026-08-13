import { QueryClientProvider } from '@tanstack/react-query'
import { render, type RenderResult } from '@testing-library/react'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router'

import { AuthProvider } from '@/app/providers/AuthProvider'
import { crearQueryClient } from '@/app/providers/queryClient'
import type { User } from '@/shared/api/types'

export function unUsuario(overrides: Partial<User> = {}): User {
  return {
    id: 1,
    name: 'Cliente de prueba',
    email: 'cliente@fefuart.test',
    role: 'customer',
    email_verified_at: '2026-08-13T10:00:00+00:00',
    ...overrides,
  }
}

export function unaAdministradora(overrides: Partial<User> = {}): User {
  return unUsuario({
    id: 2,
    name: 'Felicitas Varela',
    email: 'admin@fefuart.test',
    role: 'admin',
    ...overrides,
  })
}

/** Monta con los providers reales y el router en memoria. */
export function renderConProviders(
  ui: ReactNode,
  { ruta = '/' }: { ruta?: string } = {},
): RenderResult {
  const client = crearQueryClient()
  // En tests no interesa esperar reintentos.
  client.setDefaultOptions({ queries: { retry: false } })

  return render(
    <QueryClientProvider client={client}>
      <AuthProvider>
        <MemoryRouter initialEntries={[ruta]}>{ui}</MemoryRouter>
      </AuthProvider>
    </QueryClientProvider>,
  )
}
