import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiRequest } from '../../shared/api/client';
import type { SessionUser } from '../../shared/api/types';

type BackofficePageProps = {
  user: SessionUser | null;
};

type BackofficeSummary = {
  orders: Record<string, number>;
  events: Record<string, number>;
  catalog_products_total: number;
  generated_at: string;
};

type BackofficeOrder = {
  id: number;
  user_id: number;
  status: 'cart' | 'pending' | 'paid' | 'shipped' | 'cancelled';
  total: number;
};

type BackofficeEvent = {
  id: number;
  user_id: number;
  title: string;
  status: 'pending' | 'confirmed' | 'rejected' | 'done';
};

const allowedRoles = new Set(['admin', 'assistant']);

export function BackofficePage({ user }: BackofficePageProps) {
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [orderDrafts, setOrderDrafts] = useState<Record<number, BackofficeOrder['status']>>({});
  const [eventDrafts, setEventDrafts] = useState<Record<number, BackofficeEvent['status']>>({});

  const canAccess = useMemo(() => !!user && allowedRoles.has(user.role), [user]);

  const summaryQuery = useQuery({
    queryKey: ['backoffice-summary'],
    enabled: canAccess,
    queryFn: async () => {
      const response = await apiRequest<{ summary: BackofficeSummary }>('/backoffice/summary');
      return response.data.summary;
    },
  });

  const ordersQuery = useQuery({
    queryKey: ['backoffice-orders'],
    enabled: canAccess,
    queryFn: async () => {
      const response = await apiRequest<{ orders: BackofficeOrder[] }>('/backoffice/orders?per_page=20');
      return response.data.orders;
    },
  });

  const eventsQuery = useQuery({
    queryKey: ['backoffice-events'],
    enabled: canAccess,
    queryFn: async () => {
      const response = await apiRequest<{ events: BackofficeEvent[] }>('/backoffice/events?per_page=20');
      return response.data.events;
    },
  });

  async function updateOrderStatus(order: BackofficeOrder): Promise<void> {
    setError('');
    setMessage('');

    const nextStatus = orderDrafts[order.id] ?? order.status;

    try {
      await apiRequest(`/backoffice/orders/${order.id}/status`, {
        method: 'PATCH',
        body: JSON.stringify({ status: nextStatus }),
      });

      await Promise.all([summaryQuery.refetch(), ordersQuery.refetch()]);
      setMessage(`Pedido #${order.id} actualizado a ${nextStatus}.`);
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'No se pudo actualizar el pedido.');
    }
  }

  async function updateEventStatus(event: BackofficeEvent): Promise<void> {
    setError('');
    setMessage('');

    const nextStatus = eventDrafts[event.id] ?? event.status;

    try {
      await apiRequest(`/backoffice/events/${event.id}/status`, {
        method: 'PATCH',
        body: JSON.stringify({ status: nextStatus }),
      });

      await Promise.all([summaryQuery.refetch(), eventsQuery.refetch()]);
      setMessage(`Evento #${event.id} actualizado a ${nextStatus}.`);
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'No se pudo actualizar el evento.');
    }
  }

  if (!user) {
    return <section className="panel">Necesitas iniciar sesion para entrar al backoffice.</section>;
  }

  if (!canAccess) {
    return <section className="panel">Tu rol actual no tiene acceso al backoffice v1.</section>;
  }

  return (
    <section className="stack-gap-large">
      <header className="panel stack-gap">
        <h1>Backoffice operativo</h1>
        <p>Resumen de actividad, gestion de pedidos y gestion de eventos sobre endpoints protegidos de BackofficeOps v1.</p>
      </header>

      {summaryQuery.data && (
        <article className="panel stack-gap">
          <h2>Resumen</h2>
          <div className="stats-row">
            {Object.entries(summaryQuery.data.orders).map(([status, count]) => (
              <div key={status} className="mini-stat">
                <span>{status}</span>
                <strong>{count}</strong>
              </div>
            ))}
            {Object.entries(summaryQuery.data.events).map(([status, count]) => (
              <div key={status} className="mini-stat">
                <span>event {status}</span>
                <strong>{count}</strong>
              </div>
            ))}
            <div className="mini-stat">
              <span>catalogo</span>
              <strong>{summaryQuery.data.catalog_products_total}</strong>
            </div>
          </div>
        </article>
      )}

      <section className="split-grid">
        <article className="panel stack-gap">
          <h2>Pedidos</h2>
          {ordersQuery.isLoading && <p>Cargando pedidos...</p>}
          {ordersQuery.data && (
            <ul className="plain-list">
              {ordersQuery.data.map((order) => (
                <li key={order.id} className="row-item stack-gap">
                  <div>
                    <strong>#{order.id}</strong> user {order.user_id} · {order.status} · {order.total.toFixed(2)} EUR
                  </div>
                  <div className="button-row">
                    <select
                      value={orderDrafts[order.id] ?? order.status}
                      onChange={(event) =>
                        setOrderDrafts((current) => ({
                          ...current,
                          [order.id]: event.target.value as BackofficeOrder['status'],
                        }))
                      }
                    >
                      <option value="pending">pending</option>
                      <option value="paid">paid</option>
                      <option value="shipped">shipped</option>
                      <option value="cancelled">cancelled</option>
                    </select>
                    <button type="button" className="ghost-button" onClick={() => updateOrderStatus(order)}>
                      Guardar
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </article>

        <article className="panel stack-gap">
          <h2>Eventos</h2>
          {eventsQuery.isLoading && <p>Cargando eventos...</p>}
          {eventsQuery.data && (
            <ul className="plain-list">
              {eventsQuery.data.map((event) => (
                <li key={event.id} className="row-item stack-gap">
                  <div>
                    <strong>#{event.id}</strong> {event.title} · {event.status}
                  </div>
                  <div className="button-row">
                    <select
                      value={eventDrafts[event.id] ?? event.status}
                      onChange={(eventChange) =>
                        setEventDrafts((current) => ({
                          ...current,
                          [event.id]: eventChange.target.value as BackofficeEvent['status'],
                        }))
                      }
                    >
                      <option value="pending">pending</option>
                      <option value="confirmed">confirmed</option>
                      <option value="rejected">rejected</option>
                      <option value="done">done</option>
                    </select>
                    <button type="button" className="ghost-button" onClick={() => updateEventStatus(event)}>
                      Guardar
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </article>
      </section>

      {message && <p className="feedback success">{message}</p>}
      {error && <p className="feedback error">{error}</p>}
    </section>
  );
}
