import { Link, NavLink } from 'react-router-dom';
import type { PropsWithChildren } from 'react';
import type { SessionUser } from '../api/types';

type AppShellProps = PropsWithChildren<{
  user: SessionUser | null;
  onLogout: () => void;
}>;

const navLinks = [
  { to: '/', label: 'Inicio' },
  { to: '/catalog', label: 'Catalogo' },
  { to: '/cart', label: 'Carrito' },
  { to: '/orders', label: 'Pedidos' },
  { to: '/live-art', label: 'Live Art' },
  { to: '/notifications', label: 'Avisos' },
  { to: '/media', label: 'Media' },
  { to: '/backoffice', label: 'Backoffice' },
];

export function AppShell({ user, onLogout, children }: AppShellProps) {
  return (
    <div className="app-shell">
      <header className="topbar">
        <Link to="/" className="brand-mark">
          <span className="brand-mark-dot" />
          FefuArt v1
        </Link>

        <nav className="topbar-nav">
          {navLinks.map((link) => (
            <NavLink key={link.to} to={link.to} className={({ isActive }) => (isActive ? 'nav-pill nav-pill-active' : 'nav-pill')}>
              {link.label}
            </NavLink>
          ))}
        </nav>

        <div className="session-block">
          {user ? (
            <>
              <div className="session-user">
                <span>{user.name}</span>
                <small>{user.role}</small>
              </div>
              <button type="button" className="ghost-button" onClick={onLogout}>
                Cerrar sesion
              </button>
            </>
          ) : (
            <Link to="/auth" className="ghost-button link-button">
              Entrar
            </Link>
          )}
        </div>
      </header>

      <main className="page-frame">{children}</main>
    </div>
  );
}
