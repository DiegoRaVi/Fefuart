import { Link } from 'react-router-dom';

export function HomePage() {
  return (
    <section className="hero-grid">
      <div className="hero-card">
        <p className="eyebrow">Rebuild activo</p>
        <h1>Frontend SPA por dominios, conectado a API v1</h1>
        <p>
          Esta interfaz convive con el frontend legacy para permitir migracion progresiva. Incluye auth, catalogo,
          carrito, live art, backoffice, notificaciones y media assets.
        </p>
        <div className="hero-actions">
          <Link to="/auth" className="solid-button">
            Entrar al sistema
          </Link>
          <Link to="/catalog" className="outline-button">
            Ver catalogo
          </Link>
        </div>
      </div>

      <div className="stats-stack">
        <article className="stat-card">
          <h2>Arquitectura</h2>
          <p>React + TypeScript + React Query + Router</p>
        </article>
        <article className="stat-card">
          <h2>Contrato</h2>
          <p>Consumo de /api/v1 con envelope uniforme</p>
        </article>
        <article className="stat-card">
          <h2>Objetivo</h2>
          <p>Reducir deuda del multipage vanilla sin frenar negocio</p>
        </article>
      </div>
    </section>
  );
}
