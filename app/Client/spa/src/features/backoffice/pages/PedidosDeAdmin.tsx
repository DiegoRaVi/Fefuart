import { useState } from 'react'
import { Link } from 'react-router'

import { ESTADOS, nombreDelEstado } from '@/features/orders/api'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { euros } from '@/shared/lib/dinero'
import { Aviso } from '@/shared/ui/Aviso'
import { Cargando } from '@/shared/ui/Cargando'

import { usePedidosDeAdmin } from '../api'
import { Resumen } from '../components/Resumen'
import type { CampoDeBusqueda } from '../components/BusquedaPrecisa'
import { Filtros } from '../components/Filtros'
import { Paginacion } from '../components/Paginacion'

/** Un carrito abierto no es un pedido: el listado no los trae. */
const ESTADOS_DE_PEDIDO = Object.fromEntries(
  Object.entries(ESTADOS).filter(([valor]) => valor !== 'cart'),
)

/**
 * Los datos por los que se puede buscar de forma precisa. Tienen que cuadrar
 * con lo que admite `buscar_por` en el backend; si aqui apareciera uno mas,
 * el servidor lo rechazaria con 422.
 *
 * «Nombre» mira en el de la cuenta y en el del envio: quien busca no sabe
 * cual le han dado, y separarlos le trasladaria el problema.
 */
const CAMPOS: CampoDeBusqueda[] = [
  { valor: 'numero', etiqueta: 'Número de pedido', ayuda: '7', tipo: 'number' },
  { valor: 'nombre', etiqueta: 'Nombre', ayuda: 'De la cuenta o del envío' },
  { valor: 'email', etiqueta: 'Correo', ayuda: 'marta@ejemplo.com', tipo: 'email' },
  { valor: 'telefono', etiqueta: 'Teléfono', ayuda: '600123456', tipo: 'tel' },
]

export function PedidosDeAdmin() {
  const [q, setQ] = useState('')
  const [campo, setCampo] = useState<string | null>(null)
  const [estado, setEstado] = useState('')
  const [desde, setDesde] = useState('')
  const [hasta, setHasta] = useState('')
  const [pagina, setPagina] = useState(1)

  // Una peticion por tecla seria escanear la tabla cinco veces para escribir
  // «marta», y contaria contra el limitador de la API.
  const busqueda = useDebounce(q)

  const filtros = {
    q: busqueda,
    buscar_por: campo ?? '',
    status: estado,
    desde,
    hasta,
    page: pagina,
  }
  const { data, isPending, isError, error, isFetching } = usePedidosDeAdmin(filtros)

  const hayFiltros = Boolean(q || estado || desde || hasta)

  function limpiar() {
    setQ('')
    setCampo(null)
    setEstado('')
    setDesde('')
    setHasta('')
    setPagina(1)
  }

  /** Cualquier cambio de filtro vuelve a la primera pagina. */
  function cambiar(setter: (v: string) => void) {
    return (valor: string) => {
      setter(valor)
      setPagina(1)
    }
  }

  return (
    <div className="space-y-6">
      <h1 className="text-titulo text-verde">Pedidos</h1>

      {/* El resumen vive aqui y no en una pantalla propia: pedidos es la
          primera pantalla del backoffice, asi que es lo que se ve al entrar
          sin tener que ir a buscarlo. */}
      <Resumen />

      <Filtros
        q={q}
        onQ={(valor) => {
          setQ(valor)
          // Escribir en la caja deshace la acotacion: la caja mira en todo.
          setCampo(null)
          setPagina(1)
        }}
        campo={campo}
        onCampo={(nuevoCampo, termino) => {
          setCampo(nuevoCampo)
          setQ(termino)
          setPagina(1)
        }}
        campos={CAMPOS}
        estado={estado}
        onEstado={cambiar(setEstado)}
        estados={ESTADOS_DE_PEDIDO}
        desde={desde}
        onDesde={cambiar(setDesde)}
        hasta={hasta}
        onHasta={cambiar(setHasta)}
        ayudaBusqueda="Número de pedido, nombre, correo o teléfono"
        hayFiltros={hayFiltros}
        onLimpiar={limpiar}
      />

      {isError && <Aviso tono="error">{error.message}</Aviso>}

      {isPending || !data ? (
        <Cargando texto="Cargando los pedidos..." />
      ) : data.data.length === 0 ? (
        <Aviso tono="informacion">
          {hayFiltros
            ? 'Ningun pedido cuadra con lo que buscas.'
            : 'Todavía no hay pedidos.'}
        </Aviso>
      ) : (
        <>
          {/* `isFetching` con datos ya en pantalla: se avisa sin vaciar la
              tabla, que es lo que hacia que el contenido saltase al teclear. */}
          <div aria-busy={isFetching} className={isFetching ? 'opacity-60' : undefined}>
            <table className="w-full text-left text-base">
              <thead className="border-b border-piedra/20 text-sm text-piedra">
                <tr>
                  <th scope="col" className="py-2">
                    Pedido
                  </th>
                  <th scope="col">Cliente</th>
                  <th scope="col">Fecha</th>
                  <th scope="col">Estado</th>
                  <th scope="col" className="text-right">
                    Total
                  </th>
                </tr>
              </thead>

              <tbody>
                {data.data.map((pedido) => (
                  <tr key={pedido.id} className="border-b border-piedra/10">
                    <td className="py-3">
                      <Link
                        to={`/backoffice/pedidos/${pedido.id}`}
                        className="text-verde underline underline-offset-4"
                      >
                        #{pedido.id}
                      </Link>
                      <span className="block text-sm text-piedra">
                        {pedido.items_count}{' '}
                        {pedido.items_count === 1 ? 'encargo' : 'encargos'}
                      </span>
                    </td>

                    <td>
                      {pedido.customer?.name}
                      <span className="block text-sm text-piedra">
                        {pedido.customer?.email}
                      </span>
                    </td>

                    <td className="text-sm">
                      {pedido.placed_at &&
                        new Date(pedido.placed_at).toLocaleDateString('es-ES')}
                    </td>

                    <td>
                      <span className="rounded-fefu bg-rosa px-2 py-1 text-sm text-verde">
                        {nombreDelEstado(pedido.status)}
                      </span>
                    </td>

                    <td className="text-right">{euros(pedido.total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <Paginacion
            pagina={data.meta.current_page}
            ultima={data.meta.last_page}
            total={data.meta.total}
            onPagina={setPagina}
          />
        </>
      )}
    </div>
  )
}
