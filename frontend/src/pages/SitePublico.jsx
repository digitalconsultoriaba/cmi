import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../lib/api'
import './site/site.css'
import { Countdown, Counter, Carousel, Accordion, SiteModal, LanguageSwitcher, useReveal } from './site/ui'

const THEME_VARS = ['bg', 'bgEnd', 'surface', 'slate', 'accent', 'accentHover', 'textLight', 'textTitle', 'textCream', 'textMuted', 'blue']

export default function SitePublico() {
  const { slug } = useParams()
  const [lang, setLang] = useState('pt')

  const { data, isLoading, isError } = useQuery({
    queryKey: ['public-site', slug, lang],
    queryFn: () => apiGet(`/public/sites/${slug}?lang=${lang}`),
    retry: false,
  })

  useEffect(() => {
    if (data?.seo?.title) document.title = data.seo.title
  }, [data])

  if (isLoading) return <div style={{ minHeight: '100vh', background: '#071E2E' }} />
  if (isError || !data) return <div style={{ minHeight: '100vh', background: '#071E2E', color: '#EAECF0', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>Site não encontrado.</div>

  const style = {}
  THEME_VARS.forEach((k) => { if (data.theme?.[k]) style[`--${k}`] = data.theme[k] })

  const byType = Object.fromEntries(data.sections.map((s) => [s.type, s]))
  const sec = (t) => byType[t]

  return (
    <div className="site-root" style={style}>
      <NavbarPub site={data} lang={lang} setLang={setLang} />
      {sec('hero') && <HeroPub s={sec('hero')} identity={data.identity} countdownAt={data.countdownAt} />}
      {sec('stats') && <StatsPub s={sec('stats')} />}
      {sec('about') && <SobrePub s={sec('about')} />}
      {sec('pillars') && <PilaresPub s={sec('pillars')} />}
      {sec('speakers') && <PalestrantesPub s={sec('speakers')} />}
      {sec('program') && <ProgramacaoPub s={sec('program')} />}
      {sec('local') && <LocalPub s={sec('local')} />}
      {sec('info') && <InfoPub s={sec('info')} />}
      {sec('sponsors') && <PatrocinadoresPub s={sec('sponsors')} />}
      {sec('testimonials') && <DepoimentosPub s={sec('testimonials')} />}
      {sec('faq') && <FaqPub s={sec('faq')} />}
      {sec('cta') && <CtaPub s={sec('cta')} />}
      <RodapePub s={sec('footer')} legal={sec('legal')} />
    </div>
  )
}

const P = (v) => (typeof v === 'string' ? v : '') // valor já resolvido no idioma

function SectionHead({ kicker, title }) {
  return (
    <div className="section-head">
      {kicker && <div className="site-kicker">{P(kicker)}</div>}
      {title && <h2>{P(title)}</h2>}
    </div>
  )
}

function NavbarPub({ site, lang, setLang }) {
  const [open, setOpen] = useState(false)
  const nav = site.sections.find((s) => s.type === 'navbar')?.payload || {}
  const anchors = Array.isArray(nav.anchors) ? nav.anchors : []
  return (
    <nav className="site-nav">
      <div className="site-wrap site-nav-inner">
        <div className="site-nav-brand">
          {site.identity?.logoPath && <img src={site.identity.logoPath} alt="" />}
          <span>{site.identity?.eventName}</span>
        </div>
        <button className="site-burger" onClick={() => setOpen((o) => !o)}>☰</button>
        <div className={`site-nav-links desktop`}>
          {anchors.map((a, i) => <a key={i} href={a.href}>{P(a.label)}</a>)}
          <LanguageSwitcher languages={site.availableLanguages} current={lang} onChange={setLang} />
          {nav.ctaLabel && <a className="site-btn site-btn-primary" href={nav.ctaHref}>{P(nav.ctaLabel)}</a>}
        </div>
        {open && (
          <div className="site-nav-links mobile">
            {anchors.map((a, i) => <a key={i} href={a.href} onClick={() => setOpen(false)}>{P(a.label)}</a>)}
            <LanguageSwitcher languages={site.availableLanguages} current={lang} onChange={setLang} />
            {nav.ctaLabel && <a className="site-btn site-btn-primary" href={nav.ctaHref}>{P(nav.ctaLabel)}</a>}
          </div>
        )}
      </div>
    </nav>
  )
}

function HeroPub({ s, identity, countdownAt }) {
  const p = s.payload
  return (
    <header className="site-hero">
      {identity?.watermarkPath && <div className="site-hero-watermark"><img src={identity.watermarkPath} alt="" /></div>}
      <div className="site-wrap" style={{ position: 'relative' }}>
        <h1>{P(p.titleLine1)}<br />{P(p.titleLine2)}<br />{P(p.titleLine3)}</h1>
        <p className="sub">{P(p.subtitle)}</p>
        <p className="meta">{P(p.dateText)} · {P(p.locationText)}</p>
        <Countdown target={countdownAt} />
        <div className="btn-list justify-content-center" style={{ display: 'flex', gap: 14, justifyContent: 'center', flexWrap: 'wrap' }}>
          {p.primaryLabel && <a className="site-btn site-btn-primary" href={p.primaryHref}>{P(p.primaryLabel)}</a>}
          {p.secondaryLabel && P(p.secondaryLabel) && <a className="site-btn site-btn-ghost" href={p.secondaryHref}>{P(p.secondaryLabel)}</a>}
        </div>
        {identity?.sealPaths?.length > 0 && (
          <div className="site-seals">{identity.sealPaths.map((u, i) => <img key={i} src={u} alt="" />)}</div>
        )}
      </div>
    </header>
  )
}

function StatsPub({ s }) {
  return (
    <section className="site-section"><div className="site-wrap">
      <div className="site-grid stats-grid">
        {s.items.map((it, i) => (
          <div className="site-card stat-card" key={i}>
            <Counter value={it.payload.value} />
            <div style={{ fontFamily: 'Oswald', marginTop: 6 }}>{P(it.payload.title)}</div>
            <div style={{ color: 'var(--textMuted)', fontSize: '.85rem' }}>{P(it.payload.subtitle)}</div>
          </div>
        ))}
      </div>
    </div></section>
  )
}

function SobrePub({ s }) {
  const p = s.payload
  const gallery = Array.isArray(p.gallery) ? p.gallery : []
  return (
    <section className="site-section" id="sobre"><div className="site-wrap about-grid">
      <div>
        <div className="site-kicker">{P(p.aboutLabel)}</div>
        <h2 style={{ color: 'var(--textTitle)' }}>{P(p.aboutTitle)}</h2>
        <p style={{ color: 'var(--textMuted)', whiteSpace: 'pre-line' }}>{P(p.aboutText)}</p>
        {p.aboutBtn && P(p.aboutBtn) && <a className="site-btn site-btn-ghost" href={p.aboutHref}>{P(p.aboutBtn)}</a>}
      </div>
      {gallery.length > 0 && <div className="about-gallery">{gallery.map((u, i) => <img key={i} src={u} alt="" />)}</div>}
    </div></section>
  )
}

function PilaresPub({ s }) {
  return (
    <section className="site-section"><div className="site-wrap">
      <SectionHead kicker={s.payload.pillarsLabel} />
      <div className="site-grid pillars-grid">
        {s.items.map((it, i) => (
          <div className="site-card" style={{ padding: 26 }} key={i}>
            <h3 style={{ color: 'var(--accent)' }}>{P(it.payload.title)}</h3>
            <ul style={{ color: 'var(--textMuted)', paddingLeft: 18 }}>
              {(Array.isArray(it.payload.items) ? it.payload.items : []).map((x, j) => <li key={j}>{x}</li>)}
            </ul>
          </div>
        ))}
      </div>
    </div></section>
  )
}

function PalestrantesPub({ s }) {
  const [open, setOpen] = useState(null)
  return (
    <section className="site-section" id="palestrantes"><div className="site-wrap">
      <SectionHead kicker={s.payload.speakersLabel} />
      <Carousel items={s.items} perPage={4} render={(it, i) => (
        <div className="site-card speaker-card" key={i} onClick={() => setOpen(it.payload)}>
          <div className="speaker-photo">
            {it.payload.photo ? <img src={it.payload.photo} alt="" /> : (it.payload.name || '?').slice(0, 1)}
          </div>
          <div className="speaker-body">
            <strong>{it.payload.name}</strong>
            <div className="role">{P(it.payload.role)}</div>
            <div style={{ color: 'var(--textMuted)', fontSize: '.85rem' }}>{it.payload.org}</div>
          </div>
        </div>
      )} />
      {open && (
        <SiteModal onClose={() => setOpen(null)}>
          {open.photo && <img src={open.photo} alt="" style={{ width: '100%', maxHeight: 320, objectFit: 'cover', borderRadius: 10 }} />}
          <h3 style={{ color: 'var(--textTitle)' }}>{open.name}</h3>
          <div className="role" style={{ color: 'var(--accent)' }}>{P(open.role)} · {open.org}</div>
          <p style={{ color: 'var(--textMuted)' }}>{P(open.talk)}</p>
          <p style={{ color: 'var(--blue)' }}>{open.day} {open.time}</p>
        </SiteModal>
      )}
    </div></section>
  )
}

const PROG_LABEL = { open: 'Abertura', talk: 'Palestra', debate: 'Debate', coffee: 'Coffee break', lunch: 'Almoço', workshop: 'Workshop', results: 'Resultados', coquetel: 'Coquetel' }

function ProgramacaoPub({ s }) {
  const ref = useReveal()
  return (
    <section className="site-section" id="programacao"><div className="site-wrap" ref={ref}>
      <SectionHead kicker={s.payload.progLabel} />
      {s.items.map((day, di) => (
        <div className="prog-day" key={di}>
          <h3>{P(day.payload.label)} <small style={{ color: 'var(--textMuted)' }}>{day.payload.date}</small></h3>
          {(day.children || []).map((it, i) => (
            <div className="prog-item enter" key={i}>
              <div className="prog-time">{it.payload.t1}{it.payload.t2 ? `–${it.payload.t2}` : ''}<br /><small style={{ color: 'var(--accent)' }}>{PROG_LABEL[it.payload.type] || it.payload.type}</small></div>
              <div>
                <strong>{P(it.payload.title)}</strong>
                {it.payload.speaker && <div style={{ color: 'var(--accent)', fontSize: '.85rem' }}>{it.payload.speaker}{it.payload.org ? ` · ${it.payload.org}` : ''}</div>}
                {P(it.payload.subtitle) && <div style={{ color: 'var(--textMuted)' }}>{P(it.payload.subtitle)}</div>}
                {P(it.payload.desc) && <p style={{ color: 'var(--textMuted)' }}>{P(it.payload.desc)}</p>}
                {Array.isArray(it.payload.activities) && it.payload.activities.length > 0 && (
                  <ul style={{ color: 'var(--textMuted)' }}>
                    {it.payload.activities.map((a, j) => <li key={j}><b>{P(a.label)}</b> {P(a.text)}</li>)}
                  </ul>
                )}
              </div>
            </div>
          ))}
        </div>
      ))}
    </div></section>
  )
}

function LocalPub({ s }) {
  const p = s.payload
  return (
    <section className="site-section" id="local"><div className="site-wrap about-grid">
      <div>
        <div className="site-kicker">{P(p.localLabel)}</div>
        <h2 style={{ color: 'var(--textTitle)' }}>{p.placeName}</h2>
        <p style={{ color: 'var(--textMuted)', whiteSpace: 'pre-line' }}>{P(p.localText)}</p>
        <p style={{ color: 'var(--textLight)' }}><strong>{p.venueName}</strong><br />{P(p.venueAddress)}</p>
        {p.mapHref && <a className="site-btn site-btn-ghost" href={p.mapHref} target="_blank" rel="noreferrer">{P(p.mapBtn) || 'Ver no mapa'}</a>}
      </div>
      {p.venuePhoto && <img src={p.venuePhoto} alt="" style={{ width: '100%', borderRadius: 12, objectFit: 'cover' }} />}
    </div></section>
  )
}

function InfoPub({ s }) {
  const [open, setOpen] = useState(null)
  return (
    <section className="site-section"><div className="site-wrap">
      <SectionHead kicker={s.payload.infoLabel} />
      <div className="site-grid info-grid">
        {s.items.map((it, i) => (
          <div className="site-card info-card" key={i} onClick={() => setOpen(it)}>
            <h3 style={{ color: 'var(--accent)' }}>{P(it.payload.title)}</h3>
            <p style={{ color: 'var(--textMuted)' }}>{P(it.payload.text)}</p>
            {it.payload.linkLabel && <span style={{ color: 'var(--accent)' }}>{P(it.payload.linkLabel)} →</span>}
          </div>
        ))}
      </div>
      {open && (
        <SiteModal onClose={() => setOpen(null)}>
          <h3 style={{ color: 'var(--textTitle)' }}>{P(open.payload.title)}</h3>
          <p style={{ color: 'var(--textMuted)' }}>{P(open.payload.modalText) || P(open.payload.text)}</p>
          {(open.children || []).map((c, i) => (
            <div className="site-card" style={{ padding: 14, marginBottom: 8 }} key={i}>
              <strong>{c.payload.name}</strong>
              <div style={{ color: 'var(--textMuted)', fontSize: '.9rem' }}>{P(c.payload.desc)}</div>
              {P(c.payload.address) && <div style={{ color: 'var(--textMuted)', fontSize: '.85rem' }}>{P(c.payload.address)}</div>}
              <div style={{ color: 'var(--blue)', fontSize: '.85rem' }}>{c.payload.phone} {c.payload.site}</div>
            </div>
          ))}
        </SiteModal>
      )}
    </div></section>
  )
}

function PatrocinadoresPub({ s }) {
  return (
    <section className="site-section"><div className="site-wrap">
      <SectionHead kicker={s.payload.sponsorsLabel} />
      {s.items.map((group, gi) => (
        <div key={gi} style={{ marginBottom: 30 }}>
          <div style={{ textAlign: 'center', color: 'var(--textMuted)', fontFamily: 'Oswald', letterSpacing: '.1em', marginBottom: 16 }}>{P(group.payload.title)}</div>
          <div className="site-grid sponsors-grid">
            {(group.children || []).map((logo, i) => (
              <a className="sponsor-logo" key={i} href={logo.payload.href || '#'} target="_blank" rel="noreferrer" style={{ textAlign: 'center' }}>
                {logo.payload.src ? <img src={logo.payload.src} alt={logo.payload.name} /> : <span style={{ color: 'var(--textMuted)' }}>{logo.payload.name}</span>}
              </a>
            ))}
          </div>
        </div>
      ))}
    </div></section>
  )
}

function DepoimentosPub({ s }) {
  return (
    <section className="site-section"><div className="site-wrap">
      <SectionHead kicker={s.payload.testiLabel} />
      <Carousel items={s.items} perPage={3} render={(it, i) => (
        <div className="site-card" style={{ padding: 24 }} key={i}>
          <p style={{ color: 'var(--textLight)', fontStyle: 'italic' }}>“{P(it.payload.text)}”</p>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 12 }}>
            {it.payload.photo && <img src={it.payload.photo} alt="" style={{ width: 42, height: 42, borderRadius: '50%', objectFit: 'cover' }} />}
            <div>
              <strong>{it.payload.name}</strong>
              <div style={{ color: 'var(--accent)', fontSize: '.82rem' }}>{P(it.payload.role)}</div>
            </div>
          </div>
        </div>
      )} />
    </div></section>
  )
}

