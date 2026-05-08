import { useQuery } from '@tanstack/react-query';
import { apiRequest } from '../../shared/api/client';
import type { SessionUser } from '../../shared/api/types';

type NotificationsPageProps = {
  user: SessionUser | null;
};

type NotificationItem = {
  id: number;
  title: string;
  body: string | null;
  context_type: string;
  context_id: number;
  previous_status: string | null;
  new_status: string;
  is_read: boolean;
  created_at: string;
};

export function NotificationsPage({ user }: NotificationsPageProps) {
  const notificationsQuery = useQuery({
    queryKey: ['notifications-my', user?.id],
    enabled: !!user,
    queryFn: async () => {
      const response = await apiRequest<{ notifications: NotificationItem[] }>('/notifications/my?per_page=30');
      return response.data.notifications;
    },
  });

  async function markAsRead(notificationId: number): Promise<void> {
    await apiRequest<{ notification: NotificationItem }>(`/notifications/${notificationId}/read`, {
      method: 'PATCH',
    });

    await notificationsQuery.refetch();
  }

  if (!user) {
    return <section className="panel">Inicia sesion para consultar tus notificaciones.</section>;
  }

  return (
    <section className="panel stack-gap-large">
      <header className="stack-gap">
        <h1>Notificaciones</h1>
        <p>Vista de notificaciones operativas generadas por flujos backoffice v1.</p>
      </header>

      {notificationsQuery.isLoading && <p>Cargando notificaciones...</p>}
      {notificationsQuery.isError && <p className="error">No se pudieron cargar las notificaciones.</p>}

      {notificationsQuery.data && notificationsQuery.data.length === 0 && <p>No tienes notificaciones por ahora.</p>}

      {notificationsQuery.data && notificationsQuery.data.length > 0 && (
        <div className="stack-gap">
          {notificationsQuery.data.map((notification) => (
            <article key={notification.id} className={notification.is_read ? 'notice-card' : 'notice-card notice-card-unread'}>
              <div className="notice-head">
                <h2>{notification.title}</h2>
                <span>{new Date(notification.created_at).toLocaleString()}</span>
              </div>

              <p>{notification.body ?? 'Sin cuerpo'}</p>

              <p className="notice-meta">
                {notification.context_type} #{notification.context_id} · {notification.previous_status ?? 'n/a'} →{' '}
                {notification.new_status}
              </p>

              {!notification.is_read && (
                <button type="button" className="ghost-button" onClick={() => markAsRead(notification.id)}>
                  Marcar como leida
                </button>
              )}
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
