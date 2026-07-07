import SecaoDinamica from './SecaoDinamica'
import ListaOrdenavel from './ListaOrdenavel'
import LocalizedInput, { LocalizedList } from './LocalizedInput'

const TYPES = ['open', 'talk', 'debate', 'coffee', 'lunch', 'workshop', 'results', 'coquetel']

export default function Programacao(props) {
  const { eventId, section, languages, reload } = props
  return (
    <SecaoDinamica {...props}
      labelFields={(d, patch, langs) => (
        <>
          <LocalizedInput label="Rótulo da seção" value={d.progLabel} onChange={(v) => patch('progLabel', v)} languages={langs} />
          <div className="row">
            <div className="col-md-6"><LocalizedInput label="Botão — texto" value={d.progBtn} onChange={(v) => patch('progBtn', v)} languages={langs} /></div>
            <div className="col-md-6">
              <label className="form-label mb-1">Botão — link</label>
              <input className="form-control" value={d.progHref ?? ''} onChange={(e) => patch('progHref', e.target.value)} />
            </div>
          </div>
        </>
      )}>
      <ListaOrdenavel
        eventId={eventId} sectionId={section.id} items={section.items || []} onReload={reload}
        languages={languages} singular="dia"
        newItem={() => ({ label: { pt: '' }, date: '' })}
        title={(p) => `${p.label?.pt || 'Dia'} — ${p.date || ''}`}
        renderFields={(d, patch, { languages: langs }) => (
          <>
            <LocalizedInput label="Rótulo do dia" value={d.label} onChange={(v) => patch('label', v)} languages={langs} />
            <label className="form-label mb-1">Data</label>
            <input type="date" className="form-control" value={d.date ?? ''} onChange={(e) => patch('date', e.target.value)} />
          </>
        )}
        child={{
          singular: 'atividade',
          title: (p) => `${p.t1 || ''} ${p.title?.pt || ''}`.trim(),
          newItem: () => ({ type: 'talk', t1: '', t2: '', title: { pt: '' }, speaker: '', org: '', subtitle: { pt: '' }, desc: { pt: '' }, activities: [] }),
          renderFields: (d, patch, { languages: langs }) => (
            <>
              <div className="row">
                <div className="col-md-4">
                  <label className="form-label mb-1">Tipo</label>
                  <select className="form-select mb-2" value={d.type} onChange={(e) => patch('type', e.target.value)}>
                    {TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
                  </select>
                </div>
                <div className="col-md-4">
                  <label className="form-label mb-1">Início</label>
                  <input className="form-control mb-2" value={d.t1 ?? ''} onChange={(e) => patch('t1', e.target.value)} placeholder="09:00" />
                </div>
                <div className="col-md-4">
                  <label className="form-label mb-1">Fim</label>
                  <input className="form-control mb-2" value={d.t2 ?? ''} onChange={(e) => patch('t2', e.target.value)} placeholder="10:00" />
                </div>
              </div>
              <LocalizedInput label="Título" value={d.title} onChange={(v) => patch('title', v)} languages={langs} />
              <div className="row">
                <div className="col-md-6">
                  <label className="form-label mb-1">Palestrante</label>
                  <input className="form-control mb-2" value={d.speaker ?? ''} onChange={(e) => patch('speaker', e.target.value)} />
                </div>
                <div className="col-md-6">
                  <label className="form-label mb-1">Organização</label>
                  <input className="form-control mb-2" value={d.org ?? ''} onChange={(e) => patch('org', e.target.value)} />
                </div>
              </div>
              <LocalizedInput label="Subtítulo" value={d.subtitle} onChange={(v) => patch('subtitle', v)} languages={langs} />
              <LocalizedInput label="Descrição" value={d.desc} onChange={(v) => patch('desc', v)} languages={langs} textarea />
              {d.type === 'workshop' && (
                <div className="mt-2">
                  <label className="form-label mb-1">Atividades do workshop</label>
                  {(d.activities || []).map((a, i) => (
                    <div className="row g-2 mb-1" key={i}>
                      <div className="col"><LocalizedInput label="" value={a.label} onChange={(v) => patch('activities', d.activities.map((x, j) => j === i ? { ...x, label: v } : x))} languages={langs} /></div>
                      <div className="col"><LocalizedInput label="" value={a.text} onChange={(v) => patch('activities', d.activities.map((x, j) => j === i ? { ...x, text: v } : x))} languages={langs} /></div>
                      <div className="col-auto"><button className="btn btn-outline-danger" onClick={() => patch('activities', d.activities.filter((_, j) => j !== i))}>×</button></div>
                    </div>
                  ))}
                  <button className="btn btn-sm btn-outline-secondary" onClick={() => patch('activities', [...(d.activities || []), { label: { pt: '' }, text: { pt: '' } }])}>+ atividade</button>
                </div>
              )}
            </>
          ),
        }}
      />
    </SecaoDinamica>
  )
}
