import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiRequest, buildQueryString } from '../../shared/api/client';

type CatalogProduct = {
  id: number;
  name: string;
  description: string | null;
  price: number;
  category: string;
  subcategory: string | null;
  delivery_type: string;
  delivery_time: string;
};

export function CatalogPage() {
  const [category, setCategory] = useState('');
  const [subcategory, setSubcategory] = useState('');

  const queryString = useMemo(
    () => buildQueryString({ category, subcategory, per_page: 50 }),
    [category, subcategory]
  );

  const productsQuery = useQuery({
    queryKey: ['catalog-products', category, subcategory],
    queryFn: async () => {
      const response = await apiRequest<{ products: CatalogProduct[] }>(`/catalog/products${queryString}`);
      return response.data.products;
    },
  });

  return (
    <section className="stack-gap-large">
      <header className="panel stack-gap">
        <h1>Catalogo v1</h1>
        <p>Busqueda publica de productos de catalogo. Filtra por categoria y subcategoria para validar contrato API.</p>

        <div className="filters-row">
          <label className="field">
            <span>Categoria</span>
            <input value={category} onChange={(event) => setCategory(event.target.value)} placeholder="dibujo-encargo" />
          </label>

          <label className="field">
            <span>Subcategoria</span>
            <input value={subcategory} onChange={(event) => setSubcategory(event.target.value)} placeholder="digital" />
          </label>
        </div>
      </header>

      {productsQuery.isLoading && <p className="panel">Cargando catalogo...</p>}
      {productsQuery.isError && <p className="panel error">No se pudo cargar el catalogo.</p>}

      {productsQuery.data && (
        <div className="card-grid">
          {productsQuery.data.map((product) => (
            <article key={product.id} className="catalog-card">
              <div className="catalog-price">{product.price.toFixed(2)} EUR</div>
              <h2>{product.name}</h2>
              <p>{product.description ?? 'Sin descripcion'}</p>
              <dl>
                <div>
                  <dt>Categoria</dt>
                  <dd>{product.category}</dd>
                </div>
                <div>
                  <dt>Subcategoria</dt>
                  <dd>{product.subcategory ?? 'n/a'}</dd>
                </div>
                <div>
                  <dt>Entrega</dt>
                  <dd>{product.delivery_type}</dd>
                </div>
                <div>
                  <dt>Tiempo</dt>
                  <dd>{product.delivery_time}</dd>
                </div>
              </dl>
            </article>
          ))}

          {productsQuery.data.length === 0 && <p className="panel">No hay productos para ese filtro.</p>}
        </div>
      )}
    </section>
  );
}
