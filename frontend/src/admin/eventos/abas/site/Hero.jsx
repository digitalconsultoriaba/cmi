import SecaoSimples from './SecaoSimples'
import LocalizedInput from './LocalizedInput'

export default function Hero(props) {
  return (
    <SecaoSimples {...props}>
      {(d, patch, langs) => (
        <>
          <LocalizedInput label="Título — linha 1" value={d.titleLine1} onChange={(v) => patch('titleLine1', v)} languages={langs} />
          <LocalizedInput label="Título — linha 2" value={d.titleLine2} onChange={(v) => patch('titleLine2', v)} languages={langs} />
          <LocalizedInput label="Título — linha 3" value={d.titleLine3} onChange={(v) => patch('titleLine3', v)} languages={langs} />
          <LocalizedInput label="Subtítulo" value={d.subtitle} onChange={(v) => patch('subtitle', v)} languages={langs} textarea />
          <div className="row">
            <div className="col-md-6"><LocalizedInput label="Data (texto)" value={d.dateText} onChange={(v) => patch('dateText', v)} languages={langs} /></div>
            <div className="col-md-6"><LocalizedInput label="Local (texto)" value={d.locationText} onChange={(v) => patch('locationText', v)} languages={langs} /></div>
          </div>
          <div className="row">
            <div className="col-md-6"><LocalizedInput label="Botão principal — texto" value={d.primaryLabel} onChange={(v) => patch('primaryLabel', v)} languages={langs} /></div>
            <div className="col-md-6">
              <label className="form-label mb-1">Botão principal — link</label>
              <input className="form-control" value={d.primaryHref ?? ''} onChange={(e) => patch('primaryHref', e.target.value)} />
            </div>
          </div>
          <div className="row">
            <div className="col-md-6"><LocalizedInput label="Botão secundário — texto" value={d.secondaryLabel} onChange={(v) => patch('secondaryLabel', v)} languages={langs} /></div>
            <div className="col-md-6">
              <label className="form-label mb-1">Botão secundário — link</label>
              <input className="form-control" value={d.secondaryHref ?? ''} onChange={(e) => patch('secondaryHref', e.target.value)} />
            </div>
          </div>
        </>
      )}
    </SecaoSimples>
  )
}
