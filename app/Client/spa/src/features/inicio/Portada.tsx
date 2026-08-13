/** N1 — los cuatro servicios del negocio. */
const SERVICIOS = [
  {
    nombre: 'Live Art',
    descripcion:
      'Espectaculo de dibujo en directo para bodas y eventos. Cada evento es distinto, asi que el presupuesto es a medida.',
  },
  {
    nombre: 'Dibujo por encargo',
    descripcion: 'Retratos dibujados a partir de tu fotografia, en tres estilos.',
  },
  {
    nombre: 'Letras infantiles',
    descripcion: 'Letras ilustradas para la habitacion de los peques.',
  },
  {
    nombre: 'Ramos dibujados',
    descripcion: 'Tu ramo de novia convertido en lamina.',
  },
]

export function Portada() {
  return (
    <div className="space-y-12">
      <section className="space-y-4">
        <h1 className="text-titulo text-verde">Fefuart</h1>
        <p className="max-w-2xl">
          Felicitas Varela dibuja en directo en bodas y eventos, y por encargo a partir de
          tus fotografias.
        </p>
      </section>

      <section aria-labelledby="servicios" className="space-y-6">
        <h2 id="servicios" className="text-seccion text-verde">
          Que hacemos
        </h2>

        <ul className="grid gap-6 sm:grid-cols-2">
          {SERVICIOS.map((servicio) => (
            <li
              key={servicio.nombre}
              className="rounded-fefu bg-rosa-suave p-6 text-piedra"
            >
              <h3 className="text-apartado text-verde">{servicio.nombre}</h3>
              <p className="mt-2 text-base">{servicio.descripcion}</p>
            </li>
          ))}
        </ul>

        {/* El catalogo con precios y el formulario de encargo llegan en la
            Fase 4, contra los endpoints que ya entrego la Fase 2. */}
      </section>
    </div>
  )
}
