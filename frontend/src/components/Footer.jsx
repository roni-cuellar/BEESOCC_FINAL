import { Link } from 'react-router-dom'
import { useState } from 'react'
import logo from '../assets/branding/logo.png'
function Footer() {
  const [anio] = useState(()=>new Date().getFullYear())
  return <footer className="bg-marca-oscuro text-white"><div className="mx-auto grid max-w-7xl gap-7 px-5 py-10 sm:grid-cols-2 sm:px-8 lg:grid-cols-[1fr_1fr_auto] lg:items-center lg:px-10"><Link className="marca-logo" to="/" aria-label="BEESOCC, volver al inicio"><img src={logo} alt="BEESOCC" width="1448" height="1086" loading="lazy" /></Link><p className="max-w-xs text-xs leading-6 tracking-wider text-green-100">TODO TU CAMPEONATO EN UN SOLO LUGAR.</p><Link className="flex min-h-11 items-center text-sm font-medium text-green-100 hover:text-white hover:underline" to="/#como-funciona">Conoce la plataforma</Link></div><div className="border-t border-white/10"><div className="mx-auto flex max-w-7xl flex-wrap justify-between gap-3 px-5 py-5 text-xs text-green-100 sm:px-8 lg:px-10"><span>© {anio} BEESOCC 2.0</span><span>Hecho para el fútbol local.</span></div></div></footer>
}
export default Footer
