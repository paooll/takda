import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, type Business } from '../lib/api'
import { useAuth } from '../lib/auth'

export default function Discover() {
  const { user, signOut } = useAuth()
  const navigate = useNavigate()

  const [businesses, setBusinesses] = useState<Business[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [joining, setJoining] = useState<number | null>(null)

  useEffect(() => {
    api
      .businesses()
      .then(({ data }) => setBusinesses(data))
      .catch((caught) =>
        setError(caught instanceof Error ? caught.message : 'Could not load queues.'),
      )
  }, [])

  async function join(queueId: number) {
    if (!user) {
      navigate('/signin')
      return
    }

    setJoining(queueId)
    try {
      const { data } = await api.join(queueId)
      navigate(`/tickets/${data.id}`)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Could not join that queue.')
      setJoining(null)
    }
  }

  return (
    <main className="mx-auto w-full max-w-2xl px-4 py-8">
      <header>
        <h1 className="text-title">Takda</h1>
        <p className="mt-1 text-ink-muted">Your turn, not your afternoon.</p>
      </header>

      {error && (
        <p role="alert" className="card mt-6 p-4 text-caption text-danger">
          {error}
        </p>
      )}

      {!businesses && !error && <ListSkeleton />}

      {businesses?.length === 0 && (
        <p className="mt-8 text-ink-muted">No open businesses yet.</p>
      )}

      <ul className="mt-6 space-y-4">
        {businesses?.map((business) => (
          <li key={business.id} className="card p-5">
            <h2 className="text-lg font-semibold">{business.name}</h2>
            {business.description && (
              <p className="mt-1 text-caption text-ink-muted">{business.description}</p>
            )}

            <ul className="mt-4 divide-y divide-hairline">
              {business.active_queues?.map((queue) => (
                <li key={queue.id} className="flex items-center justify-between gap-3 py-3">
                  <div>
                    <p className="font-medium">{queue.name}</p>
                    <p className="text-caption text-ink-muted">
                      About {queue.avg_service_minutes} min per person
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={() => join(queue.id)}
                    disabled={joining !== null}
                    className="btn btn-primary shrink-0"
                  >
                    {joining === queue.id ? 'Joining…' : 'Join'}
                  </button>
                </li>
              ))}
            </ul>
          </li>
        ))}
      </ul>

      <nav className="mt-8 flex gap-2">
        <Link to="/tickets" className="btn btn-secondary flex-1">
          My tickets
        </Link>
        {user ? (
          <button type="button" onClick={signOut} className="btn btn-secondary flex-1">
            Sign out
          </button>
        ) : (
          <Link to="/signin" className="btn btn-secondary flex-1">
            Sign in
          </Link>
        )}
      </nav>
    </main>
  )
}

function ListSkeleton() {
  return (
    <div className="mt-6 animate-pulse space-y-4" aria-busy="true" aria-label="Loading queues">
      {[0, 1].map((i) => (
        <div key={i} className="card h-32" />
      ))}
    </div>
  )
}
