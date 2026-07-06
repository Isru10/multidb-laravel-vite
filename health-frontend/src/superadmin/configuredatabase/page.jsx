import { useMemo, useState } from 'react'

function getApiBaseUrl() {
  const host = window.location.hostname

  if (host === 'localhost' || host === '127.0.0.1') {
    return 'http://localhost:8000'
  }

  return `http://${host}:8000`
}

function parseConnectionString(value) {
  const parsed = new URL(value)

  return {
    host: parsed.hostname,
    port: parsed.port ? Number(parsed.port) : 5432,
    database: parsed.pathname.replace(/^\//, ''),
    username: decodeURIComponent(parsed.username),
    password: decodeURIComponent(parsed.password),
  }
}

export default function ConfigureDatabasePage() {
  const apiBaseUrl = useMemo(() => getApiBaseUrl(), [])
  const token = localStorage.getItem('token') || ''
  const [clinicName, setClinicName] = useState('')
  const [clinicId, setClinicId] = useState('')
  const [subdomain, setSubdomain] = useState('')
  const [adminEmail, setAdminEmail] = useState('')
  const [adminName, setAdminName] = useState('')
  const [connectionString, setConnectionString] = useState('')
  const [status, setStatus] = useState('')
  const [tenantUrl, setTenantUrl] = useState('')
  const [loading, setLoading] = useState(false)

  const loginWithGoogle = async () => {
    const response = await fetch(`${apiBaseUrl}/api/auth/google/redirect`, {
      headers: { Accept: 'application/json' },
      credentials: 'include',
    })
    const data = await response.json()
    window.location.href = data.url
  }

  async function handleSubmit(event) {
    event.preventDefault()

    if (!token) {
      setStatus('Sign in as superadmin first.')
      return
    }

    let parsed
    try {
      parsed = parseConnectionString(connectionString)
    } catch {
      setStatus('Invalid Neon connection string.')
      return
    }

    setLoading(true)
    setStatus('Creating tenant...')
    setTenantUrl('')

    try {
      const response = await fetch(`${apiBaseUrl}/api/organizations`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          clinic_name: clinicName,
          clinic_id: clinicId,
          subdomain,
          admin_email: adminEmail,
          admin_name: adminName,
          connection_string: `postgresql://${encodeURIComponent(parsed.username)}:${encodeURIComponent(parsed.password)}@${parsed.host}:${parsed.port}/${parsed.database}?sslmode=require`,
        }),
      })

      const data = await response.json()
      if (!response.ok) {
        throw new Error(data.error || data.message || 'Tenant creation failed')
      }

      setStatus(data.message || 'Tenant created successfully.')
      setTenantUrl(data.tenant?.tenant_url || `http://${subdomain}.lvh.me:3000`)
    } catch (error) {
      setStatus(error.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div style={styles.page}>
      <div style={styles.card}>
        <p style={styles.badge}>Superadmin</p>
        <h1 style={styles.title}>Configure tenant database</h1>
        <p style={styles.subtitle}>
          Paste the Neon connection string and choose the clinic subdomain. On local dev, the
          tenant becomes <code>subdomain.lvh.me</code>.
        </p>

        {!token && (
          <div style={styles.notice}>
            You are not signed in yet. Use Google first, then come back here.
            <div style={{ marginTop: 12 }}>
              <button onClick={loginWithGoogle} style={styles.primaryButton}>Sign in with Google</button>
            </div>
          </div>
        )}

        <form onSubmit={handleSubmit} style={styles.form}>
          <label style={styles.label}>
            Clinic name
            <input style={styles.input} value={clinicName} onChange={(e) => setClinicName(e.target.value)} placeholder="Yaneth Clinic" required />
          </label>
          <label style={styles.label}>
            Clinic ID
            <input style={styles.input} value={clinicId} onChange={(e) => setClinicId(e.target.value)} placeholder="yanethms" required />
          </label>
          <label style={styles.label}>
            Subdomain
            <input style={styles.input} value={subdomain} onChange={(e) => setSubdomain(e.target.value)} placeholder="yanethms" required />
          </label>
          <label style={styles.label}>
            Admin email
            <input style={styles.input} type="email" value={adminEmail} onChange={(e) => setAdminEmail(e.target.value)} placeholder="admin@yanethms.test" required />
          </label>
          <label style={styles.label}>
            Admin name
            <input style={styles.input} value={adminName} onChange={(e) => setAdminName(e.target.value)} placeholder="Clinic Admin" />
          </label>
          <label style={styles.label}>
            Neon PostgreSQL connection string
            <textarea
              style={styles.textarea}
              value={connectionString}
              onChange={(e) => setConnectionString(e.target.value)}
              placeholder="postgresql://user:pass@host.neon.tech/dbname?sslmode=require"
              rows={5}
              required
            />
          </label>
          <button style={styles.primaryButton} type="submit" disabled={loading}>
            {loading ? 'Creating...' : 'Create tenant'}
          </button>
        </form>

        {status && <div style={styles.status}>{status}</div>}
        {tenantUrl && (
          <div style={styles.successBox}>
            <strong>Tenant URL:</strong>{' '}
            <a href={tenantUrl} target="_blank" rel="noreferrer" style={{ color: '#a7f3d0' }}>
              {tenantUrl}
            </a>
          </div>
        )}
      </div>
    </div>
  )
}

