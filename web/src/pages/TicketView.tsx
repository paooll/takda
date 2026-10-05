import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, formatWait, type Ticket } from '../lib/api'

/** How often the ticket screen refreshes while the customer waits. */
const POLL_MS = 5000

export default function TicketView() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [ticket, setTicket] = useState<Ticket | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [leaving, setLeaving] = useState(false)
  const [pulse, setPulse] = useState(false)

  // Acknowledge a position change once, then let the animation rest.
  const lastPosition = useRef<number | null>(null)

  useEffect(() => {
    if (!ticket || ticket.position === lastPosition.current) return
    const isFirst = lastPosition.current === null
    lastPosition.current = ticket.position
    if (isFirst) return
    setPulse(true)
  }, [ticket])

  useEffect(() => {
    if (!pulse) return
    const timer = window.setTimeout(() => setPulse(false), 600)
    return () => window.clearTimeout(timer)
  }, [pulse])

  useEffect(() => {
    if (!id) return
    let cancelled = false

    async function load() {
      try {
        const { data } = await api.ticket(Number(id))
        if (!cancelled) {
          setTicket(data)
          setError(null)
        }
      } catch (caught) {
        if (!cancelled) setError(caught instanceof Error ? caught.message : 'Could not load your ticket.')
      }
    }

    void load()

    const timer = window.setInterval(() => {
      // Stop polling once the turn is done; there is nothing left to watch.
      if (cancelled) return
      void load()
    }, POLL_MS)

    return () => {
      cancelled = true
      window.clearInterval(timer)
    }
  }, [id])

  if (error) {
    return (
      <Shell>
        <p className="text-danger">{error}</p>
        <Link to="/" className="btn btn-secondary mt-4">
          Back to queues
        </Link>
      </Shell>
    )
  }

  if (!ticket) {
    return (
      <Shell>
        <Skeleton />
      </Shell>
    )
  }

  const isDone = ticket.status === 'done' || ticket.status === 'cancelled'
  const isNext = ticket.status === 'serving' || ticket.position === 1

  async function leave() {
    setLeaving(true)
    try {
      await api.leave(ticket!.id)
      navigate('/tickets')
    } catch {
      setLeaving(false)
    }
  }

  return (
    <Shell>
      <p className="text-caption text-ink-muted">{ticket.business.name}</p>
      <h1 className="mt-1 text-title">{ticket.queue.name}</h1>

      <div className="card mt-6 p-6 text-center">
        <p className="text-caption text-ink-muted">Your ticket</p>
        <p className="mt-1 text-display font-bold tabular-nums">{ticket.code}</p>
      </div>

      {/*
        aria-live announces position changes without stealing focus, so a
        screen reader user hears the update while the number visibly changes.
      */}
      <div className="card mt-4 p-6" aria-live="polite" aria-atomic="true">
        {isDone ? (
          <>
            <p className="text-caption text-ink-muted">Status</p>
            <p className="mt-1 text-title capitalize">
              {ticket.status === 'done' ? 'Served' : 'Cancelled'}
            </p>
          </>
        ) : ticket.status === 'serving' ? (
          <>
            <p className="text-caption text-ink-muted">Status</p>
            <p className="mt-1 text-title text-sun">It&rsquo;s your turn</p>
            <p className="mt-2 text-ink-muted">Please proceed to the counter.</p>
          </>
        ) : (
          <>
            <p className="text-caption text-ink-muted">
              {ticket.people_ahead === 0
                ? 'You are next in line'
                : `${ticket.people_ahead} ${ticket.people_ahead === 1 ? 'person' : 'people'} ahead of you`}
            </p>
            <p
              key={ticket.position}
              className={`mt-1 text-display font-bold tabular-nums ${pulse ? 'position-pulse' : ''}`}
            >
              {ticket.position}
            </p>
            <p className="mt-2 text-ink-muted">
              About <span className="font-semibold text-ink">{formatWait(ticket.estimated_wait_seconds)}</span> to wait
            </p>
          </>
        )}
      </div>

      {ticket.now_serving.length > 0 && (
        <div className="card mt-4 p-4">
          <p className="text-caption text-ink-muted">Now serving</p>
          <p className="mt-1 font-semibold tabular-nums">{ticket.now_serving.join(', ')}</p>
        </div>
      )}

      {!isDone && (
        <button
          type="button"
          onClick={leave}
          disabled={leaving}
          className="btn btn-secondary mt-6 w-full"
        >
          {leaving ? 'Leaving…' : 'Leave this queue'}
        </button>
      )}

      {isNext && !isDone && (
        <p className="mt-4 text-center text-sm text-sun">
          Stay close — your turn is approaching.
        </p>
      )}
    </Shell>
  )
}

function Shell({ children }: { children: React.ReactNode }) {
  return (
    <main className="mx-auto min-h-dvh w-full max-w-md px-4 py-8">
      <Link to="/" className="tap inline-flex items-center text-caption text-ink-muted">
        ← All queues
      </Link>
      <div className="mt-4">{children}</div>
    </main>
  )
}

function Skeleton() {
  return (
    <div className="animate-pulse" aria-busy="true" aria-label="Loading your ticket">
      <div className="h-4 w-24 rounded bg-surface-sunken" />
      <div className="mt-3 h-7 w-48 rounded bg-surface-sunken" />
      <div className="card mt-6 h-28" />
      <div className="card mt-4 h-40" />
    </div>
  )
}
