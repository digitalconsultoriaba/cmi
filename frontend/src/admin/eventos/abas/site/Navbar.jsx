import SecaoSimples from './SecaoSimples'
import LocalizedInput from './LocalizedInput'

export default function Navbar(props) {
  return (
    <SecaoSimples {...props}>
      {(d, patch, langs) => {
        const anchors = Array.isArray(d.anchors) ? d.anchors : []
        const setAnchors = (a) => patch('anchors', a)
        return (
          <>
            <div className="row">
              <div className="col-md-6"><LocalizedInput label="Botão (CTA) — texto" value={d.ctaLabel} onChange={(v) => patch('ctaLabel', v)} languages={langs} /></div>
              <div className="col-md-6">
                <label className="form-label mb-1">Botão (CTA) — link</label>
                <input className="form-control" value={d.ctaHref ?? ''} onChange={(e) => patch('ctaHref', e.target.value)} />
              </div>
            </div>
            <label className="form-label mt-2">Âncoras do menu</label>
            {anchors.map((a, i) => (
              <div className="row g-2 align-items-end mb-1" key={i}>
                <div className="col"><LocalizedInput label="" value={a.label} onChange={(v) => setAnchors(anchors.map((x, j) => j === i ? { ...x, label: v } : x))} languages={langs} /></div>
                <div className="col">
                  <input className="form-control" placeholder="#ancora" value={a.href ?? ''} onChange={(e) => setAnchors(anchors.map((x, j) => j === i ? { ...x, href: e.target.value } : x))} />
                </div>
                <div className="col-auto"><button className="btn btn-outline-danger" onClick={() => setAnchors(anchors.filter((_, j) => j !== i))}>×</button></div>
              </div>
            ))}
            <button className="btn btn-sm btn-outline-secondary" onClick={() => setAnchors([...anchors, { label: { pt: '' }, href: '' }])}>+ âncora</button>
          </>
        )
      }}
    </SecaoSimples>
  )
}
