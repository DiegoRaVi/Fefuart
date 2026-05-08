import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import type { SessionUser } from '../../shared/api/types';
import { apiRequest, buildQueryString } from '../../shared/api/client';

type OrdersPageProps = {
  user: SessionUser | null;
};

type OrderStatusFilter = 'all' | 'pending' | 'paid' | 'shipped' | 'cancelled' | 'rejected' | 'done';

type OrderItem = {
  id: number;
  name: string;
  price: number;
  quantity: number;
};

type Order = {
  id: number;
  status: string;
  order_date: string;
  address: string;
  total: number;
  items: OrderItem[];
};

type PaginationMeta = {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
};

type StatusBreakdown = {
  status: Exclude<OrderStatusFilter, 'all'>;
  label: string;
  count: number;
};

const ORDER_STATUS_OPTIONS: ReadonlyArray<{ value: OrderStatusFilter; label: string }> = [
  { value: 'all', label: 'Todos' },
  { value: 'pending', label: 'Pending' },
  { value: 'paid', label: 'Paid' },
  { value: 'shipped', label: 'Shipped' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'done', label: 'Done' },
];

const BREAKDOWN_STATUS_OPTIONS: ReadonlyArray<{ value: Exclude<OrderStatusFilter, 'all'>; label: string }> = [
  { value: 'pending', label: 'Pending' },
  { value: 'paid', label: 'Paid' },
  { value: 'shipped', label: 'Shipped' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'done', label: 'Done' },
];

