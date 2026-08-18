import { BrowserRouter, Navigate, Route, Routes } from 'react-router'

import { Login } from '@/features/auth/pages/Login'
import { Avisos } from '@/features/avisos/pages/Avisos'
import { LayoutDeBackoffice } from '@/features/backoffice/LayoutDeBackoffice'
import { CatalogoDeAdmin } from '@/features/backoffice/pages/CatalogoDeAdmin'
import { AjustesDeAdmin } from '@/features/backoffice/pages/AjustesDeAdmin'
import { EventosDeAdmin } from '@/features/backoffice/pages/EventosDeAdmin'
import { PedidoDeAdmin } from '@/features/backoffice/pages/PedidoDeAdmin'
import { PedidosDeAdmin } from '@/features/backoffice/pages/PedidosDeAdmin'
import { Carrito } from '@/features/cart/pages/Carrito'
import { Checkout } from '@/features/cart/pages/Checkout'
import { Catalogo } from '@/features/catalog/pages/Catalogo'
import { FichaProducto } from '@/features/catalog/pages/FichaProducto'
import { FormularioDeEncargo } from '@/features/catalog/pages/FormularioDeEncargo'
import { RecuperarContrasena } from '@/features/auth/pages/RecuperarContrasena'
import { Registro } from '@/features/auth/pages/Registro'
import { RestablecerContrasena } from '@/features/auth/pages/RestablecerContrasena'
import { LiveArt } from '@/features/eventos/pages/LiveArt'
import { Portada } from '@/features/inicio/Portada'
import { DetalleDePedido } from '@/features/orders/pages/DetalleDePedido'
import { MisPedidos } from '@/features/orders/pages/MisPedidos'
import { VueltaDelPago } from '@/features/pagos/pages/VueltaDelPago'
import { Perfil } from '@/features/perfil/pages/Perfil'

import { LayoutPrincipal } from './layouts/LayoutPrincipal'
import { AuthProvider } from './providers/AuthProvider'
import { QueryProvider } from './providers/QueryProvider'
import { RutaDeAdmin, RutaDeInvitado, RutaProtegida } from './routes/RutaProtegida'

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

                {/* D10 — el centro de avisos. Es de quien inicia sesion, asi
                    que va tras la ruta protegida como el resto. */}
                <Route path="avisos" element={<Avisos />} />

                {/* N18 — encargar exige cuenta; mirar el catalogo no. */}
                <Route path="encargos/:slug/encargar" element={<FormularioDeEncargo />} />
                <Route path="carrito" element={<Carrito />} />
                <Route path="carrito/confirmar" element={<Checkout />} />
                <Route path="pedidos" element={<MisPedidos />} />
                <Route path="pedidos/:id" element={<DetalleDePedido />} />

                {/* La vuelta de Stripe. No da nada por pagado: pregunta al
                    servidor hasta que el webhook haya movido el estado. */}
                <Route path="pedidos/:id/pago" element={<VueltaDelPago tipo="pedido" />} />
                {/* N18 — pedir presupuesto tambien exige cuenta: la artista
                    tiene que poder responder a alguien. */}
                <Route path="live-art" element={<LiveArt />} />
                <Route path="live-art/:id/pago" element={<VueltaDelPago tipo="evento" />} />
              </Route>

              {/* N20 — el backoffice es de la administradora. El rol sale de
                  `GET /api/auth/me`, y cada endpoint lo vuelve a comprobar
                  con el middleware `admin`. */}
              <Route element={<RutaDeAdmin />}>
                <Route path="backoffice" element={<LayoutDeBackoffice />}>
                  <Route index element={<Navigate to="/backoffice/pedidos" replace />} />
                  <Route path="pedidos" element={<PedidosDeAdmin />} />
                  <Route path="pedidos/:id" element={<PedidoDeAdmin />} />
                  <Route path="eventos" element={<EventosDeAdmin />} />
                  <Route path="catalogo" element={<CatalogoDeAdmin />} />
                  {/* N15 — el porcentaje de la señal y la validez del
                      presupuesto, sin tocar codigo. */}
                  <Route path="ajustes" element={<AjustesDeAdmin />} />
                </Route>
              </Route>
            </Route>
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </QueryProvider>
  )
}
