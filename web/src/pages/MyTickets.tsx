import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, formatWait, type Ticket } from '../lib/api'

export default function MyTickets() {
  const [tickets, setTickets] = useState<Ticket[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api
      .myTickets()
      .then(({ data }) => setTickets(data))
      .catch((caught) =>
        setError(caught instanceof Error ? caught.message : 'Could not load your tickets.'),
      )
  }, [])

  const active = tickets?.filter((t) => t.status === 'waiting' || t.status === 'serving') ?? []
  const past = tickets?.filter((t) => t.status === 'done' || t.status === 'cancelled') ?? []

  return (
    <main className="mx-auto w-full max-w-2xl px-4 py-8">
      <h1 className="text-title">My tickets</h1>

      {error && (
        <p role="alert" className="card mt-6 p-4 text-danger">
          {error}
        </p>
      )}

      {!tickets && !error && <div className="card mt-6 h-24 animate-pulse" aria-busy="true" />}

      {tickets?.length === 0 && (
        <div className="card mt-6 p-6 text-center">
          <p className="text-ink-muted">You have no tickets yet.</p>
          <Link to="/" className="btn btn-primary mt-4 w-full">
            Join a queue
          </Link>
        </div>
      )}

      {active.length > 0 && (
        <Section title="Active">
          {active.map((ticket) => (
            <Link
              key={ticket.id}
              to={`/tickets/${ticket.id}`}
              className="card flex items-center justify-between p-4"
            >
              <div>
                <p className="font-medium">{ticket.business.name}</p>
                <p className="text-caption text-ink-muted">
                  {ticket.queue.name} · {ticket.code}
                </p>
              </div>
              <div className="text-right">
                <p className="text-lg font-semibold tabular-nums">#{ticket.position}</p>
                <p className="text-xs text-ink-muted">{formatWait(ticket.estimated_wait_seconds)}</p>
              </div>
            </Link>
          ))}
        </Section>
      )}

      {past.length > 0 && (
        <Section title="History">
          {past.map((ticket) => (
            <div key={ticket.id} className="card flex items-center justify-between p-4">
              <div>
                <p className="font-medium">{ticket.business.name}</p>
                <p className="text-caption text-ink-muted">
                  {ticket.queue.name} · {ticket.code}
                </p>
              </div>
              <span className="text-caption capitalize text-ink-muted">{ticket.status}</span>
            </div>
          ))}
        </Section>
      )}
    </main>
  )
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="mt-6">
      <h2 className="mb-2 text-caption font-semibold uppercase tracking-wide text-ink-muted">{title}</h2>
      <ul className="space-y-2">{children}</ul>
    </section>
  )
}
