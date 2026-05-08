import { useEffect, useState } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import type { SessionUser } from '../shared/api/types';
import { AppShell } from '../shared/ui/AppShell';
import { clearSession, getSessionEventName, getSessionUser } from '../shared/lib/session';
import { HomePage } from './HomePage';
import { AuthPage } from '../features/auth/AuthPage';
import { CatalogPage } from '../features/catalog/CatalogPage';
import { CartPage } from '../features/cart/CartPage';
import { OrdersPage } from '../features/orders/OrdersPage';
import { LiveArtPage } from '../features/live-art-booking/LiveArtPage';
import { NotificationsPage } from '../features/notifications/NotificationsPage';
import { MediaAssetsPage } from '../features/media-assets/MediaAssetsPage';
import { BackofficePage } from '../features/backoffice/BackofficePage';

export function App() {
  const [user, setUser] = useState<SessionUser | null>(() => getSessionUser());

  useEffect(() => {
    const eventName = getSessionEventName();
    const handler = () => setUser(getSessionUser());

    window.addEventListener(eventName, handler);
    return () => window.removeEventListener(eventName, handler);
  }, []);

  return (
    <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <AppShell user={user} onLogout={clearSession}>
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/auth" element={<AuthPage user={user} />} />
          <Route path="/catalog" element={<CatalogPage />} />
          <Route path="/cart" element={<CartPage user={user} />} />
          <Route path="/orders" element={<OrdersPage user={user} />} />
          <Route path="/live-art" element={<LiveArtPage user={user} />} />
          <Route path="/notifications" element={<NotificationsPage user={user} />} />
          <Route path="/media" element={<MediaAssetsPage user={user} />} />
          <Route path="/backoffice" element={<BackofficePage user={user} />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AppShell>
    </BrowserRouter>
  );
}
