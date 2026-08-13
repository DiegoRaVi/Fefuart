export function Cargando({ texto = 'Cargando...' }: { texto?: string }) {
  return (
    <div
      role="status"
      aria-live="polite"
      className="flex min-h-40 items-center justify-center p-8 text-piedra"
    >
      {texto}
    </div>
  )
}
