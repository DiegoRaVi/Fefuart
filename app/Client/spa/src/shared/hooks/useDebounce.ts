import { useEffect, useState } from 'react'

/**
 * Retrasa el valor hasta que deja de cambiar.
 *
 * Sin esto, el buscador dispara una peticion por tecla: escribir «marta» son
 * cinco consultas de las que solo importa la ultima, y cada una hace un
 * escaneo con comodin por delante. Ademas cuenta contra el limitador de la
 * API (SEC-007).
 */
export function useDebounce<T>(valor: T, milisegundos = 300): T {
  const [retrasado, setRetrasado] = useState(valor)

  useEffect(() => {
    const id = setTimeout(() => setRetrasado(valor), milisegundos)

    return () => clearTimeout(id)
  }, [valor, milisegundos])

  return retrasado
}
