import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { ApiError, type Role } from '../lib/api'
import { useAuth } from '../lib/auth'

export default function SignIn() {
  const { signIn, signUp } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  // Where to land after authenticating. Set when a protected route or a QR
  // deep link bounced the customer here.
  const from = (location.state as { from?: string } | null)?.from

  const [mode, setMode] = useState<'signin' | 'signup'>('signin')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [role, setRole] = useState<Role>('customer')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function submit(event: React.FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)

    try {
      if (mode === 'signin') {
        await signIn(email, password)
      } else {
        await signUp({ name, email, password, role })
      }
      navigate(from ?? '/', { replace: true })
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? (Object.values(caught.errors).flat()[0] ?? caught.message)
          : 'Something went wrong. Please try again.',
      )
      setBusy(false)
    }
  }

  return (
    <main className="mx-auto w-full max-w-md px-4 py-10">
      <h1 className="text-title">
        {mode === 'signin' ? 'Sign in' : 'Create your account'}
      </h1>
      <p className="mt-1 text-ink-muted">
        {mode === 'signin'
          ? 'Pick up where you left off.'
          : 'Join a queue from anywhere.'}
      </p>

      <form onSubmit={submit} className="mt-8 space-y-4">
        {mode === 'signup' && (
          <>
            <Field id="name" label="Full name" value={name} onChange={setName} type="text" autoComplete="name" />
            <Field
              id="role"
              label="I am a"
              type="select"
              value={role}
              onChange={(v) => setRole(v as Role)}
              options={[
                { value: 'customer', label: 'Customer' },
                { value: 'business', label: 'Business owner' },
              ]}
            />
          </>
        )}

        <Field
          id="email"
          label="Email"
          type="email"
          value={email}
          onChange={setEmail}
          autoComplete="email"
          required
        />
        <Field
          id="password"
          label="Password"
          type="password"
          value={password}
          onChange={setPassword}
          autoComplete={mode === 'signin' ? 'current-password' : 'new-password'}
          required
        />

        {error && (
          <p role="alert" className="rounded-xl bg-danger-soft p-3 text-caption text-danger">
            {error}
          </p>
        )}

        <button type="submit" disabled={busy} className="btn btn-primary w-full">
          {busy ? 'Please wait…' : mode === 'signin' ? 'Sign in' : 'Create account'}
        </button>
      </form>

      <p className="mt-6 text-center text-caption text-ink-muted">
        {mode === 'signin' ? "Don't have an account? " : 'Already registered? '}
        <button
          type="button"
          className="tap font-medium text-primary underline"
          onClick={() => {
            setMode(mode === 'signin' ? 'signup' : 'signin')
            setError(null)
          }}
        >
          {mode === 'signin' ? 'Create one' : 'Sign in'}
        </button>
      </p>

      <p className="mt-8 text-center text-sm">
        <Link to="/" className="tap text-caption text-ink-muted">
          ← Back to queues
        </Link>
      </p>
    </main>
  )
}

interface FieldProps {
  id: string
  label: string
  value: string
  onChange: (value: string) => void
  type: string
  options?: Array<{ value: string; label: string }>
  autoComplete?: string
  required?: boolean
}

function Field({ id, label, value, onChange, type, options, autoComplete, required }: FieldProps) {
  return (      <div>
      <label htmlFor={id} className="mb-1 block text-caption font-semibold">
        {label}
      </label>
      {options ? (
        <select
          id={id}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="tap w-full rounded-xl border border-hairline bg-surface px-3 py-2"
        >
          {options.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      ) : (
        <input
          id={id}
          type={type}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          autoComplete={autoComplete}
          required={required}
          className="tap w-full rounded-xl border border-hairline bg-surface px-3 py-2 text-base"
        />
      )}
    </div>
  )
}
