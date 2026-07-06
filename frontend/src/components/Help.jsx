import { useState } from 'react'

/** Ícone "?" que ao passar o mouse mostra um popup com a explicação. */
export default function Help({ text, width = 280 }) {
  const [show, setShow] = useState(false)
  return (
    <span
      style={{ position: 'relative', display: 'inline-block', marginLeft: 6 }}
      onMouseEnter={() => setShow(true)}
      onMouseLeave={() => setShow(false)}
    >
      <span style={{
        cursor: 'help', display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
        width: 16, height: 16, borderRadius: '50%', border: '1px solid #9aa4b2',
        color: '#697386', fontSize: 11, fontWeight: 700, lineHeight: 1,
      }}>?</span>
      {show && (
        <span style={{
          position: 'absolute', zIndex: 1060, bottom: '150%', left: '50%', transform: 'translateX(-50%)',
          width, background: '#1e293b', color: '#fff', padding: '8px 10px', borderRadius: 6,
          fontSize: 12, lineHeight: 1.45, fontWeight: 400, textAlign: 'left', whiteSpace: 'normal',
          boxShadow: '0 6px 18px rgba(0,0,0,.25)',
        }}>{text}</span>
      )}
    </span>
  )
}
