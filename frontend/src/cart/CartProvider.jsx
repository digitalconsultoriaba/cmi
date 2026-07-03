import { createContext, useContext, useEffect, useState } from 'react'

// Carrinho no navegador (research 004, Decisão 8): sobrevive ao login;
// o servidor só conhece o pedido criado.
const STORAGE_KEY = 'cmi-cart'
const CartContext = createContext(null)

function load() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY)) ?? null
  } catch {
    return null
  }
}

export function CartProvider({ children }) {
  const [cart, setCart] = useState(load) // { eventSlug, quantities: {typeId: n} }

  useEffect(() => {
    if (cart) localStorage.setItem(STORAGE_KEY, JSON.stringify(cart))
    else localStorage.removeItem(STORAGE_KEY)
  }, [cart])

  const setQuantity = (eventSlug, typeId, quantity) => {
    setCart((current) => {
      const base = current?.eventSlug === eventSlug ? current : { eventSlug, quantities: {} }
      const quantities = { ...base.quantities, [typeId]: quantity }
      if (quantity <= 0) delete quantities[typeId]
      return { eventSlug, quantities }
    })
  }

  const totalItems = cart
    ? Object.values(cart.quantities).reduce((sum, n) => sum + n, 0)
    : 0

  const clear = () => setCart(null)

  return (
    <CartContext.Provider value={{ cart, setQuantity, totalItems, clear }}>
      {children}
    </CartContext.Provider>
  )
}

export function useCart() {
  const context = useContext(CartContext)
  if (!context) throw new Error('useCart precisa estar dentro de <CartProvider>')
  return context
}
