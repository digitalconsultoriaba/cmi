import SecaoSimples from './SecaoSimples'
import LocalizedInput from './LocalizedInput'
import MediaUpload from './MediaUpload'

export default function Local(props) {
  const { eventId } = props
  return (
    <SecaoSimples {...props}>
      {(d, patch, langs) => (
        <>
          <LocalizedInput label="Rótulo da seção" value={d.localLabel} onChange={(v) => patch('localLabel', v)} languages={langs} />
          <div className="row">
            <div className="col-md-6">
              <label className="form-label mb-1">Nome do lugar</label>
              <input className="form-control mb-2" value={d.placeName ?? ''} onChange={(e) => patch('placeName', e.target.value)} />
            </div>
            <div className="col-md-6">
              <label className="form-label mb-1">Nome do venue</label>
              <input className="form-control mb-2" value={d.venueName ?? ''} onChange={(e) => patch('venueName', e.target.value)} />
            </div>
          </div>
          <LocalizedInput label="Texto" value={d.localText} onChange={(v) => patch('localText', v)} languages={langs} textarea />
          <LocalizedInput label="Endereço" value={d.venueAddress} onChange={(v) => patch('venueAddress', v)} languages={langs} />
          <div className="row">
            <div className="col-md-6">
              <LocalizedInput label="Botão do mapa — texto" value={d.mapBtn} onChange={(v) => patch('mapBtn', v)} languages={langs} />
            </div>
            <div className="col-md-6">
              <label className="form-label mb-1">Link do mapa (Google Maps)</label>
              <input className="form-control" value={d.mapHref ?? ''} onChange={(e) => patch('mapHref', e.target.value)} />
            </div>
          </div>
          <MediaUpload label="Foto do venue" value={d.venuePhoto} onChange={(p) => patch('venuePhoto', p)} eventId={eventId} />
        </>
      )}
    </SecaoSimples>
  )
}
