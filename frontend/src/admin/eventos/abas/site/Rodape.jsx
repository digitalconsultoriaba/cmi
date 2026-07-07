import SecaoSimples from './SecaoSimples'
import LocalizedInput from './LocalizedInput'

const LINKS = [
  ['contactEmail', 'E-mail'], ['contactPhone', 'Telefone'], ['contactWhatsapp', 'WhatsApp (texto)'],
  ['whatsappHref', 'WhatsApp (link)'], ['instagramHref', 'Instagram'], ['facebookHref', 'Facebook'],
  ['youtubeHref', 'YouTube'], ['linkedinHref', 'LinkedIn'], ['privacyHref', 'Link Privacidade'], ['termsHref', 'Link Termos'],
]

export default function Rodape(props) {
  return (
    <SecaoSimples {...props}>
      {(d, patch, langs) => (
        <>
          <LocalizedInput label="Tagline" value={d.footerTagline} onChange={(v) => patch('footerTagline', v)} languages={langs} textarea />
          <div className="row g-2">
            {LINKS.map(([k, label]) => (
              <div className="col-md-6" key={k}>
                <label className="form-label mb-1">{label}</label>
                <input className="form-control" value={d[k] ?? ''} onChange={(e) => patch(k, e.target.value)} />
              </div>
            ))}
          </div>
          <div className="mt-2">
            <LocalizedInput label="Copyright" value={d.copyright} onChange={(v) => patch('copyright', v)} languages={langs} />
          </div>
        </>
      )}
    </SecaoSimples>
  )
}
