import SecaoSimples from './SecaoSimples'
import LocalizedInput from './LocalizedInput'
import MediaUpload from './MediaUpload'

export default function CtaFinal(props) {
  const { eventId } = props
  return (
    <SecaoSimples {...props}>
      {(d, patch, langs) => (
        <>
          <LocalizedInput label="Kicker" value={d.ctaKicker} onChange={(v) => patch('ctaKicker', v)} languages={langs} />
          <LocalizedInput label="Título" value={d.ctaTitle} onChange={(v) => patch('ctaTitle', v)} languages={langs} />
          <LocalizedInput label="Subtítulo" value={d.ctaSubtitle} onChange={(v) => patch('ctaSubtitle', v)} languages={langs} textarea />
          <div className="row">
            <div className="col-md-6"><LocalizedInput label="Data" value={d.ctaDate} onChange={(v) => patch('ctaDate', v)} languages={langs} /></div>
            <div className="col-md-6"><LocalizedInput label="Local" value={d.ctaLocation} onChange={(v) => patch('ctaLocation', v)} languages={langs} /></div>
          </div>
          <div className="row">
            <div className="col-md-6"><LocalizedInput label="Botão — texto" value={d.ctaBtnLabel} onChange={(v) => patch('ctaBtnLabel', v)} languages={langs} /></div>
            <div className="col-md-6">
              <label className="form-label mb-1">Botão — link</label>
              <input className="form-control" value={d.ctaBtnHref ?? ''} onChange={(e) => patch('ctaBtnHref', e.target.value)} />
            </div>
          </div>
          <div className="row mt-2">
            <div className="col-md-6 border-end">
              <h5>Retrato esquerdo</h5>
              <input className="form-control mb-2" placeholder="Nome" value={d.portraitLeftName ?? ''} onChange={(e) => patch('portraitLeftName', e.target.value)} />
              <LocalizedInput label="Cargo" value={d.portraitLeftRole} onChange={(v) => patch('portraitLeftRole', v)} languages={langs} />
              <MediaUpload label="Foto" value={d.portraitLeftPhoto} onChange={(p) => patch('portraitLeftPhoto', p)} eventId={eventId} />
            </div>
            <div className="col-md-6">
              <h5>Retrato direito</h5>
              <input className="form-control mb-2" placeholder="Nome" value={d.portraitRightName ?? ''} onChange={(e) => patch('portraitRightName', e.target.value)} />
              <LocalizedInput label="Cargo" value={d.portraitRightRole} onChange={(v) => patch('portraitRightRole', v)} languages={langs} />
              <MediaUpload label="Foto" value={d.portraitRightPhoto} onChange={(p) => patch('portraitRightPhoto', p)} eventId={eventId} />
            </div>
          </div>
        </>
      )}
    </SecaoSimples>
  )
}
