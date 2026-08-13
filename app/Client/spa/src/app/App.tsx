import { BrowserRouter, Route, Routes } from 'react-router'

import { Login } from '@/features/auth/pages/Login'
import { Catalogo } from '@/features/catalog/pages/Catalogo'
import { FichaProducto } from '@/features/catalog/pages/FichaProducto'
import { RecuperarContrasena } from '@/features/auth/pages/RecuperarContrasena'
import { Registro } from '@/features/auth/pages/Registro'
import { RestablecerContrasena } from '@/features/auth/pages/RestablecerContrasena'
import { Portada } from '@/features/inicio/Portada'
import { Perfil } from '@/features/perfil/pages/Perfil'

import { LayoutPrincipal } from './layouts/LayoutPrincipal'
import { AuthProvider } from './providers/AuthProvider'
import { QueryProvider } from './providers/QueryProvider'
import { RutaDeInvitado, RutaProtegida } from './routes/RutaProtegida'

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

              {/* Publico: mirar no exige cuenta, encargar si (N18). */}
              <Route path="encargos" element={<Catalogo />} />
              <Route path="encargos/:slug" element={<FichaProducto />} />

              <Route element={<RutaDeInvitado />}>
                <Route path="login" element={<Login />} />
                <Route path="registro" element={<Registro />} />
                <Route path="recuperar-contrasena" element={<RecuperarContrasena />} />
                {/* Destino del enlace del correo, que construye el backend en
                    AppServiceProvider::configurePasswordResetUrl. */}
                <Route path="restablecer-contrasena" element={<RestablecerContrasena />} />
              </Route>

              <Route element={<RutaProtegida />}>
                {/* Tambien el destino del enlace de verificacion de email,
                    que redirige aqui con ?verificado=1. */}
                <Route path="perfil" element={<Perfil />} />
              </Route>
            </Route>
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </QueryProvider>
  )
}
