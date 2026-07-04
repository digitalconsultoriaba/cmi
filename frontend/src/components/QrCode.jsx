import { useEffect, useState } from 'react'
import QRCode from 'qrcode'

/**
 * QR code renderizado no cliente a partir de um texto (código público do
 * ingresso). É o mesmo valor que a portaria lê no check-in.
 */
export default function QrCode({ value, size = 180 }) {
  const [dataUrl, setDataUrl] = useState(null)

  useEffect(() => {
    let active = true
    if (!value) return
    QRCode.toDataURL(String(value), { width: size, margin: 1, errorCorrectionLevel: 'M' })
      .then((url) => { if (active) setDataUrl(url) })
      .catch(() => { if (active) setDataUrl(null) })
    return () => { active = false }
  }, [value, size])

  if (!dataUrl) {
    return <div style={{ width: size, height: size, background: '#f1f3f5', borderRadius: 8 }} />
  }
  return <img src={dataUrl} alt={`QR ${value}`} width={size} height={size} style={{ borderRadius: 8 }} />
}
