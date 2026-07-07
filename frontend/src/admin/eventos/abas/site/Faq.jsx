import SecaoDinamica from './SecaoDinamica'
import ListaOrdenavel from './ListaOrdenavel'
import LocalizedInput from './LocalizedInput'

export default function Faq(props) {
  const { eventId, section, languages, reload } = props
  return (
    <SecaoDinamica {...props}
      labelFields={(d, patch, langs) => (
        <LocalizedInput label="Rótulo da seção" value={d.faqLabel} onChange={(v) => patch('faqLabel', v)} languages={langs} />
      )}>
      <ListaOrdenavel
        eventId={eventId} sectionId={section.id} items={section.items || []} onReload={reload}
        languages={languages} singular="pergunta"
        newItem={() => ({ q: { pt: '' }, a: { pt: '' } })}
        title={(p) => p.q?.pt || ''}
        renderFields={(d, patch, { languages: langs }) => (
          <>
            <LocalizedInput label="Pergunta" value={d.q} onChange={(v) => patch('q', v)} languages={langs} />
            <LocalizedInput label="Resposta" value={d.a} onChange={(v) => patch('a', v)} languages={langs} textarea rows={4} />
          </>
        )}
      />
    </SecaoDinamica>
  )
}
