import { NavLink, Outlet } from 'react-router'

const SECCIONES = [
  { to: '/backoffice/pedidos', texto: 'Pedidos' },
  { to: '/backoffice/eventos', texto: 'Eventos' },
  { to: '/backoffice/catalogo', texto: 'Catalogo' },
  { to: '/backoffice/galeria', texto: 'Galería' },
  { to: '/backoffice/ajustes', texto: 'Ajustes' },
]

/**
 * N20 — esto es de la administradora. Quien llega aqui ya ha pasado por
 * RutaDeAdmin, que lo resuelve contra `GET /api/auth/me`; y cada endpoint
 * vuelve a comprobarlo con el middleware `admin`.
 */
export function LayoutDeBackoffice() {
  return (
    <div className="space-y-6">
      <nav aria-label="Backoffice" className="flex flex-wrap gap-2 border-b border-piedra/20 pb-3">
        {SECCIONES.map((seccion) => (
          <NavLink
            key={seccion.to}
            to={seccion.to}
            className={({ isActive }) =>
              `rounded-fefu px-4 py-2 text-base ${
                isActive ? 'bg-verde text-white' : 'text-verde hover:bg-rosa-suave'
              }`
            }
          >
            {seccion.texto}
          </NavLink>
        ))}
      </nav>

      <Outlet />
    </div>
  )
}
