import React, { useEffect, useState, useCallback } from 'react';
import { adminSupabase } from '../adminSupabaseClient';
import { Link } from 'react-router-dom';
import { useAuth } from '../auth';
import {
  Activity,
  RefreshCw,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  Server,
  Wifi,
  Database,
  Mail,
  ShieldCheck,
  Clock,
  Key,
  Globe,
  Save,
  Check,
} from 'lucide-react';

interface HealthStatus {
  service: string;
  status: 'ONLINE' | 'OFFLINE' | 'DEGRADED' | 'CHECKING';
  latencyMs?: number;
  details?: string;
  url?: string;
}

export default function SystemHealth() {
  const { session } = useAuth();
  const defaultBackend =
    ((import.meta as any).env?.VITE_BACKEND_URL as string) ||
    'https://api.swapnopay.top';

  const [backendUrl, setBackendUrl] = useState(() => {
    const saved = localStorage.getItem('swapnopay_backend_url');
    if (saved && !saved.includes('pay.swapnopay.top') && !saved.includes('admin.swapnopay.top')) {
      return saved;
    }
    return defaultBackend;
  });
  const [adminSecret, setAdminSecret] = useState(
    sessionStorage.getItem('swapnopay_admin_secret') ||
      localStorage.getItem('swapnopay_admin_secret') ||
      ''
  );
  const [loading, setLoading] = useState(false);
  const [lastCheckTime, setLastCheckTime] = useState<string | null>(null);
  const [saveSuccess, setSaveSuccess] = useState(false);

  const [services, setServices] = useState<HealthStatus[]>([
    { service: 'Admin Supabase DB', status: 'CHECKING', details: 'Platform Owner PostgreSQL Database' },
    { service: 'SwapnoPay Backend Server', status: 'CHECKING', details: 'Express.js + Socket.io Server' },
    { service: 'Gateway Receipt Service', status: 'CHECKING', details: 'Gmail OAuth Email Receipts Engine' },
  ]);

  const [backendMeta, setBackendMeta] = useState<{ clientsCount?: number; version?: string } | null>(null);

  const runDiagnostics = useCallback(async () => {
    setLoading(true);
    setLastCheckTime(new Date().toLocaleTimeString());

    const updated: HealthStatus[] = [];

    // 1. Test Admin Supabase Database
    const startDb = performance.now();
    try {
      const { error } = await adminSupabase.from('gateway_config').select('id').limit(1);
      const latencyDb = Math.round(performance.now() - startDb);
      if (error) {
        updated.push({
          service: 'Admin Supabase DB',
          status: 'OFFLINE',
          latencyMs: latencyDb,
          details: error.message,
        });
      } else {
        updated.push({
          service: 'Admin Supabase DB',
          status: 'ONLINE',
          latencyMs: latencyDb,
          details: 'Connected to platform owner database',
        });
      }
    } catch (e: any) {
      updated.push({
        service: 'Admin Supabase DB',
        status: 'OFFLINE',
        details: e.message || 'Connection failed',
      });
    }

    // 2. Test SwapnoPay Backend Server (/healthz)
    const cleanBackend = backendUrl.trim().replace(/\/$/, '');
    const startBackend = performance.now();
    try {
      const res = await fetch(`${cleanBackend}/healthz`, { signal: AbortSignal.timeout(4000) });
      const latencyBackend = Math.round(performance.now() - startBackend);
      if (res.ok) {
        const data = await res.json();
        setBackendMeta({ clientsCount: data.socket_io_clients, version: data.version });
        updated.push({
          service: 'SwapnoPay Backend Server',
          status: 'ONLINE',
          latencyMs: latencyBackend,
          details: `Socket.io active clients: ${data.socket_io_clients ?? 0}`,
          url: `${cleanBackend}/healthz`,
        });
      } else {
        updated.push({
          service: 'SwapnoPay Backend Server',
          status: 'DEGRADED',
          latencyMs: latencyBackend,
          details: `HTTP ${res.status}`,
        });
      }
    } catch (e: any) {
      updated.push({
        service: 'SwapnoPay Backend Server',
        status: 'OFFLINE',
        details: 'Backend unreachable. Ensure server is running.',
      });
    }

    // 3. Test Gateway Admin Health Endpoint (/v1/admin/health)
    try {
      const startAdmin = performance.now();
      const authHeaders: Record<string, string> = {};
      if (session?.access_token) {
        authHeaders['Authorization'] = `Bearer ${session.access_token}`;
      } else if (adminSecret) {
        authHeaders['X-Admin-Secret'] = adminSecret;
      }

      const res = await fetch(`${cleanBackend}/v1/admin/health`, {
        headers: authHeaders,
        signal: AbortSignal.timeout(4000),
      });
      const latencyAdmin = Math.round(performance.now() - startAdmin);
      if (res.ok) {
        const data = await res.json();
        const receiptStatus = data.gateway_service === 'ok' ? 'ONLINE' : 'DEGRADED';
        updated.push({
          service: 'Gateway Receipt Service',
          status: receiptStatus,
          latencyMs: latencyAdmin,
          details: data.gateway_service === 'ok' ? 'Email & Invoice Receipt Engine Active' : `Service health: ${data.gateway_service}`,
        });
      } else {
        updated.push({
          service: 'Gateway Receipt Service',
          status: 'OFFLINE',
          details: 'Admin authorization required or endpoint down',
        });
      }
    } catch {
      updated.push({
        service: 'Gateway Receipt Service',
        status: 'OFFLINE',
        details: 'Gateway health endpoint offline or timeout',
      });
    }

    setServices(updated);
    setLoading(false);
  }, [backendUrl, adminSecret, session]);

  useEffect(() => {
    runDiagnostics();
  }, [runDiagnostics]);

  const saveSettings = () => {
    localStorage.setItem('swapnopay_backend_url', backendUrl);
    if (adminSecret) {
      sessionStorage.setItem('swapnopay_admin_secret', adminSecret);
      localStorage.setItem('swapnopay_admin_secret', adminSecret);
    } else {
      sessionStorage.removeItem('swapnopay_admin_secret');
      localStorage.removeItem('swapnopay_admin_secret');
    }
    setSaveSuccess(true);
    setTimeout(() => setSaveSuccess(false), 2500);
    runDiagnostics();
  };

  const isAllOperational = services.every(s => s.status === 'ONLINE');
  const hasOffline = services.some(s => s.status === 'OFFLINE');

  const getStatusBadge = (status: HealthStatus['status']) => {
    switch (status) {
      case 'ONLINE':
        return (
          <span className="status-pill success">
            <span className="status-dot" />
            ONLINE
          </span>
        );
      case 'DEGRADED':
        return (
          <span className="status-pill warning">
            <span className="status-dot" />
            DEGRADED
          </span>
        );
      case 'OFFLINE':
        return (
          <span className="status-pill danger">
            <span className="status-dot" />
            OFFLINE
          </span>
        );
      default:
        return (
          <span className="status-pill neutral">
            <span className="status-dot" />
            CHECKING...
          </span>
        );
    }
  };

  const getServiceIcon = (name: string) => {
    if (name.includes('DB') || name.includes('Supabase')) {
      return <Database size={16} color="var(--brand-primary)" />;
    }
    if (name.includes('Receipt')) {
      return <Mail size={16} color="#7C3AED" />;
    }
    return <Server size={16} color="#059669" />;
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 16 }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 4 }}>
            <h1 style={{ fontSize: 24, fontWeight: 700, color: 'var(--text-primary)', margin: 0, letterSpacing: '-0.02em' }}>
              System Health & Diagnostics
            </h1>
            <span className={`status-pill ${isAllOperational ? 'success' : hasOffline ? 'danger' : 'warning'}`}>
              <span className="status-dot" />
              {isAllOperational ? 'All Systems Healthy' : hasOffline ? 'Service Alert' : 'Degraded State'}
            </span>
          </div>
          <p style={{ margin: 0, color: 'var(--text-secondary)', fontSize: 13 }}>
            Continuous latency telemetry, socket heartbeat, and infrastructure status for SwapnoPay platform services.
          </p>
        </div>

        <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
          <button
            className="btn btn-primary btn-sm"
            onClick={runDiagnostics}
            disabled={loading}
          >
            <RefreshCw size={13} className={loading ? 'spin' : ''} />
            {loading ? 'Running Tests...' : 'Run Diagnostics'}
          </button>
          <Link to="/dashboard" className="btn btn-secondary btn-sm">
            Overview Dashboard
          </Link>
        </div>
      </div>

      {/* Health Overview Cards */}
      <div className="kpi-grid">
        {/* Card 1: Health Status */}
        <div className="kpi-card">
          <div className="kpi-top">
            <span className="kpi-label">OVERALL STATUS</span>
            <div
              className="kpi-icon-wrap"
              style={{
                background: isAllOperational
                  ? 'var(--success-subtle)'
                  : hasOffline
                  ? 'var(--danger-subtle)'
                  : 'var(--warning-subtle)',
                color: isAllOperational
                  ? 'var(--success)'
                  : hasOffline
                  ? 'var(--danger)'
                  : 'var(--warning)',
              }}
            >
              {isAllOperational ? <CheckCircle2 size={15} /> : <AlertTriangle size={15} />}
            </div>
          </div>
          <div
            className="kpi-value"
            style={{
              fontSize: 16,
              fontWeight: 800,
              color: isAllOperational ? 'var(--success)' : hasOffline ? 'var(--danger)' : 'var(--warning)',
            }}
          >
            {isAllOperational
              ? 'OPERATIONAL'
              : hasOffline
              ? 'DEGRADED / DOWN'
              : 'PARTIAL OUTAGE'}
          </div>
          <div className="kpi-subtext">
            {services.filter(s => s.status === 'ONLINE').length} of {services.length} services responsive
          </div>
        </div>

        {/* Card 2: Socket.io Active Clients */}
        <div className="kpi-card">
          <div className="kpi-top">
            <span className="kpi-label">SOCKET.IO CLIENTS</span>
            <div className="kpi-icon-wrap" style={{ background: 'rgba(79, 70, 229, 0.1)', color: '#4F46E5' }}>
              <Wifi size={15} />
            </div>
          </div>
          <div className="kpi-value" style={{ color: 'var(--text-primary)' }}>
            {backendMeta?.clientsCount ?? '—'}
          </div>
          <div className="kpi-subtext">
            Live merchant checkout sessions connected
          </div>
        </div>

        {/* Card 3: Backend Version */}
        <div className="kpi-card">
          <div className="kpi-top">
            <span className="kpi-label">BACKEND VERSION</span>
            <div className="kpi-icon-wrap" style={{ background: 'rgba(124, 58, 237, 0.1)', color: '#7C3AED' }}>
              <Activity size={15} />
            </div>
          </div>
          <div className="kpi-value" style={{ color: 'var(--text-primary)' }}>
            {backendMeta?.version ? `v${backendMeta.version}` : 'v2.0.0'}
          </div>
          <div className="kpi-subtext">
            Current deployed release
          </div>
        </div>

        {/* Card 4: Last Diagnostic Check */}
        <div className="kpi-card">
          <div className="kpi-top">
            <span className="kpi-label">LAST CHECK</span>
            <div className="kpi-icon-wrap" style={{ background: 'var(--bg-muted)', color: 'var(--text-secondary)' }}>
              <Clock size={15} />
            </div>
          </div>
          <div className="kpi-value" style={{ fontSize: 18, color: 'var(--text-primary)' }}>
            {lastCheckTime || 'Just now'}
          </div>
          <div className="kpi-subtext">
            Auto-polled upon page load
          </div>
        </div>
      </div>

      {/* Connected Services Table */}
      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <div
          style={{
            padding: '16px 20px',
            borderBottom: '1px solid var(--border-default)',
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
          }}
        >
          <div>
            <h3 style={{ margin: 0, fontSize: 15, fontWeight: 700, color: 'var(--text-primary)' }}>
              Connected Core Services ({services.length})
            </h3>
            <p style={{ margin: '2px 0 0', fontSize: 12, color: 'var(--text-secondary)' }}>
              Real-time latency response times and service discovery targets
            </p>
          </div>
          <button
            className="btn btn-secondary btn-sm"
            onClick={runDiagnostics}
            disabled={loading}
          >
            <RefreshCw size={12} className={loading ? 'spin' : ''} />
            Re-test
          </button>
        </div>

        <div className="enterprise-table-container">
          <table className="enterprise-table">
            <thead>
              <tr>
                <th>Service Name</th>
                <th>Status</th>
                <th>Latency</th>
                <th>Diagnostic Details</th>
              </tr>
            </thead>
            <tbody>
              {services.map(s => (
                <tr key={s.service}>
                  <td>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                      <div
                        style={{
                          width: 32,
                          height: 32,
                          borderRadius: 8,
                          background: 'var(--bg-subtle)',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          border: '1px solid var(--border-default)',
                        }}
                      >
                        {getServiceIcon(s.service)}
                      </div>
                      <div>
                        <div style={{ fontWeight: 600, fontSize: 13.5, color: 'var(--text-primary)' }}>
                          {s.service}
                        </div>
                        {s.url && (
                          <div style={{ fontSize: 11, color: 'var(--text-muted)', fontFamily: 'var(--font-mono)' }}>
                            {s.url}
                          </div>
                        )}
                      </div>
                    </div>
                  </td>
                  <td>{getStatusBadge(s.status)}</td>
                  <td>
                    {s.latencyMs !== undefined ? (
                      <span
                        style={{
                          display: 'inline-flex',
                          alignItems: 'center',
                          gap: 6,
                          fontSize: 12,
                          fontWeight: 700,
                          fontFamily: 'var(--font-mono)',
                          color:
                            s.latencyMs < 150
                              ? 'var(--success)'
                              : s.latencyMs < 500
                              ? 'var(--warning)'
                              : 'var(--danger)',
                        }}
                      >
                        <span
                          style={{
                            width: 6,
                            height: 6,
                            borderRadius: '50%',
                            background:
                              s.latencyMs < 150
                                ? 'var(--success)'
                                : s.latencyMs < 500
                                ? 'var(--warning)'
                                : 'var(--danger)',
                          }}
                        />
                        {s.latencyMs} ms
                      </span>
                    ) : (
                      <span style={{ color: 'var(--text-muted)', fontSize: 12 }}>—</span>
                    )}
                  </td>
                  <td style={{ fontSize: 12.5, color: 'var(--text-secondary)' }}>
                    {s.details || '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Backend Connection Settings */}
      <div className="card" style={{ padding: '20px 24px' }}>
        <div style={{ marginBottom: 16 }}>
          <h3 style={{ margin: 0, fontSize: 15, fontWeight: 700, color: 'var(--text-primary)' }}>
            Backend Connection & Authentication
          </h3>
          <p style={{ margin: '3px 0 0', fontSize: 12.5, color: 'var(--text-secondary)' }}>
            Configure target backend endpoint and administrative credentials used for system telemetry and health probes.
          </p>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '1.5fr 1.5fr auto', gap: 14, alignItems: 'flex-end' }}>
          <div>
            <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: 'var(--text-secondary)', marginBottom: 6 }}>
              SWAPNOPAY BACKEND URL
            </label>
            <div style={{ position: 'relative' }}>
              <Globe size={14} style={{ position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
              <input
                className="input"
                style={{ paddingLeft: 32 }}
                value={backendUrl}
                onChange={e => setBackendUrl(e.target.value)}
                placeholder="https://api.swapnopay.top"
              />
            </div>
          </div>

          <div>
            <label style={{ display: 'block', fontSize: 11, fontWeight: 600, color: 'var(--text-secondary)', marginBottom: 6 }}>
              ADMIN SECRET HEADER {session?.access_token && <span style={{ color: 'var(--success)' }}>(Active JWT Session)</span>}
            </label>
            <div style={{ position: 'relative' }}>
              <Key size={14} style={{ position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)', color: 'var(--text-muted)' }} />
              <input
                className="input"
                type="password"
                style={{ paddingLeft: 32 }}
                value={adminSecret}
                onChange={e => setAdminSecret(e.target.value)}
                placeholder={session?.access_token ? 'Using session JWT (override optional)' : 'X-Admin-Secret passphrase'}
              />
            </div>
          </div>

          <button
            className="btn btn-primary"
            onClick={saveSettings}
            style={{ height: 38, minWidth: 100 }}
          >
            {saveSuccess ? (
              <>
                <Check size={14} />
                Saved
              </>
            ) : (
              <>
                <Save size={14} />
                Save
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}
