import { BrowserRouter, Route, Routes } from 'react-router'

import { Login } from '@/features/auth/pages/Login'
import { Registro } from '@/features/auth/pages/Registro'
import { Portada } from '@/features/inicio/Portada'

import { LayoutPrincipal } from './layouts/LayoutPrincipal'
import { AuthProvider } from './providers/AuthProvider'
import { QueryProvider } from './providers/QueryProvider'
import { RutaDeInvitado } from './routes/RutaProtegida'

/**
 * El orden importa: QueryProvider envuelve a AuthProvider porque la sesion
 * se resuelve con una query, y el router va dentro de los dos para que
 * cualquier pantalla pueda leer la sesion y navegar.
 */
export function App() {
  return (
    <QueryProvider>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            <Route element={<LayoutPrincipal />}>
              <Route index element={<Portada />} />

              <Route element={<RutaDeInvitado />}>
                <Route path="login" element={<Login />} />
                <Route path="registro" element={<Registro />} />
              </Route>
            </Route>
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </QueryProvider>
  )
}
