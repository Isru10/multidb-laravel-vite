import { useEffect, useMemo, useState } from 'react'
import ConfigureDatabasePage from './superadmin/configuredatabase/page.jsx'

function getApiBaseUrl() {
  const host = window.location.hostname

  if (host === 'localhost' || host === '127.0.0.1') {
    return 'http://localhost:8000'
  }

  return `http://${host}:8000`
}

function isSuperadminPath() {
  return window.location.pathname.startsWith('/superadmin/configuredatabase')
}

export default function App() {
  if (isSuperadminPath()) {
    return <ConfigureDatabasePage />
  }

  return <TenantApp />
}

function TenantApp() {
  const apiBaseUrl = useMemo(() => getApiBaseUrl(), [])
  const [token, setToken] = useState(localStorage.getItem('token') || '')
  const [posts, setPosts] = useState([])
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [error, setError] = useState('')
  const [status, setStatus] = useState('')

  useEffect(() => {
    const urlParams = new URLSearchParams(window.location.search)
    const urlToken = urlParams.get('token')

    if (urlToken) {
      localStorage.setItem('token', urlToken)
      setToken(urlToken)
      window.history.replaceState({}, document.title, window.location.pathname)
    }
  }, [])

  const currentHost = window.location.hostname
  const isCentralHost = currentHost === 'localhost' || currentHost === '127.0.0.1'

  const loginWithGoogle = async () => {
    try {
      const response = await fetch(`${apiBaseUrl}/api/auth/google/redirect`, {
        headers: { Accept: 'application/json' },
        credentials: 'include',
      })
      const data = await response.json()
      window.location.href = data.url
    } catch (err) {
      setError('Unable to start Google sign-in.')
    }
  }

  const fetchPosts = async () => {
    try {
      setError('')
      setStatus('Loading posts...')
      const response = await fetch(`${apiBaseUrl}/api/posts`, {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
        },
      })
      const data = await response.json()
      if (!response.ok) throw new Error(data.error || data.message || 'Failed to fetch posts')
      setPosts(data)
      setStatus(`Loaded ${data.length} post(s).`)
    } catch (err) {
      setError(err.message)
    }
  }

  const createPost = async (e) => {
    e.preventDefault()
    try {
      setError('')
      const response = await fetch(`${apiBaseUrl}/api/posts`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({ title, description }),
      })
      const data = await response.json()
      if (!response.ok) throw new Error(data.error || data.message || 'Failed to create post')

      setTitle('')
      setDescription('')
      setStatus(data.message || 'Post created successfully.')
      fetchPosts()
    } catch (err) {
      setError(err.message)
    }
  }

  const logout = () => {
    localStorage.removeItem('token')
    setToken('')
    setPosts([])
    setStatus('Logged out.')
  }

  if (!token) {
    return (
      <div style={pageStyles}>
        <div style={cardStyles}>
          <p style={badgeStyles}>{isCentralHost ? 'Central domain' : 'Tenant domain'}</p>
          <h1 style={titleStyles}>{isCentralHost ? 'Superadmin / Central Login' : 'Tenant Login'}</h1>
          <p style={textStyles}>
            {isCentralHost
              ? 'Sign in as the seeded superadmin, then open the tenant configurator.'
              : 'Sign in on the tenant subdomain to get tenant-scoped access.'}
          </p>
          <button onClick={loginWithGoogle} style={primaryButtonStyles}>Sign in with Google</button>
          <div style={{ marginTop: '16px', color: '#94a3b8' }}>
            Open superadmin configurator: <a href="/superadmin/configuredatabase" style={{ color: '#93c5fd' }}>/superadmin/configuredatabase</a>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div style={pageStyles}>
      <div style={cardStyles}>
        <div style={headerRowStyles}>
          <div>
            <p style={badgeStyles}>{isCentralHost ? 'Central authenticated session' : `Tenant: ${currentHost}`}</p>
            <h1 style={titleStyles}>{isCentralHost ? 'Superadmin Dashboard' : 'Tenant Dashboard'}</h1>
          </div>
          <button onClick={logout} style={secondaryButtonStyles}>Logout</button>
        </div>

        <div style={infoGridStyles}>
          <div style={infoBoxStyles}>
            <strong>API base</strong>
            <div style={{ marginTop: 6 }}>{apiBaseUrl}</div>
          </div>
          <div style={infoBoxStyles}>
            <strong>Frontend host</strong>
            <div style={{ marginTop: 6 }}>{window.location.origin}</div>
          </div>
        </div>

        <div style={{ marginTop: '16px', display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
          {isCentralHost ? (
            <a href="/superadmin/configuredatabase" style={linkButtonStyles}>Open tenant configurator</a>
          ) : (
            <button onClick={fetchPosts} style={linkButtonStyles}>Load tenant posts</button>
          )}
        </div>

        {status && <div style={statusStyles}>{status}</div>}
        {error && <div style={errorStyles}>{error}</div>}

        {!isCentralHost && (
          <form onSubmit={createPost} style={formStyles}>
            <h2 style={{ margin: 0, color: '#f8fafc' }}>Create post</h2>
            <input
              style={inputStyles}
              placeholder="Post Title"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              required
            />
            <textarea
              style={textareaStyles}
              placeholder="Post Description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              required
              rows={5}
            />
            <button type="submit" style={primaryButtonStyles}>Create Post</button>
          </form>
        )}

        {!isCentralHost && (
          <div style={{ marginTop: '24px' }}>
            <h2 style={{ color: '#f8fafc' }}>Blog posts</h2>
            {posts.length === 0 ? (
              <p style={textStyles}>No posts loaded yet.</p>
            ) : (
              <div style={{ display: 'grid', gap: '12px', marginTop: '12px' }}>
                {posts.map((post) => (
                  <article key={post.id} style={postCardStyles}>
                    <h3 style={{ margin: '0 0 8px', color: '#f8fafc' }}>{post.title}</h3>
                    <p style={{ margin: 0, color: '#cbd5e1' }}>{post.description}</p>
                  </article>
                ))}
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}

const pageStyles = {
  minHeight: '100vh',
  background: 'radial-gradient(circle at top, #1e293b 0%, #020617 55%, #020617 100%)',
  color: '#e2e8f0',
  padding: '32px 16px',
  boxSizing: 'border-box',
}

const cardStyles = {
  width: 'min(980px, 100%)',
  margin: '0 auto',
  background: 'rgba(15, 23, 42, 0.88)',
  border: '1px solid rgba(148, 163, 184, 0.18)',
  borderRadius: '24px',
  padding: '28px',
  boxShadow: '0 24px 80px rgba(0, 0, 0, 0.35)',
}

const headerRowStyles = {
  display: 'flex',
  justifyContent: 'space-between',
  alignItems: 'flex-start',
  gap: '16px',
  flexWrap: 'wrap',
}

const badgeStyles = {
  display: 'inline-block',
  margin: 0,
  padding: '6px 12px',
  borderRadius: '999px',
  background: 'rgba(59, 130, 246, 0.14)',
  color: '#93c5fd',
  fontSize: '12px',
  fontWeight: 700,
  letterSpacing: '0.12em',
  textTransform: 'uppercase',
}

const titleStyles = {
  margin: '10px 0 0',
  fontSize: '42px',
  lineHeight: 1.05,
  color: '#f8fafc',
}

const textStyles = {
  marginTop: '12px',
  color: '#cbd5e1',
}

const primaryButtonStyles = {
  padding: '12px 18px',
  borderRadius: '12px',
  border: 'none',
  background: 'linear-gradient(135deg, #2563eb, #7c3aed)',
  color: 'white',
  fontWeight: 700,
  cursor: 'pointer',
  textDecoration: 'none',
  display: 'inline-flex',
  alignItems: 'center',
  justifyContent: 'center',
}

const secondaryButtonStyles = {
  ...primaryButtonStyles,
  background: 'rgba(148, 163, 184, 0.18)',
}

const linkButtonStyles = {
  ...primaryButtonStyles,
}

const statusStyles = {
  marginTop: '16px',
  padding: '12px 14px',
  borderRadius: '12px',
  background: 'rgba(148, 163, 184, 0.12)',
}

const errorStyles = {
  marginTop: '16px',
  padding: '12px 14px',
  borderRadius: '12px',
  background: 'rgba(239, 68, 68, 0.14)',
  color: '#fecaca',
}

const infoGridStyles = {
  display: 'grid',
  gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
  gap: '12px',
  marginTop: '20px',
}

const infoBoxStyles = {
  padding: '14px 16px',
  borderRadius: '16px',
  background: 'rgba(15, 23, 42, 0.95)',
  border: '1px solid rgba(148, 163, 184, 0.14)',
}

const formStyles = {
  display: 'grid',
  gap: '12px',
  marginTop: '24px',
}

const inputStyles = {
  width: '100%',
  boxSizing: 'border-box',
  padding: '12px 14px',
  borderRadius: '12px',
  border: '1px solid rgba(148, 163, 184, 0.22)',
  background: '#0f172a',
  color: '#f8fafc',
}

const textareaStyles = {
  ...inputStyles,
  resize: 'vertical',
}

const postCardStyles = {
  padding: '16px',
  borderRadius: '16px',
  border: '1px solid rgba(148, 163, 184, 0.16)',
  background: 'rgba(15, 23, 42, 0.96)',
}