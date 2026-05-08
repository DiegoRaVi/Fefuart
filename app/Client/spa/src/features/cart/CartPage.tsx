import { FormEvent, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import type { SessionUser } from '../../shared/api/types';
import { apiRequest } from '../../shared/api/client';
import { getApiFieldErrors, pickFieldErrors } from '../../shared/lib/fieldErrors';

type CartPageProps = {
  user: SessionUser | null;
};

type CustomItemField = 'name' | 'price' | 'quantity';
type CatalogItemField = 'product_id' | 'quantity';
type CheckoutField = 'address';

type CartItem = {
  id: number;
  name: string;
  price: number;
  quantity: number;
};

type CatalogProduct = {
  id: number;
  name: string;
  price: number;
  category: string;
  subcategory: string | null;
  delivery_type: string;
  delivery_time: string;
};

type Cart = {
  id: number;
  status: string;
  address: string;
  total: number;
  items: CartItem[];
  updated_at?: string;
};

const CHECKOUT_ADDRESS_KEY = 'fefuart_checkout_address_hint_v1';

function readCheckoutAddressHint(): string {
  try {
    return window.localStorage.getItem(CHECKOUT_ADDRESS_KEY) ?? '';
  } catch {
    return '';
  }
}

export function CartPage({ user }: CartPageProps) {
  const [cart, setCart] = useState<Cart | null>(null);
  const [name, setName] = useState('Retrato digital');
  const [price, setPrice] = useState(45);
  const [quantity, setQuantity] = useState(1);
  const [catalogProductId, setCatalogProductId] = useState<number | null>(null);
  const [catalogQuantity, setCatalogQuantity] = useState(1);
  const [address, setAddress] = useState('');
  const [lastCheckoutAddress, setLastCheckoutAddress] = useState(() => readCheckoutAddressHint());
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [customFieldErrors, setCustomFieldErrors] = useState<Partial<Record<CustomItemField, string>>>({});
  const [catalogFieldErrors, setCatalogFieldErrors] = useState<Partial<Record<CatalogItemField, string>>>({});
  const [checkoutFieldErrors, setCheckoutFieldErrors] = useState<Partial<Record<CheckoutField, string>>>({});
  const [lineFieldErrors, setLineFieldErrors] = useState<Record<number, string>>({});
  const [lineDraftQuantities, setLineDraftQuantities] = useState<Record<number, number>>({});
  const [lineActionLoadingId, setLineActionLoadingId] = useState<number | null>(null);
  const [lineActionType, setLineActionType] = useState<'update' | 'remove' | null>(null);
  const [cartActionLoading, setCartActionLoading] = useState(false);
  const [addCustomLoading, setAddCustomLoading] = useState(false);
  const [addCatalogLoading, setAddCatalogLoading] = useState(false);
  const [checkoutLoading, setCheckoutLoading] = useState(false);

  const catalogQuery = useQuery({
    queryKey: ['catalog-options'],
    queryFn: async () => {
      const response = await apiRequest<{ products: CatalogProduct[] }>('/catalog/products?per_page=50');
      return response.data.products;
    },
  });

  useEffect(() => {
    if (!catalogQuery.data?.length || catalogProductId !== null) {
      return;
    }

    setCatalogProductId(catalogQuery.data[0].id);
  }, [catalogProductId, catalogQuery.data]);

  useEffect(() => {
    if (!cart) {
      setLineDraftQuantities({});
      return;
    }

    const nextDrafts = cart.items.reduce<Record<number, number>>((accumulator, item) => {
      accumulator[item.id] = item.quantity;
      return accumulator;
    }, {});

    setLineDraftQuantities(nextDrafts);
    setLineFieldErrors({});
  }, [cart]);

  const selectedCatalogProduct = useMemo(
    () => catalogQuery.data?.find((product) => product.id === catalogProductId) ?? null,
    [catalogProductId, catalogQuery.data]
  );

  const computedCartTotal = useMemo(() => {
    if (!cart) {
      return 0;
    }

    return cart.items.reduce((total, item) => total + item.price * item.quantity, 0);
  }, [cart]);

  const hasCartItems = (cart?.items.length ?? 0) > 0;
  const canEditLines = cart?.status === 'cart';
  const isBusy =
    cartActionLoading ||
    addCustomLoading ||
    addCatalogLoading ||
    checkoutLoading ||
    lineActionLoadingId !== null;

  async function createOrGetCart(): Promise<void> {
    setCartActionLoading(true);
    setError('');
    setMessage('');
    setLineFieldErrors({});

    try {
      const response = await apiRequest<{ cart: Cart }>('/cart', { method: 'POST' });
      setCart(response.data.cart);
      setMessage('Carrito preparado correctamente.');
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'No se pudo preparar el carrito.');
    } finally {
      setCartActionLoading(false);
    }
  }

  async function refreshCart(options?: { silent?: boolean }): Promise<void> {
    const silent = options?.silent ?? false;
    setCartActionLoading(true);

    if (!silent) {
      setError('');
      setMessage('');
    }

    try {
      const response = await apiRequest<{ cart: Cart }>('/cart');
      setCart(response.data.cart);

      if (!silent) {
        setMessage('Carrito actualizado.');
      }
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'No se pudo leer el carrito.');
    } finally {
      setCartActionLoading(false);
    }
  }

  async function addCustomItem(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setAddCustomLoading(true);
    setError('');
    setMessage('');
    setCustomFieldErrors({});

    if (!name.trim()) {
      setCustomFieldErrors({ name: 'El nombre del item es obligatorio.' });
      setError('Revisa los campos del item personalizado.');
      setAddCustomLoading(false);
      return;
    }

    if (price <= 0) {
      setCustomFieldErrors({ price: 'El precio debe ser mayor que 0.' });
      setError('Revisa los campos del item personalizado.');
      setAddCustomLoading(false);
      return;
    }

    if (quantity <= 0) {
      setCustomFieldErrors({ quantity: 'La cantidad debe ser mayor que 0.' });
      setError('Revisa los campos del item personalizado.');
      setAddCustomLoading(false);
      return;
    }

    try {
      await apiRequest('/cart/items', {
        method: 'POST',
        body: JSON.stringify({
          name: name.trim(),
          price,
          quantity,
          description: 'Producto de prueba desde SPA v1',
          category: 'dibujo-encargo',
          subcategory: 'digital',
          delivery_type: 'digital',
          delivery_time: '7 dias',
        }),
      });

      await refreshCart({ silent: true });
      setMessage('Item personalizado agregado al carrito.');
    } catch (requestError) {
      const apiFieldErrors = pickFieldErrors(getApiFieldErrors(requestError), ['name', 'price', 'quantity'] as const);

      if (Object.keys(apiFieldErrors).length > 0) {
        setCustomFieldErrors(apiFieldErrors);
        setError('Revisa los campos del item personalizado.');
      } else {
        setError(requestError instanceof Error ? requestError.message : 'No se pudo agregar el item.');
      }
    } finally {
      setAddCustomLoading(false);
    }
  }

  async function addFromCatalog(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setAddCatalogLoading(true);
    setError('');
    setMessage('');
    setCatalogFieldErrors({});

    if (catalogProductId === null) {
      setCatalogFieldErrors({ product_id: 'Selecciona un producto de catalogo antes de agregar.' });
      setError('Revisa los campos de catalogo.');
      setAddCatalogLoading(false);
      return;
    }

    if (catalogQuantity <= 0) {
      setCatalogFieldErrors({ quantity: 'La cantidad de catalogo debe ser mayor que 0.' });
      setError('Revisa los campos de catalogo.');
      setAddCatalogLoading(false);
      return;
    }

    try {
      await apiRequest('/cart/items/from-catalog', {
        method: 'POST',
        body: JSON.stringify({
          product_id: catalogProductId,
          quantity: catalogQuantity,
        }),
      });

      await refreshCart({ silent: true });
      setMessage('Item de catalogo agregado al carrito.');
    } catch (requestError) {
      const apiFieldErrors = pickFieldErrors(getApiFieldErrors(requestError), ['product_id', 'quantity'] as const);

      if (Object.keys(apiFieldErrors).length > 0) {
        setCatalogFieldErrors(apiFieldErrors);
        setError('Revisa los campos de catalogo.');
      } else {
        setError(requestError instanceof Error ? requestError.message : 'No se pudo agregar desde catalogo.');
      }
    } finally {
      setAddCatalogLoading(false);
    }
  }

  async function updateCartItemQuantity(item: CartItem): Promise<void> {
    const nextQuantity = lineDraftQuantities[item.id] ?? item.quantity;
    setLineFieldErrors((previous) => ({ ...previous, [item.id]: '' }));

    if (!Number.isFinite(nextQuantity) || nextQuantity <= 0) {
      setLineFieldErrors((previous) => ({ ...previous, [item.id]: 'La cantidad debe ser mayor que 0.' }));
      return;
    }

    setLineActionLoadingId(item.id);
    setLineActionType('update');
    setError('');
    setMessage('');

    try {
      await apiRequest(`/cart/items/${item.id}`, {
        method: 'PATCH',
        body: JSON.stringify({ quantity: nextQuantity }),
      });

      await refreshCart({ silent: true });
      setMessage(`Cantidad actualizada para ${item.name}.`);
    } catch (requestError) {
      const apiFieldErrors = getApiFieldErrors(requestError);

      if (apiFieldErrors.quantity) {
        setLineFieldErrors((previous) => ({ ...previous, [item.id]: apiFieldErrors.quantity }));
      } else {
        setError(requestError instanceof Error ? requestError.message : 'No se pudo actualizar la cantidad del item.');
      }
    } finally {
      setLineActionLoadingId(null);
      setLineActionType(null);
    }
  }

  async function removeCartItem(item: CartItem): Promise<void> {
    setLineActionLoadingId(item.id);
    setLineActionType('remove');
    setError('');
    setMessage('');

    try {
      await apiRequest(`/cart/items/${item.id}`, {
        method: 'DELETE',
      });

      await refreshCart({ silent: true });
      setMessage(`Item eliminado: ${item.name}.`);
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : 'No se pudo eliminar el item.');
    } finally {
      setLineActionLoadingId(null);
      setLineActionType(null);
    }
  }

  async function checkout(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setCheckoutLoading(true);
    setError('');
    setMessage('');
    setCheckoutFieldErrors({});

    if (!cart || cart.status !== 'cart') {
      setError('Primero crea o recupera un carrito activo antes de hacer checkout.');
      setCheckoutLoading(false);
      return;
    }

    if (!hasCartItems) {
      setError('El carrito esta vacio. Agrega al menos un item antes de checkout.');
      setCheckoutLoading(false);
      return;
    }

    if (address.trim().length < 5) {
      setCheckoutFieldErrors({ address: 'Incluye una direccion valida (minimo 5 caracteres).' });
      setError('Revisa los datos de checkout.');
      setCheckoutLoading(false);
      return;
    }

    try {
      const normalizedAddress = address.trim();
      const response = await apiRequest<{ order: Cart }>('/cart/checkout', {
        method: 'POST',
        body: JSON.stringify({ address: normalizedAddress }),
      });

      setCart(response.data.order);
      setAddress('');
      setCheckoutFieldErrors({});
      window.localStorage.setItem(CHECKOUT_ADDRESS_KEY, normalizedAddress);
      setLastCheckoutAddress(normalizedAddress);
      setMessage(`Checkout completado. Estado actual: ${response.data.order.status}.`);
    } catch (requestError) {
      const apiFieldErrors = pickFieldErrors(getApiFieldErrors(requestError), ['address'] as const);

      if (Object.keys(apiFieldErrors).length > 0) {
        setCheckoutFieldErrors(apiFieldErrors);
        setError('Revisa los datos de checkout.');
      } else {
        setError(requestError instanceof Error ? requestError.message : 'No se pudo completar checkout.');
      }
    } finally {
      setCheckoutLoading(false);
    }
  }

  if (!user) {
    return (
      <section className="panel stack-gap">
        <p>Debes iniciar sesion para usar carrito y checkout.</p>
        <Link to="/auth" className="solid-button">
          Ir a entrar
        </Link>
      </section>
    );
  }

  return (
    <section className="stack-gap-large">
      <header className="panel stack-gap">
        <h1>Carrito v1</h1>
        <p>Orquesta create/get cart, lineas editables, add from catalog y checkout sobre el modulo OrderManagement.</p>
        <div className="button-row">
          <button type="button" className="solid-button" onClick={createOrGetCart} disabled={isBusy}>
            {cartActionLoading ? 'Preparando...' : 'Crear o recuperar carrito'}
          </button>
          <button type="button" className="outline-button" onClick={() => refreshCart()} disabled={isBusy}>
            {cartActionLoading ? 'Refrescando...' : 'Refrescar carrito'}
          </button>
          <Link to="/orders" className="ghost-button link-button">
            Ver historial de pedidos
          </Link>
        </div>
      </header>

      <section className="split-grid">
        <article className="panel stack-gap">
          <h2>Agregar item personalizado</h2>
          <form className="stack-gap" onSubmit={addCustomItem}>
            <label className="field">
              <span>Nombre</span>
              <input
                value={name}
                onChange={(event) => {
                  setName(event.target.value);
                  setCustomFieldErrors((previous) => ({ ...previous, name: undefined }));
                }}
                required
                disabled={isBusy}
              />
            </label>
            {customFieldErrors.name && <p className="field-error">{customFieldErrors.name}</p>}
            <label className="field">
              <span>Precio</span>
              <input
                type="number"
                min={0.01}
                step={0.01}
                value={price}
                onChange={(event) => {
                  setPrice(Number(event.target.value));
                  setCustomFieldErrors((previous) => ({ ...previous, price: undefined }));
                }}
                required
                disabled={isBusy}
              />
            </label>
            {customFieldErrors.price && <p className="field-error">{customFieldErrors.price}</p>}
            <label className="field">
              <span>Cantidad</span>
              <input
                type="number"
                min={1}
                value={quantity}
                onChange={(event) => {
                  setQuantity(Number(event.target.value));
                  setCustomFieldErrors((previous) => ({ ...previous, quantity: undefined }));
                }}
                required
                disabled={isBusy}
              />
            </label>
            {customFieldErrors.quantity && <p className="field-error">{customFieldErrors.quantity}</p>}
            <button type="submit" className="solid-button" disabled={isBusy}>
              {addCustomLoading ? 'Agregando...' : 'Agregar item'}
            </button>
          </form>
        </article>

        <article className="panel stack-gap">
          <h2>Agregar desde catalogo</h2>
          {catalogQuery.isLoading && <p className="muted-text">Cargando productos de catalogo...</p>}
          {catalogQuery.isError && (
            <p className="feedback error">No se pudo cargar catalogo. Revisa API local o ejecuta -SeedDemoCatalog.</p>
          )}
          {catalogQuery.data && catalogQuery.data.length === 0 && (
            <p className="feedback error">No hay productos disponibles. Ejecuta el runner local con -SeedDemoCatalog.</p>
          )}

          <form className="stack-gap" onSubmit={addFromCatalog}>
            <label className="field">
              <span>Producto de catalogo</span>
              <select
                value={catalogProductId ?? ''}
                onChange={(event) => {
                  const nextValue = event.target.value;
                  setCatalogProductId(nextValue ? Number(nextValue) : null);
                  setCatalogFieldErrors((previous) => ({ ...previous, product_id: undefined }));
                }}
                required
                disabled={isBusy || catalogQuery.isLoading || !catalogQuery.data?.length}
              >
                <option value="" disabled>
                  Selecciona producto
                </option>
                {(catalogQuery.data ?? []).map((product) => (
                  <option key={product.id} value={product.id}>
                    #{product.id} · {product.name} · {product.price.toFixed(2)} EUR
                  </option>
                ))}
              </select>
            </label>
            {catalogFieldErrors.product_id && <p className="field-error">{catalogFieldErrors.product_id}</p>}

            {selectedCatalogProduct && (
              <p className="muted-text">
                Categoria: {selectedCatalogProduct.category} · Subcategoria: {selectedCatalogProduct.subcategory ?? 'n/a'} ·
                Entrega: {selectedCatalogProduct.delivery_type} ({selectedCatalogProduct.delivery_time})
              </p>
            )}

            <label className="field">
              <span>Cantidad</span>
              <input
                type="number"
                min={1}
                value={catalogQuantity}
                onChange={(event) => {
                  setCatalogQuantity(Number(event.target.value));
                  setCatalogFieldErrors((previous) => ({ ...previous, quantity: undefined }));
                }}
                required
                disabled={isBusy || !catalogQuery.data?.length}
              />
            </label>
            {catalogFieldErrors.quantity && <p className="field-error">{catalogFieldErrors.quantity}</p>}
            <button type="submit" className="solid-button" disabled={isBusy || !catalogQuery.data?.length}>
              {addCatalogLoading ? 'Agregando...' : 'Agregar desde catalogo'}
            </button>
          </form>
        </article>
      </section>

      <article className="panel stack-gap">
        <h2>Checkout</h2>
        <form onSubmit={checkout} className="stack-gap">
          <label className="field">
            <span>Direccion</span>
            <input
              value={address}
              onChange={(event) => {
                setAddress(event.target.value);
                setCheckoutFieldErrors((previous) => ({ ...previous, address: undefined }));
              }}
              placeholder="Ejemplo: Calle Mayor 123, Valencia"
              required
              disabled={isBusy}
            />
          </label>
          {checkoutFieldErrors.address && <p className="field-error">{checkoutFieldErrors.address}</p>}
          <p className="muted-text">Consejo: usa direccion completa con calle, numero y ciudad para evitar incidencias de entrega.</p>

          {lastCheckoutAddress && (
            <button
              type="button"
              className="outline-button"
              onClick={() => {
                setAddress(lastCheckoutAddress);
                setCheckoutFieldErrors((previous) => ({ ...previous, address: undefined }));
              }}
              disabled={isBusy}
            >
              Usar ultima direccion: {lastCheckoutAddress}
            </button>
          )}

          <button type="submit" className="solid-button" disabled={isBusy}>
            {checkoutLoading ? 'Procesando...' : 'Confirmar checkout'}
          </button>
        </form>
      </article>

      <article className="panel stack-gap">
        <h2>Estado del carrito</h2>
        {!cart && <p>Aun no hay carrito cargado.</p>}

        {cart && (
          <>
            <p>
              ID {cart.id} · estado {cart.status} · total API {cart.total.toFixed(2)} EUR · total calculado{' '}
              {computedCartTotal.toFixed(2)} EUR
            </p>

            {cart.status !== 'cart' && (
              <p className="feedback success">
                Este carrito ya fue convertido en pedido ({cart.status}).
                {' '}
                <Link to="/orders">Ir al historial</Link>
              </p>
            )}

            {cart.items.length === 0 && <p className="muted-text">El carrito no tiene lineas.</p>}

            {cart.items.length > 0 && (
              <ul className="plain-list">
                {cart.items.map((item) => {
                  const subtotal = item.quantity * item.price;
                  const isUpdatingLine = lineActionLoadingId === item.id && lineActionType === 'update';
                  const isRemovingLine = lineActionLoadingId === item.id && lineActionType === 'remove';

                  return (
                    <li key={item.id} className="panel stack-gap" data-testid={`cart-line-${item.id}`}>
                      <div className="row-item">
                        <p>
                          {item.name} · {item.quantity} x {item.price.toFixed(2)} EUR
                        </p>
                        <strong>{subtotal.toFixed(2)} EUR</strong>
                      </div>

                      {canEditLines && (
                        <div className="button-row">
                          <label className="field">
                            <span>Cantidad</span>
                            <input
                              type="number"
                              min={1}
                              value={lineDraftQuantities[item.id] ?? item.quantity}
                              onChange={(event) => {
                                setLineDraftQuantities((previous) => ({
                                  ...previous,
                                  [item.id]: Number(event.target.value),
                                }));
                                setLineFieldErrors((previous) => ({ ...previous, [item.id]: '' }));
                              }}
                              disabled={isBusy}
                            />
                          </label>

                          <button
                            type="button"
                            className="outline-button"
                            onClick={() => void updateCartItemQuantity(item)}
                            aria-label={`Actualizar cantidad ${item.name}`}
                            disabled={isBusy}
                          >
                            {isUpdatingLine ? 'Guardando...' : 'Actualizar cantidad'}
                          </button>
                          <button
                            type="button"
                            className="ghost-button"
                            onClick={() => void removeCartItem(item)}
                            aria-label={`Eliminar linea ${item.name}`}
                            disabled={isBusy}
                          >
                            {isRemovingLine ? 'Eliminando...' : 'Eliminar linea'}
                          </button>
                        </div>
                      )}

                      {!canEditLines && <p className="muted-text">Linea bloqueada: el pedido ya no esta en estado carrito.</p>}

                      {lineFieldErrors[item.id] && <p className="field-error">{lineFieldErrors[item.id]}</p>}
                    </li>
                  );
                })}
              </ul>
            )}
          </>
        )}
      </article>

      {message && <p className="feedback success">{message}</p>}
      {error && <p className="feedback error">{error}</p>}
    </section>
  );
}
