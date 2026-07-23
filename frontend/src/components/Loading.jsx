import './Loading.css'

/**
 * Estado de carregamento com a logo do projeto centralizada e fade in/out.
 * `fullscreen` (padrão) cobre a viewport — usado nos loaders de navegação;
 * `fullscreen={false}` centraliza dentro da área de conteúdo.
 */
export default function Loading({ fullscreen = true }) {
  return (
    <div className={fullscreen ? 'app-loading app-loading--full' : 'app-loading'}>
      <img src="/favicon-512x512.png" alt="Carregando…" className="app-loading__logo" />
    </div>
  )
}
