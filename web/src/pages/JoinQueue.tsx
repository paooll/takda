import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, type Business, type Queue } from '../lib/api'
import { useAuth } from '../lib/auth'

/**
 * Landing page for a scanned QR code: /j/{businessSlug}/{queueId}
 *
 * Kept deliberately shallow — show what the code points at, get a ticket, go to
 * the live view. A poster scanned by a customer should not require browsing.
 */
export default function JoinQueue() {
  const { slug, queueId } = useParams<{ slug: string; queueId: string }>()
  const { user } = useAuth()
  const navigate = useNavigate()

  const [business, setBusiness] = useState<Business | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [joining, setJoining] = useState(false)

  useEffect(() => {
    if (!slug) return
    api
      .business(slug)
      .then(({ data }) => setBusiness(data))
      .catch(() => setError('This queue code is not valid any more.'))
  }, [slug])

  const queue: Queue | undefined = business?.active_queues?.find(
    (q) => q.id === Number(queueId),
  )

  async function join() {
    if (!user) {
      // Come back to this exact code after signing in.
      navigate('/signin', { state: { from: `/j/${slug}/${queueId}` } })
      return
    }

    setJoining(true)
    setError(null)
    try {
      const { data } = await api.join(Number(queueId))
      navigate(`/tickets/${data.id}`)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Could not join that queue.')
      setJoining(false)
    }
  }

  if (error && !business) {
    return (
      <main className="mx-auto w-full max-w-md px-4 py-10">
        <p role="alert" className="text-danger">
          {error}
        </p>
        <Link to="/" className="btn btn-secondary mt-4 w-full">
          Browse other queues
        </Link>
      </main>
    )
  }

  // Still loading the business.
  if (!business) {
    return (
      <main className="mx-auto w-full max-w-md px-4 py-10" aria-busy="true">
        <div className="h-6 w-40 animate-pulse rounded bg-surface-sunken" />
        <div className="card mt-4 h-40 animate-pulse" />
      </main>
    )
  }

  // Loaded, but the code points at a queue that is closed or gone.
  if (!queue) {
    return (
      <main className="mx-auto w-full max-w-md px-4 py-10">
        <h1 className="text-title">Queue unavailable</h1>
        <p className="mt-2 text-ink-muted">
          This queue is closed or no longer accepting customers.
        </p>
        <Link to="/" className="btn btn-primary mt-6 w-full">
          Browse other queues
        </Link>
      </main>
    )
  }

  return (
    <main className="mx-auto w-full max-w-md px-4 py-10">
      <p className="text-caption text-ink-muted">You scanned a queue at</p>
      <h1 className="mt-1 text-title">{business.name}</h1>

      <div className="card mt-6 p-5">
        <h2 className="text-lg font-semibold">{queue.name}</h2>
        {queue.description && <p className="mt-1 text-ink-muted">{queue.description}</p>}
        <p className="mt-3 text-caption text-ink-muted">
          About {queue.avg_service_minutes} min per person
          {business.address ? ` · ${business.address}` : ''}
        </p>
      </div>

      {error && (
        <p role="alert" className="mt-4 rounded-xl bg-danger-soft p-3 text-caption text-danger">
          {error}
        </p>
      )}

      <button type="button" onClick={join} disabled={joining} className="btn btn-primary mt-6 w-full">
        {joining ? 'Taking your ticket…' : user ? 'Join this queue' : 'Sign in to join'}
      </button>

      <p className="mt-6 text-center text-sm">
        <Link to="/" className="tap text-ink-muted">
          Browse other queues
        </Link>
      </p>
    </main>
  )
}
