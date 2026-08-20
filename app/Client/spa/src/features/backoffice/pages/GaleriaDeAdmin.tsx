import { useState } from 'react'

import {
  CATEGORIAS,
  nombreDeCategoria,
  useBorrarPieza,
  useCambiarPieza,
  useGaleriaDeAdmin,
  useSubirPieza,
  type Categoria,
  type PiezaDeGaleria,
} from '@/features/galeria/api'
import { Aviso } from '@/shared/ui/Aviso'
import { Boton } from '@/shared/ui/Boton'
import { Campo } from '@/shared/ui/Campo'
import { Cargando } from '@/shared/ui/Cargando'

/**
 * D33 — la galeria la mantiene Felicitas.
 *
 * Aqui si sale lo no publicado: es la vista de quien la gestiona, no el
 * escaparate. Despublicar es lo normal y borrar es lo raro — una pieza que
 * deja de gustar se esconde, no se tira, porque tirarla se lleva el fichero.
 */
export function GaleriaDeAdmin() {
  const { data, isPending, isError, error } = useGaleriaDeAdmin()

  return (
    <div className="space-y-6">
      <h1 className="text-titulo text-verde">Galería</h1>

      <NuevaPieza />

      {isPending && <Cargando texto="Cargando la galeria..." />}
      {isError && <Aviso tono="error">{error.message}</Aviso>}

      {data && data.length === 0 && (
        <Aviso tono="informacion">
          La galería esta vacía. Sube la primera pieza aquí arriba.
        </Aviso>
      )}

      {data && data.length > 0 && (
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {data.map((pieza) => (
            <li key={pieza.id}>
              <Pieza pieza={pieza} />
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function NuevaPieza() {
  const subir = useSubirPieza()
  const [titulo, setTitulo] = useState('')
  const [categoria, setCategoria] = useState<Categoria>('dibujo')
  const [archivo, setArchivo] = useState<File | null>(null)

  return (
    <form
      className="space-y-3 rounded-fefu bg-rosa-suave p-4"
      onSubmit={(e) => {
        e.preventDefault()

        if (archivo) {
          subir.mutate(
            { file: archivo, title: titulo, category: categoria },
            {
              onSuccess: () => {
                setTitulo('')
                setArchivo(null)
              },
            },
          )
        }
      }}
    >
      <h2 className="text-apartado text-verde">Añadir una pieza</h2>

      <Campo
        etiqueta="Título"
        value={titulo}
        onChange={(e) => setTitulo(e.target.value)}
        required
        maxLength={120}
      />

      <div className="space-y-1">
        <label htmlFor="categoria" className="block font-bold text-verde">
          Categoría
        </label>

        <select
          id="categoria"
          value={categoria}
          onChange={(e) => setCategoria(e.target.value as Categoria)}
          className="w-full rounded-fefu border border-piedra/40 px-3 py-2"
        >
          {CATEGORIAS.map((c) => (
            <option key={c.valor} value={c.valor}>
              {c.etiqueta}
            </option>
          ))}
        </select>
      </div>

      <div className="space-y-1">
        <label htmlFor="imagen" className="block font-bold text-verde">
          Imagen
        </label>

        <input
          id="imagen"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          required
          onChange={(e) => setArchivo(e.target.files?.[0] ?? null)}
          className="block w-full text-sm text-piedra"
        />

        {/* Se dice el tamano al que se guarda: sus fotos son de movil y de
            varios MB, y conviene que sepa que no se sirven asi. */}
        <p className="text-sm text-piedra">
          Se guarda a 1600 px para el visor y 600 px para la rejilla.
        </p>
      </div>

      <Boton type="submit" disabled={subir.isPending || !archivo}>
        {subir.isPending ? 'Subiendo...' : 'Añadir a la galería'}
      </Boton>

      {subir.isError && <Aviso tono="error">{subir.error.message}</Aviso>}
    </form>
  )
}

function Pieza({ pieza }: { pieza: PiezaDeGaleria }) {
  const cambiar = useCambiarPieza()
  const borrar = useBorrarPieza()
  const [confirmando, setConfirmando] = useState(false)

  return (
    <article className="space-y-2 rounded-fefu bg-rosa-suave p-3">
      {pieza.thumbnail?.url && (
        <img
          src={pieza.thumbnail.url}
          alt={pieza.title}
          loading="lazy"
          className={`aspect-square w-full rounded-fefu object-cover ${
            pieza.is_published ? '' : 'opacity-50'
          }`}
        />
      )}

      <h3 className="text-apartado text-verde">{pieza.title}</h3>
      <p className="text-sm text-piedra">
        {nombreDeCategoria(pieza.category)}
        {!pieza.is_published && ' · sin publicar'}
      </p>

      <div className="flex flex-wrap gap-2">
        <Boton
          type="button"
          variante="secundario"
          disabled={cambiar.isPending}
          onClick={() => cambiar.mutate({ id: pieza.id, is_published: !pieza.is_published })}
        >
          {pieza.is_published ? 'Ocultar' : 'Publicar'}
        </Boton>

        {/* Borrar se lleva el fichero del disco, asi que se pregunta. */}
        {confirmando ? (
          <>
            <Boton
              type="button"
              disabled={borrar.isPending}
              onClick={() => borrar.mutate(pieza.id)}
            >
              Borrar de verdad
            </Boton>
            <Boton type="button" variante="secundario" onClick={() => setConfirmando(false)}>
              Dejarlo
            </Boton>
          </>
        ) : (
          <Boton type="button" variante="secundario" onClick={() => setConfirmando(true)}>
            Borrar
          </Boton>
        )}
      </div>

      {cambiar.isError && <Aviso tono="error">{cambiar.error.message}</Aviso>}
      {borrar.isError && <Aviso tono="error">{borrar.error.message}</Aviso>}
    </article>
  )
}
