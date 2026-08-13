import type { ButtonHTMLAttributes, ReactNode } from 'react'

type Variante = 'principal' | 'secundario'

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variante?: Variante
  children: ReactNode
}

/**
 * El boton de v1: fondo piedra, verde al pasar por encima, 0.5rem de radio.
 */
const ESTILOS: Record<Variante, string> = {
  principal:
    'bg-piedra text-white hover:bg-verde focus-visible:bg-verde disabled:bg-piedra/50',
  secundario:
    'border border-piedra text-piedra hover:bg-rosa-suave disabled:opacity-50',
}

export function Boton({ variante = 'principal', className = '', ...props }: Props) {
  return (
    <button
      {...props}
      className={`rounded-fefu px-4 py-2 transition-colors duration-300 disabled:cursor-not-allowed ${ESTILOS[variante]} ${className}`}
    />
  )
}
