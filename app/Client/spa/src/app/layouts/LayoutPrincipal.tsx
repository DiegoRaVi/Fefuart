import { Outlet } from 'react-router'

import { Cabecera } from './Cabecera'
import { PieDePagina } from './PieDePagina'

export function LayoutPrincipal() {
  return (
    <div className="flex min-h-screen flex-col">
      <Cabecera />

      <main className="mx-auto w-full max-w-6xl flex-1 px-6 py-10">
        <Outlet />
      </main>

      <PieDePagina />
    </div>
  )
}
