import { Boton } from '@/shared/ui/Boton'

interface Props {
  pagina: number
  ultima: number
  total: number
  onPagina: (pagina: number) => void
}

export function Paginacion({ pagina, ultima, total, onPagina }: Props) {
  if (ultima <= 1) {
    return <p className="text-sm text-piedra">{total} en total</p>
  }

  return (
    <nav aria-label="Paginacion" className="flex items-center justify-between gap-4">
      <Boton
        type="button"
        variante="secundario"
        disabled={pagina <= 1}
        onClick={() => onPagina(pagina - 1)}
      >
        Anteriores
      </Boton>

      <span className="text-base text-piedra">
        Pagina {pagina} de {ultima} · {total} en total
      </span>

      <Boton
        type="button"
        variante="secundario"
        disabled={pagina >= ultima}
        onClick={() => onPagina(pagina + 1)}
      >
        Siguientes
      </Boton>
    </nav>
  )
}
