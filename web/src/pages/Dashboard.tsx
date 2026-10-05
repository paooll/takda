import { useCallback, useEffect, useState } from 'react'
import { api, type Analytics, type BoardTicket } from '../lib/api'
import { useAuth } from '../lib/auth'

const POLL_MS = 5000

export default function Dashboard() {
  const { user, signOut } = useAuth()

  const [slug, setSlug] = useState<string | null>(null)
  const [queueId, setQueueId] = useState<number | null>(null)
  const [board, setBoard] = useState<BoardTicket[] | null>(null)
  const [analytics, setAnalytics] = useState<Analytics | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  // Load the owner's first business, then its first queue.
  useEffect(() => {
    if (!user) return
    api
      .businesses()
      .then(({ data }) => {
        const owned = data[0]
        if (!owned) {
          setError('No business is open yet.')
          return
        }
        setSlug(owned.slug)
        const first = owned.active_queues?.[0]
        if (first) setQueueId(first.id)
      })
      .catch((caught) => setError(caught instanceof Error ? caught.message : 'Could not load.'))
  }, [user])

  const refresh = useCallback(async () => {
    if (!slug || !queueId) return
    try {
      const { data } = await api.board(slug, queueId)
      setBoard(data.tickets)
    } catch {
      // Transient poll failures should not blank the board.
    }
  }, [slug, queueId])

  useEffect(() => {
    if (!slug) return
    void refresh()
    const timer = window.setInterval(refresh, POLL_MS)
    return () => window.clearInterval(timer)
  }, [slug, queueId, refresh])

  useEffect(() => {
    if (!slug) return
    api.analytics(slug).then(({ data }) => setAnalytics(data)).catch(() => setAnalytics(null))
  }, [slug])

  async function act(action: () => Promise<unknown>) {
    setBusy(true)
    try {
      await action()
      await refresh()
    } finally {
      setBusy(false)
    }
  }

  if (error) {
    return (
      <main className="mx-auto w-full max-w-2xl px-4 py-8">
        <p role="alert" className="text-danger">{error}</p>
      </main>
    )
  }

  const serving = board?.find((t) => t.status === 'serving')
  const waiting = board?.filter((t) => t.status === 'waiting') ?? []

  return (
    <main className="mx-auto w-full max-w-2xl px-4 py-8">
      <header className="flex items-center justify-between">
        <h1 className="text-title">Counter</h1>
        <button type="button" onClick={signOut} className="tap text-caption text-ink-muted">
          Sign out
        </button>
      </header>

      {analytics && (
        <dl className="mt-6 grid grid-cols-3 gap-3">
          <Stat label="Waiting now" value={analytics.waiting_now} />
          <Stat label="Served 30d" value={analytics.served_30d} />
          <Stat label="No-show rate" value={`${Math.round(analytics.no_show_rate * 100)}%`} />
        </dl>
      )}

      <section className="card mt-6 p-5">
        <h2 className="text-caption font-semibold uppercase tracking-wide text-ink-muted">Now serving</h2>
        {serving ? (
          <p className="mt-2 text-display font-bold tabular-nums">{serving.code}</p>
        ) : (
          <p className="mt-2 text-ink-muted">Nobody at the counter.</p>
        )}

        <div className="mt-4 flex gap-2">
          <button
            type="button"
            disabled={busy || waiting.length === 0}
            onClick={() => act(() => api.callNext(slug!, queueId!))}
            className="btn btn-primary flex-1"
          >
            Call next
          </button>
          {serving && (
            <button
              type="button"
              disabled={busy}
              onClick={() => act(() => api.complete(slug!, queueId!, serving.id))}
              className="btn btn-secondary flex-1"
            >
              Mark served
            </button>
          )}
        </div>
      </section>

      <section className="mt-6">
        <h2 className="mb-2 text-caption font-semibold uppercase tracking-wide text-ink-muted">
          Waiting ({waiting.length})
        </h2>
        {!board && <div className="card h-24 animate-pulse" aria-busy="true" />}
        <ul className="space-y-2">
          {waiting.map((ticket) => (
            <li key={ticket.id} className="card flex items-center justify-between p-4">
              <div>
                <p className="font-medium">{ticket.code}</p>
                <p className="text-caption text-ink-muted">{ticket.customer}</p>
              </div>
              <span className="text-caption text-ink-muted tabular-nums">#{ticket.position}</span>
            </li>
          ))}
        </ul>
        {board?.length === 0 && <p className="mt-4 text-ink-muted">The queue is empty.</p>}
      </section>
    </main>
  )
}

function Stat({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="card p-3 text-center">
      <dt className="text-xs font-medium text-ink-muted">{label}</dt>
      <dd className="mt-1 text-xl font-semibold tabular-nums">{value}</dd>
    </div>
  )
}
