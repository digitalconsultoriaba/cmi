// Ícones SVG minimalistas do checkout (spec 014). stroke currentColor.
const s = { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round', strokeLinejoin: 'round' }

export const IcUserPlus = (p) => <svg {...s} {...p}><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M19 8v6M22 11h-6" /></svg>
export const IcUsers = (p) => <svg {...s} {...p}><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></svg>
export const IcBuilding = (p) => <svg {...s} {...p}><rect x="4" y="3" width="16" height="18" rx="1.5" /><path d="M9 8h.01M15 8h.01M9 12h.01M15 12h.01M9 16h6" /></svg>
export const IcGlobe = (p) => <svg {...s} {...p}><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z" /></svg>
export const IcMail = (p) => <svg {...s} {...p}><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></svg>
export const IcPhone = (p) => <svg {...s} {...p}><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.4c.9.3 1.7.6 2.7.7a2 2 0 0 1 1.7 2Z" /></svg>
export const IcShield = (p) => <svg {...s} {...p}><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" /><path d="m9 12 2 2 4-4" /></svg>
export const IcTicket = (p) => <svg {...s} {...p}><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 6 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-6Z" /><path d="M12 7v10" strokeDasharray="2 2" /></svg>
export const IcGift = (p) => <svg {...s} {...p}><path d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7ZM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7Z" /></svg>
export const IcCheck = (p) => <svg {...s} {...p}><path d="M20 6 9 17l-5-5" /></svg>
export const IcChevron = (p) => <svg {...s} {...p}><path d="m9 18 6-6-6-6" /></svg>

// Esquadro e compasso (selo + marca-d'água do header).
export const IcCompass = (p) => (
  <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" {...p}>
    <path d="M32 6 12 44h14M32 6l20 38H38" />
    <path d="M20 40 8 58h20M44 40l12 18H36" />
    <circle cx="32" cy="6" r="2.2" fill="currentColor" />
    <path d="M22 50h20" />
  </svg>
)
