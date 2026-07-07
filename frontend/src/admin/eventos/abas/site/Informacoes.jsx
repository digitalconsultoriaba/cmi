import SecaoDinamica from './SecaoDinamica'
import ListaOrdenavel from './ListaOrdenavel'
import LocalizedInput from './LocalizedInput'

const ICONS = ['bed', 'transport', 'tourism', 'food', 'money']

export default function Informacoes(props) {
  const { eventId, section, languages, reload } = props
  return (
    <SecaoDinamica {...props}
      labelFields={(d, patch, langs) => (
        <LocalizedInput label="Rótulo da seção" value={d.infoLabel} onChange={(v) => patch('infoLabel', v)} languages={langs} />
      )}>
      <ListaOrdenavel
        eventId={eventId} sectionId={section.id} items={section.items || []} onReload={reload}
        languages={languages} singular="categoria"
        newItem={() => ({ icon: 'bed', title: { pt: '' }, text: { pt: '' }, linkLabel: { pt: '' }, modalText: { pt: '' } })}
        title={(p) => p.title?.pt || ''}
        renderFields={(d, patch, { languages: langs }) => (
          <>
            <label className="form-label mb-1">Ícone</label>
            <select className="form-select mb-2" value={d.icon} onChange={(e) => patch('icon', e.target.value)}>
              {ICONS.map((i) => <option key={i} value={i}>{i}</option>)}
            </select>
            <LocalizedInput label="Título" value={d.title} onChange={(v) => patch('title', v)} languages={langs} />
            <LocalizedInput label="Texto" value={d.text} onChange={(v) => patch('text', v)} languages={langs} textarea />
            <LocalizedInput label="Rótulo do link" value={d.linkLabel} onChange={(v) => patch('linkLabel', v)} languages={langs} />
            <LocalizedInput label="Texto do modal" value={d.modalText} onChange={(v) => patch('modalText', v)} languages={langs} textarea />
          </>
        )}
        child={{
          singular: 'contato',
          title: (p) => p.name || '',
          newItem: () => ({ name: '', desc: { pt: '' }, info: { pt: '' }, address: { pt: '' }, phone: '', site: '' }),
          renderFields: (d, patch, { languages: langs }) => (
            <>
              <label className="form-label mb-1">Nome</label>
              <input className="form-control mb-2" value={d.name ?? ''} onChange={(e) => patch('name', e.target.value)} />
              <LocalizedInput label="Descrição" value={d.desc} onChange={(v) => patch('desc', v)} languages={langs} />
              <LocalizedInput label="Informação" value={d.info} onChange={(v) => patch('info', v)} languages={langs} />
              <LocalizedInput label="Endereço" value={d.address} onChange={(v) => patch('address', v)} languages={langs} />
              <div className="row">
                <div className="col-md-6">
                  <label className="form-label mb-1">Telefone</label>
                  <input className="form-control mb-2" value={d.phone ?? ''} onChange={(e) => patch('phone', e.target.value)} />
                </div>
                <div className="col-md-6">
                  <label className="form-label mb-1">Site</label>
                  <input className="form-control mb-2" value={d.site ?? ''} onChange={(e) => patch('site', e.target.value)} />
                </div>
              </div>
            </>
          ),
        }}
      />
    </SecaoDinamica>
  )
}
