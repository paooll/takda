import type { ReactElement } from 'react'
import { Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { useAuth } from './lib/auth'
import Dashboard from './pages/Dashboard'
import Discover from './pages/Discover'
import JoinQueue from './pages/JoinQueue'
import MyTickets from './pages/MyTickets'
import SignIn from './pages/SignIn'
import TicketView from './pages/TicketView'

/**
 * Sends signed-out visitors to sign in and remembers where they were headed,
 * so signing in returns them to the page they actually asked for.
 */
function RequireAuth({ children, role }: { children: ReactElement; role?: 'business' }) {
  const { user } = useAuth()
  const location = useLocation()

  if (!user) {
    return <Navigate to="/signin" replace state={{ from: location.pathname }} />
  }

  if (role && user.role !== role) {
    return <Navigate to="/" replace />
  }

  return children
}

export default function App() {
  const { user, loading } = useAuth()

  if (loading) {
    return (
      <div className="flex min-h-dvh items-center justify-center" role="status" aria-label="Loading">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-hairline border-t-primary" />
      </div>
    )
  }

  return (
    <Routes>
      <Route
        path="/"
        element={user?.role === 'business' ? <Navigate to="/counter" replace /> : <Discover />}
      />
      <Route
        path="/tickets"
        element={
          <RequireAuth>
            <MyTickets />
          </RequireAuth>
        }
      />
      <Route
        path="/tickets/:id"
        element={
          <RequireAuth>
            <TicketView />
          </RequireAuth>
        }
      />
      <Route
        path="/counter"
        element={
          <RequireAuth role="business">
            <Dashboard />
          </RequireAuth>
        }
      />
      {/* QR deep link: public, because the code is scanned before sign-in. */}
      <Route path="/j/:slug/:queueId" element={<JoinQueue />} />
      <Route path="/signin" element={<SignIn />} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