function toNumber(value: unknown, fallback: number): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function normalizeText(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

function formatStatus(status: string): string {
  return status.charAt(0).toUpperCase() + status.slice(1);
}

function getStatusChipClass(status: string): string {
  return `status-chip status-chip-${status.toLowerCase()}`;
}

function parsePaginationMeta(meta?: Record<string, unknown>): PaginationMeta {
  const pagination = (meta?.pagination as Record<string, unknown> | undefined) ?? {};

  return {
    currentPage: toNumber(pagination.current_page, 1),
    lastPage: toNumber(pagination.last_page, 1),
    perPage: toNumber(pagination.per_page, 10),
    total: toNumber(pagination.total, 0),
  };
}

export function OrdersPage({ user }: OrdersPageProps) {
  const [statusFilter, setStatusFilter] = useState<OrderStatusFilter>('all');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [searchTerm, setSearchTerm] = useState('');
  const [expandedOrders, setExpandedOrders] = useState<Record<number, boolean>>({});

  useEffect(() => {
    setPage(1);
  }, [statusFilter, perPage]);

  const ordersQuery = useQuery({
    queryKey: ['my-orders', statusFilter, page, perPage],
    enabled: Boolean(user),
    queryFn: async () => {
      const query = buildQueryString({ status: statusFilter, per_page: perPage, page });
      const response = await apiRequest<{ orders: Order[] }>(`/orders/my${query}`);

      return {
        orders: response.data.orders,
        pagination: parsePaginationMeta(response.meta),
      };
    },
  });

  useEffect(() => {
    setExpandedOrders({});
  }, [ordersQuery.data?.orders]);

  const pagination = useMemo(() => {
    return (
      ordersQuery.data?.pagination ?? {
        currentPage: page,
        lastPage: 1,
        perPage,
        total: 0,
      }
    );
  }, [ordersQuery.data?.pagination, page, perPage]);

  const visibleOrders = useMemo(() => {
    const orders = ordersQuery.data?.orders ?? [];
    const normalizedSearch = normalizeText(searchTerm);

    if (!normalizedSearch) {
      return orders;
    }

    return orders.filter((order) => {
      const orderText = [
        String(order.id),
        order.status,
        order.order_date,
        order.address,
        ...order.items.map((item) => item.name),
      ]
        .map((value) => normalizeText(value))
        .join(' ');

      return orderText.includes(normalizedSearch);
    });
  }, [ordersQuery.data?.orders, searchTerm]);

  const visibleStatusBreakdown = useMemo<StatusBreakdown[]>(() => {
    const statusCounts = visibleOrders.reduce<Record<string, number>>((accumulator, order) => {
      const normalizedStatus = order.status.toLowerCase();
      accumulator[normalizedStatus] = (accumulator[normalizedStatus] ?? 0) + 1;
      return accumulator;
    }, {});

    return BREAKDOWN_STATUS_OPTIONS
      .map((option) => ({
        status: option.value,
        label: option.label,
        count: statusCounts[option.value] ?? 0,
      }))
      .filter((entry) => entry.count > 0);
  }, [visibleOrders]);

  if (!user) {
    return (
      <section className="panel stack-gap">
        <p>Debes iniciar sesion para ver tu historial de pedidos.</p>
        <Link to="/auth" className="solid-button">
          Ir a entrar
        </Link>
      </section>
    );
  }

  return (
    <section className="stack-gap-large">
      <header className="panel stack-gap">
        <h1>Historial de pedidos</h1>
        <p>Consulta tus pedidos cerrados y filtra por estado para seguimiento rapido.</p>

        <div className="filters-row">
          <label className="field">
            <span>Estado</span>
            <select
              value={statusFilter}
              onChange={(event) => setStatusFilter(event.target.value as OrderStatusFilter)}
              disabled={ordersQuery.isFetching}
            >
              {ORDER_STATUS_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <label className="field">
            <span>Buscar en pagina</span>
            <input
              type="search"
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              placeholder="ID, estado, direccion o item"
              disabled={ordersQuery.isFetching}
            />
          </label>

          <label className="field">
            <span>Items por pagina</span>
            <select value={perPage} onChange={(event) => setPerPage(Number(event.target.value))} disabled={ordersQuery.isFetching}>
              <option value={5}>5</option>
              <option value={10}>10</option>
              <option value={20}>20</option>
            </select>
          </label>

          <button type="button" className="outline-button" onClick={() => ordersQuery.refetch()} disabled={ordersQuery.isFetching}>
            {ordersQuery.isFetching ? 'Actualizando...' : 'Actualizar'}
          </button>
        </div>
      </header>

      {ordersQuery.isLoading && <p className="panel">Cargando historial...</p>}
      {ordersQuery.isError && <p className="feedback error">No se pudo cargar el historial. Intenta de nuevo.</p>}

      {ordersQuery.data && ordersQuery.data.orders.length === 0 && (
        <p className="panel">Aun no tienes pedidos cerrados con este filtro.</p>
      )}

      {ordersQuery.data && ordersQuery.data.orders.length > 0 && (
        <>
          <div className="stats-row">
            <div className="mini-stat">
              <span>Total pedidos</span>
              <strong>{pagination.total}</strong>
            </div>
            <div className="mini-stat">
              <span>Pagina</span>
              <strong>
                {pagination.currentPage}/{pagination.lastPage}
              </strong>
            </div>
            <div className="mini-stat">
              <span>Por pagina</span>
              <strong>{pagination.perPage}</strong>
            </div>
            <div className="mini-stat">
              <span>Mostrando</span>
              <strong>{visibleOrders.length}</strong>
            </div>
          </div>

          <article className="panel stack-gap">
            <h2>Estados visibles en pagina</h2>

            {visibleStatusBreakdown.length === 0 && <p className="muted-text">Sin pedidos visibles para calcular desglose.</p>}

            {visibleStatusBreakdown.length > 0 && (
              <div className="status-metrics-grid">
                {visibleStatusBreakdown.map((entry) => (
                  <div key={entry.status} className="status-metric-card">
                    <span className={getStatusChipClass(entry.status)}>{entry.label}</span>
                    <strong>{entry.count}</strong>
                  </div>
                ))}
              </div>
            )}
          </article>

          {visibleOrders.length === 0 && (
            <p className="panel">No hay coincidencias para la busqueda actual dentro de esta pagina.</p>
          )}

          {visibleOrders.length > 0 && (
            <ul className="plain-list">
              {visibleOrders.map((order) => {
                const isExpanded = expandedOrders[order.id] ?? false;

                return (
                  <li key={order.id} className="panel stack-gap">
                    <div className="row-item">
                      <h2>Pedido #{order.id}</h2>
                      <div className="button-row">
                        <span className={getStatusChipClass(order.status)}>{formatStatus(order.status)}</span>
                        <strong>{order.total.toFixed(2)} EUR</strong>
                      </div>
                    </div>

                    <p>
                      Fecha: {order.order_date} · Direccion: {order.address} · Lineas: {order.items.length}
                    </p>

                    <div className="button-row">
                      <button
                        type="button"
                        className="outline-button"
                        onClick={() =>
                          setExpandedOrders((previous) => ({
                            ...previous,
                            [order.id]: !isExpanded,
                          }))
                        }
                        disabled={order.items.length === 0}
                      >
                        {isExpanded ? `Ocultar lineas (${order.items.length})` : `Ver lineas (${order.items.length})`}
                      </button>
                    </div>

                    {isExpanded && order.items.length > 0 && (
                      <ul className="plain-list order-lines-list">
                        {order.items.map((item) => (
                          <li key={item.id} className="order-line-item">
                            {item.name} · {item.quantity} x {item.price.toFixed(2)} EUR · subtotal{' '}
                            {(item.quantity * item.price).toFixed(2)} EUR
                          </li>
                        ))}
                      </ul>
                    )}
                  </li>
                );
              })}
            </ul>
          )}

          <div className="button-row">
            <button
              type="button"
              className="outline-button"
              onClick={() => setPage((previous) => Math.max(previous - 1, 1))}
              disabled={ordersQuery.isFetching || pagination.currentPage <= 1}
            >
              Anterior
            </button>
            <button
              type="button"
              className="outline-button"
              onClick={() => setPage((previous) => Math.min(previous + 1, pagination.lastPage))}
              disabled={ordersQuery.isFetching || pagination.currentPage >= pagination.lastPage}
            >
              Siguiente
            </button>
          </div>
        </>
      )}
    </section>
  );
}
