import SecaoSimples from './SecaoSimples'
import LocalizedInput, { LocalizedList } from './LocalizedInput'

export default function Legal(props) {
  return (
    <SecaoSimples {...props} hideActive>
      {(d, patch, langs) => {
        const privacy = d.privacy || {}
        const terms = d.terms || {}
        return (
          <div className="row">
            <div className="col-md-6 border-end">
              <h4>Política de Privacidade</h4>
              <LocalizedInput label="Título" value={privacy.title} onChange={(v) => patch('privacy', { ...privacy, title: v })} languages={langs} />
              <LocalizedList label="Parágrafos" value={privacy.paragraphs} onChange={(v) => patch('privacy', { ...privacy, paragraphs: v })} languages={langs} />
            </div>
            <div className="col-md-6">
              <h4>Termos de Uso</h4>
              <LocalizedInput label="Título" value={terms.title} onChange={(v) => patch('terms', { ...terms, title: v })} languages={langs} />
              <LocalizedList label="Parágrafos" value={terms.paragraphs} onChange={(v) => patch('terms', { ...terms, paragraphs: v })} languages={langs} />
            </div>
          </div>
        )
      }}
    </SecaoSimples>
  )
}
