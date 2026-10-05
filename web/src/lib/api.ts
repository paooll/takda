const TOKEN_KEY = 'takda.token'

export type Role = 'customer' | 'business'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  phone: string | null
}

export interface Queue {
  id: number
  name: string
  description: string | null
  code_prefix: string
  avg_service_minutes: number
  is_active: boolean
}

export interface Business {
  id: number
  name: string
  slug: string
  description: string | null
  address: string | null
  avg_service_minutes: number
  is_open: boolean
  active_queues?: Queue[]
}

export interface Ticket {
  id: number
  code: string
  status: 'waiting' | 'serving' | 'done' | 'cancelled'
  position: number
  people_ahead: number
  estimated_wait_seconds: number
  joined_at: string | null
  called_at: string | null
  queue: { id: number; name: string; avg_service_minutes: number }
  business: { id: number; name: string; slug: string }
  now_serving: string[]
}

export interface BoardTicket {
  id: number
  code: string
  status: 'waiting' | 'serving'
  customer: string
  position: number
  joined_at: string | null
}

export interface Analytics {
  served_30d: number
  waiting_now: number
  appointments_30d: number
  no_show_rate: number
  queues: Array<{
    id: number
    name: string
    waiting: number
    served_30d: number
    avg_service_minutes: number
  }>
}

export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

export const getToken = () => localStorage.getItem(TOKEN_KEY)
export const setToken = (token: string) => localStorage.setItem(TOKEN_KEY, token)
export const clearToken = () => localStorage.removeItem(TOKEN_KEY)

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = getToken()
  const isFormData = init.body instanceof FormData

  const response = await fetch(`/api${path}`, {
    ...init,
    headers: {
      Accept: 'application/json',
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...init.headers,
    },
  })

  if (response.status === 204) return undefined as T

  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new ApiError(
      payload.message ?? 'Something went wrong. Please try again.',
      response.status,
      payload.errors ?? {},
    )
  }

  return payload as T
}

export const api = {
  register: (data: { name: string; email: string; password: string; password_confirmation: string; role?: Role; phone?: string }) =>
    request<{ data: { user: User; token: string } }>('/register', {
      method: 'POST',
      body: JSON.stringify(data),
    }),

  login: (email: string, password: string) =>
    request<{ data: { user: User; token: string } }>('/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),

  me: () => request<{ data: { user: User } }>('/user'),

  logout: () => request<{ message: string }>('/logout', { method: 'POST' }),

  businesses: () => request<{ data: Business[] }>('/businesses'),

  business: (slug: string) => request<{ data: Business }>(`/businesses/${slug}`),

  join: (queueId: number) =>
    request<{ data: Ticket; message?: string }>(`/queues/${queueId}/join`, { method: 'POST' }),

  ticket: (id: number) => request<{ data: Ticket }>(`/tickets/${id}`),

  myTickets: () => request<{ data: Ticket[] }>('/tickets'),

  leave: (ticketId: number) => request<{ message: string }>(`/tickets/${ticketId}`, { method: 'DELETE' }),

  board: (slug: string, queueId: number) =>
    request<{ data: { queue: { waiting_count: number }; tickets: BoardTicket[] } }>(
      `/businesses/${slug}/queues/${queueId}/board`,
    ),

  callNext: (slug: string, queueId: number) =>
    request<{ data: { code: string } }>(`/businesses/${slug}/queues/${queueId}/call-next`, {
      method: 'POST',
    }),

  complete: (slug: string, queueId: number, ticketId: number) =>
    request<{ message: string }>(`/businesses/${slug}/queues/${queueId}/tickets/${ticketId}/complete`, {
      method: 'POST',
    }),

  analytics: (slug: string) => request<{ data: Analytics }>(`/businesses/${slug}/dashboard`),

  qr: (slug: string, queueId: number) =>
    request<{ data: { url: string; svg: string } }>(`/businesses/${slug}/queues/${queueId}/qr.json`),
}

/** "1 hr 20 min" / "12 min" / "Less than a minute" */
export function formatWait(totalSeconds: number): string {
  if (totalSeconds <= 0) return 'Less than a minute'

  const minutes = Math.round(totalSeconds / 60)

  if (minutes < 60) return `${minutes} min`

  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60

  return rest === 0 ? `${hours} hr` : `${hours} hr ${rest} min`
}