function FaqPub({ s }) {
  return (
    <section className="site-section"><div className="site-wrap" style={{ maxWidth: 780 }}>
      <SectionHead kicker={s.payload.faqLabel} />
      <Accordion items={s.items} q={(it) => P(it.payload.q)} a={(it) => P(it.payload.a)} />
    </div></section>
  )
}

function CtaPub({ s }) {
  const p = s.payload
  return (
    <section className="site-section cta-final"><div className="site-wrap">
      <div className="site-kicker">{P(p.ctaKicker)}</div>
      <h2>{P(p.ctaTitle)}</h2>
      <p style={{ color: 'var(--textMuted)' }}>{P(p.ctaSubtitle)}</p>
      <p className="meta" style={{ color: 'var(--accent)', fontFamily: 'Oswald', letterSpacing: '.1em' }}>{P(p.ctaDate)} · {P(p.ctaLocation)}</p>
      {p.ctaBtnLabel && <a className="site-btn site-btn-primary" href={p.ctaBtnHref}>{P(p.ctaBtnLabel)}</a>}
    </div></section>
  )
}

function RodapePub({ s, legal }) {
  const [modal, setModal] = useState(null)
  const p = s?.payload || {}
  const lp = legal?.payload || {}
  const paras = (which) => {
    const arr = lp[which]?.paragraphs
    return Array.isArray(arr) ? arr : []
  }
  return (
    <footer className="site-footer"><div className="site-wrap">
      <div style={{ display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 20 }}>
        <div style={{ maxWidth: 360 }}>
          <p>{P(p.footerTagline)}</p>
          <p>{p.contactEmail} · {p.contactPhone}</p>
        </div>
        <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap' }}>
          {p.instagramHref && <a href={p.instagramHref}>Instagram</a>}
          {p.facebookHref && <a href={p.facebookHref}>Facebook</a>}
          {p.youtubeHref && <a href={p.youtubeHref}>YouTube</a>}
          {p.linkedinHref && <a href={p.linkedinHref}>LinkedIn</a>}
          {p.whatsappHref && <a href={p.whatsappHref}>WhatsApp</a>}
        </div>
      </div>
      <div style={{ marginTop: 24, display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 12, borderTop: '1px solid rgba(127,160,200,.12)', paddingTop: 16 }}>
        <span>{P(p.copyright)}</span>
        <span style={{ display: 'flex', gap: 16 }}>
          {legal && <a style={{ cursor: 'pointer' }} onClick={() => setModal('privacy')}>{P(lp.privacy?.title) || 'Privacidade'}</a>}
          {legal && <a style={{ cursor: 'pointer' }} onClick={() => setModal('terms')}>{P(lp.terms?.title) || 'Termos'}</a>}
        </span>
      </div>
      {modal && (
        <SiteModal onClose={() => setModal(null)}>
          <h3 style={{ color: 'var(--textTitle)' }}>{P(lp[modal]?.title)}</h3>
          {paras(modal).map((para, i) => <p key={i} style={{ color: 'var(--textMuted)' }}>{para}</p>)}
        </SiteModal>
      )}
    </div></footer>
  )
}