const styles = {
  page: {
    minHeight: '100vh',
    padding: '40px 16px',
    background: 'linear-gradient(180deg, #020617 0%, #0f172a 100%)',
    color: '#e2e8f0',
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'flex-start',
    boxSizing: 'border-box',
  },
  card: {
    width: 'min(900px, 100%)',
    borderRadius: '24px',
    padding: '32px',
    background: 'rgba(15, 23, 42, 0.92)',
    border: '1px solid rgba(148, 163, 184, 0.16)',
    boxShadow: '0 24px 80px rgba(0,0,0,0.35)',
  },
  badge: {
    margin: 0,
    display: 'inline-block',
    padding: '6px 12px',
    borderRadius: '999px',
    background: 'rgba(59, 130, 246, 0.16)',
    color: '#93c5fd',
    fontSize: '12px',
    fontWeight: 700,
    letterSpacing: '0.12em',
    textTransform: 'uppercase',
  },
  title: {
    margin: '12px 0 0',
    color: '#f8fafc',
    fontSize: '40px',
    lineHeight: 1.05,
  },
  subtitle: {
    marginTop: '12px',
    maxWidth: '70ch',
    color: '#cbd5e1',
  },
  notice: {
    marginTop: '18px',
    padding: '12px 14px',
    borderRadius: '14px',
    background: 'rgba(245, 158, 11, 0.12)',
    color: '#fbbf24',
  },
  form: {
    display: 'grid',
    gap: '14px',
    marginTop: '24px',
  },
  label: {
    display: 'grid',
    gap: '8px',
    color: '#e2e8f0',
    fontWeight: 600,
  },
  input: {
    width: '100%',
    boxSizing: 'border-box',
    padding: '13px 14px',
    borderRadius: '12px',
    border: '1px solid rgba(148, 163, 184, 0.22)',
    background: '#020617',
    color: '#f8fafc',
  },
  textarea: {
    width: '100%',
    boxSizing: 'border-box',
    padding: '13px 14px',
    borderRadius: '12px',
    border: '1px solid rgba(148, 163, 184, 0.22)',
    background: '#020617',
    color: '#f8fafc',
    resize: 'vertical',
    fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace',
  },
  primaryButton: {
    padding: '13px 18px',
    borderRadius: '12px',
    border: 'none',
    background: 'linear-gradient(135deg, #2563eb, #7c3aed)',
    color: 'white',
    fontWeight: 700,
    cursor: 'pointer',
    textDecoration: 'none',
  },
  status: {
    marginTop: '16px',
    padding: '12px 14px',
    borderRadius: '12px',
    background: 'rgba(148, 163, 184, 0.12)',
  },
  successBox: {
    marginTop: '16px',
    padding: '12px 14px',
    borderRadius: '12px',
    background: 'rgba(16, 185, 129, 0.12)',
    color: '#bbf7d0',
  },
}
