/**
 * El footer de v1, con sus datos de contacto reales. Mismo cambio que en la
 * cabecera: sobre el rosa se escribe en verde, no en blanco.
 */
export function PieDePagina() {
  return (
    <footer className="mt-16 bg-rosa text-verde">
      <div className="mx-auto flex max-w-6xl flex-col gap-6 px-6 py-8 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="text-apartado font-bold">Contacta con Feli Varela</h2>

          <ul className="mt-3 space-y-1">
            <li>
              Tel:{' '}
              <a className="underline underline-offset-4 hover:text-verde-hondo" href="tel:+34614135975">
                +34 614 13 59 75
              </a>
            </li>
            <li>
              Email:{' '}
              <a
                className="underline underline-offset-4 hover:text-verde-hondo"
                href="mailto:fefuartist@gmail.com"
              >
                fefuartist@gmail.com
              </a>
            </li>
          </ul>
        </div>

        <a
          href="https://www.instagram.com/fefu_art/"
          target="_blank"
          rel="noreferrer noopener"
          className="underline underline-offset-4 hover:text-verde-hondo"
        >
          Instagram @fefu_art
        </a>
      </div>
    </footer>
  )
}
