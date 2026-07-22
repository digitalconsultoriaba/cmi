// Landing do Congresso servida na raiz do app (localhost:5173/).
// A landing é estática (cms) e mora em public/landing/; renderizada em iframe
// full-screen para manter o `/` como URL. Links abrem no topo (base target=_top).
export default function LandingHome() {
  return (
    <iframe
      title="Congresso Internacional da Maçonaria 2026"
      src="/landing/Landing.dc.html"
      style={{ position: 'fixed', inset: 0, width: '100%', height: '100%', border: 0 }}
    />
  )
}
