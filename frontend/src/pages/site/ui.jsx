import { useEffect, useRef, useState } from 'react'

/** Countdown regressivo (1s) a partir de uma data ISO. Data ausente/passada → zerado. */
export function Countdown({ target }) {
  const [left, setLeft] = useState(() => diff(target))
  useEffect(() => {
    setLeft(diff(target))
    const id = setInterval(() => setLeft(diff(target)), 1000)
    return () => clearInterval(id)
  }, [target])

  const boxes = [['Dias', left.d], ['Horas', left.h], ['Min', left.m], ['Seg', left.s]]
  return (
    <div className="site-countdown">
      {boxes.map(([label, v]) => (
        <div className="site-count-box" key={label}>
          <b>{String(v).padStart(2, '0')}</b>
          <span>{label}</span>
        </div>
      ))}
    </div>
  )
}
function diff(target) {
  if (!target) return { d: 0, h: 0, m: 0, s: 0 }
  const ms = new Date(target).getTime() - Date.now()
  if (!Number.isFinite(ms) || ms <= 0) return { d: 0, h: 0, m: 0, s: 0 }
  const s = Math.floor(ms / 1000)
  return { d: Math.floor(s / 86400), h: Math.floor((s % 86400) / 3600), m: Math.floor((s % 3600) / 60), s: s % 60 }
}

/** Contador que anima 0 → value ao entrar na tela (IntersectionObserver). */
export function Counter({ value = 0 }) {
  const ref = useRef(null)
  const [n, setN] = useState(0)
  useEffect(() => {
    const el = ref.current
    if (!el) return
    const io = new IntersectionObserver(([e]) => {
      if (!e.isIntersecting) return
      io.disconnect()
      const target = Number(value) || 0
      const start = performance.now()
      const tick = (t) => {
        const p = Math.min(1, (t - start) / 1200)
        setN(Math.round(target * (1 - Math.pow(1 - p, 3))))
        if (p < 1) requestAnimationFrame(tick)
      }
      requestAnimationFrame(tick)
    }, { threshold: 0.4 })
    io.observe(el)
    return () => io.disconnect()
  }, [value])
  return <span ref={ref} className="num">{n}</span>
}

/** Revela filhos em stagger ao entrar na tela. */
export function useReveal() {
  const ref = useRef(null)
  useEffect(() => {
    const el = ref.current
    if (!el) return
    const items = el.querySelectorAll('.prog-item.enter')
    const io = new IntersectionObserver((entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting) {
          const i = [...items].indexOf(e.target)
          setTimeout(() => e.target.classList.add('show'), i * 90)
          io.unobserve(e.target)
        }
      })
    }, { threshold: 0.2 })
    items.forEach((it) => io.observe(it))
    return () => io.disconnect()
  }, [])
  return ref
}

/** Carrossel paginado (setas + dots), N por página conforme a largura. */
export function Carousel({ items, perPage = 4, render }) {
  const [page, setPage] = useState(0)
  const [pp, setPp] = useState(perPage)
  useEffect(() => {
    const calc = () => setPp(window.innerWidth < 640 ? 1 : window.innerWidth < 1040 ? 2 : perPage)
    calc()
    window.addEventListener('resize', calc)
    return () => window.removeEventListener('resize', calc)
  }, [perPage])

  const pages = Math.max(1, Math.ceil(items.length / pp))
  const cur = Math.min(page, pages - 1)
  const slice = items.slice(cur * pp, cur * pp + pp)

  return (
    <div>
      <div className="site-grid" style={{ gridTemplateColumns: `repeat(${pp}, 1fr)` }}>
        {slice.map((it, i) => render(it, i))}
      </div>
      {pages > 1 && (
        <div className="site-carousel-nav">
          <button className="site-arrow" onClick={() => setPage((p) => Math.max(0, p - 1))}>‹</button>
          {Array.from({ length: pages }).map((_, i) => (
            <button key={i} className={`site-dot ${i === cur ? 'on' : ''}`} onClick={() => setPage(i)} />
          ))}
          <button className="site-arrow" onClick={() => setPage((p) => Math.min(pages - 1, p + 1))}>›</button>
        </div>
      )}
    </div>
  )
}

/** Accordion de item (FAQ). */
export function Accordion({ items, q, a }) {
  const [open, setOpen] = useState(null)
  return (
    <div>
      {items.map((it, i) => (
        <div className={`faq-item ${open === i ? 'open' : ''}`} key={i}>
          <div className="faq-q" onClick={() => setOpen(open === i ? null : i)}>
            <span>{q(it)}</span><span className="chev">▾</span>
          </div>
          <div className="faq-a"><p>{a(it)}</p></div>
        </div>
      ))}
    </div>
  )
}

/** Modal simples da landing. */
export function SiteModal({ onClose, children }) {
  useEffect(() => {
    const onEsc = (e) => e.key === 'Escape' && onClose()
    window.addEventListener('keydown', onEsc)
    return () => window.removeEventListener('keydown', onEsc)
  }, [onClose])
  return (
    <div className="site-modal-bg" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className="site-modal">
        <button className="close" onClick={onClose}>×</button>
        {children}
      </div>
    </div>
  )
}

/** Seletor de idioma. */
export function LanguageSwitcher({ languages, current, onChange }) {
  const FLAG = { pt: 'PT', en: 'EN', es: 'ES' }
  if (!languages || languages.length < 2) return null
  return (
    <div className="lang-switch">
      {languages.map((l) => (
        <button key={l} className={l === current ? 'on' : ''} onClick={() => onChange(l)}>{FLAG[l] || l.toUpperCase()}</button>
      ))}
    </div>
  )
}
