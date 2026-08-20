import { useState } from 'react'

import type { Product, ProductVariant } from '@/shared/api/types'
import { euros } from '@/shared/lib/dinero'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Cargando } from '@/shared/ui/Cargando'

import { useCambiarVariante, useCatalogoDeAdmin, useSubirFotoDeProducto } from '../api'
import { Paginacion } from '../components/Paginacion'

/**
 * D5 — el motivo por el que esto existe: Felicitas cambia precios sin tocar
 * codigo. En v1 vivian en el `<script>` de cada formulario, asi que cambiar
 * uno era editar HTML.
 */
export function CatalogoDeAdmin() {
  const [pagina, setPagina] = useState(1)
  const { data, isPending, isError, error } = useCatalogoDeAdmin(pagina)

  if (isPending) {
    return <Cargando texto="Cargando el catalogo..." />
  }

  if (isError) {
    return <Aviso tono="error">{error.message}</Aviso>
  }

  return (
    <div className="space-y-6">
      <div className="space-y-2">
        <h1 className="text-titulo text-verde">Catalogo</h1>
        <p className="text-base text-piedra">
          Cambiar un precio no toca los pedidos ya hechos: cada linea guarda el
          suyo.
        </p>
      </div>

      <ul className="space-y-4">
        {data.data.map((producto) => (
          <li key={producto.id}>
            <Producto producto={producto} />
          </li>
        ))}
      </ul>

      {/* El endpoint pagina de 25. Sin esto, un catalogo mas largo perderia
          productos en silencio, que es peor que tenerlos en dos paginas. */}
      <Paginacion
        pagina={data.meta.current_page}
        ultima={data.meta.last_page}
        total={data.meta.total}
        onPagina={setPagina}
      />
    </div>
  )
}

function Producto({ producto }: { producto: Product }) {
  return (
    <article className="space-y-3 rounded-fefu bg-rosa-suave p-4">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-apartado text-verde">{producto.name}</h2>
        <span className="text-sm text-piedra">
          {producto.requires_reference_image && 'Necesita foto · '}
          Hasta {producto.max_quantity} copias · {producto.delivery_days} dias
        </span>
      </div>

      <FotoDelProducto producto={producto} />

      <ul className="space-y-2">
        {producto.variants.map((variante) => (
          <li key={variante.id}>
            <Variante variante={variante} />
          </li>
        ))}
      </ul>
    </article>
  )
}

/**
 * La foto que ve el cliente en el catalogo y en la ficha.
 *
 * Entra tras la auditoria de UX del 2026-08-20: la tienda vendia dibujos sin
 * enseñar ninguno y el precio aparecia antes que el producto.
 */
function FotoDelProducto({ producto }: { producto: Product }) {
  const subir = useSubirFotoDeProducto(producto.id)
  const idCampo = `foto-${producto.id}`

  return (
    <div className="flex flex-wrap items-start gap-4 rounded-fefu bg-white/60 p-3">
      {producto.image?.url ? (
        <img
          src={producto.image.url}
          alt=""
          className="h-24 w-32 rounded-fefu object-cover"
        />
      ) : (
        <p className="flex h-24 w-32 items-center justify-center rounded-fefu border border-dashed border-piedra/40 text-center text-sm text-piedra">
          Sin foto
        </p>
      )}

      <div className="min-w-48 flex-1 space-y-1">
        <label htmlFor={idCampo} className="block text-sm font-bold text-verde">
          {producto.image ? 'Cambiar la foto' : 'Subir una foto'}
        </label>

        <input
          id={idCampo}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          disabled={subir.isPending}
          onChange={(e) => {
            const archivo = e.target.files?.[0]

            if (archivo) {
              subir.mutate(archivo)
            }

            // Se limpia para poder reintentar con el mismo fichero: sin esto,
            // `change` no se dispara la segunda vez.
            e.target.value = ''
          }}
          className="block w-full text-sm text-piedra"
        />

        <p className="text-sm text-piedra">
          Es lo primero que ve quien entra en la tienda. Se guarda a 1600 px.
        </p>

        {subir.isPending && <p className="text-sm text-piedra">Subiendo...</p>}
        {subir.isError && <Aviso tono="error">{subir.error.message}</Aviso>}
      </div>
    </div>
  )
}

function Variante({ variante }: { variante: ProductVariant }) {
  const cambiar = useCambiarVariante()

  const [editando, setEditando] = useState(false)
  const [precio, setPrecio] = useState(variante.price)
  const [copia, setCopia] = useState(variante.additional_copy_price)

  async function guardar() {
    await cambiar.mutateAsync({
      id: variante.id,
      price: precio,
      additional_copy_price: copia,
    })

    setEditando(false)
  }

  if (!editando) {
    return (
      <div className="flex flex-wrap items-baseline justify-between gap-3 rounded-fefu bg-white/60 p-3">
        <div>
          <p className="text-base">{variante.name}</p>
          <p className="text-sm text-piedra">
            {variante.shipping_methods.map((m) => m.name).join(' · ')}
          </p>
        </div>

        <div className="flex items-baseline gap-4">
          <span className="text-base text-verde">
            {euros(variante.price)}
            {Number(variante.additional_copy_price) > 0 && (
              <span className="text-sm text-piedra">
                {' '}
                + {euros(variante.additional_copy_price)} por copia
              </span>
            )}
          </span>

          <Boton type="button" variante="secundario" onClick={() => setEditando(true)}>
            Cambiar
          </Boton>
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-3 rounded-fefu bg-white/60 p-3">
      <p className="text-base">{variante.name}</p>

      {cambiar.isError && <Aviso tono="error">{cambiar.error.message}</Aviso>}

      <div className="flex flex-wrap items-end gap-3">
        <label className="text-sm text-piedra">
          Precio
          <input
            type="text"
            inputMode="decimal"
            value={precio}
            onChange={(e) => setPrecio(e.target.value)}
            className="mt-1 block w-28 rounded-fefu border border-piedra/40 px-2 py-1 text-base"
          />
        </label>

        <label className="text-sm text-piedra">
          Copia adicional
          <input
            type="text"
            inputMode="decimal"
            value={copia}
            onChange={(e) => setCopia(e.target.value)}
            className="mt-1 block w-28 rounded-fefu border border-piedra/40 px-2 py-1 text-base"
          />
        </label>

        <Boton type="button" onClick={guardar} disabled={cambiar.isPending}>
          {cambiar.isPending ? 'Guardando...' : 'Guardar'}
        </Boton>

        <Boton
          type="button"
          variante="secundario"
          onClick={() => {
            setPrecio(variante.price)
            setCopia(variante.additional_copy_price)
            setEditando(false)
          }}
        >
          Dejarlo
        </Boton>
      </div>

      {/* Los importes viajan como cadena decimal, igual que llegan: el
          servidor los valida con `decimal:0,2` y los calcula en centimos
          enteros. Convertirlos a numero aqui seria pasar por float. */}
    </div>
  )
}
