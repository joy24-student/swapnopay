import React, { useEffect, useState, useMemo } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  adminSupabase,
  updateMerchantAccountStatus,
  deleteMerchantAccount,
  getBackendBaseUrl,
  getAdminHeaders
} from '../adminSupabaseClient'
import AddMerchantModal from '../components/AddMerchantModal'
import DetailDrawer from '../components/DetailDrawer'
import KycInspectionModal from '../components/KycInspectionModal'
import {
  Users,
  Search,
  Plus,
  ArrowUpDown,
  ExternalLink,
  ShieldCheck,
  MoreHorizontal,
  Copy,
  CheckCircle2,
  AlertCircle,
  AlertTriangle,
  AlertOctagon,
  Clock,
  Ban,
  Database,
  Mail,
  Phone,
  Store,
  Calendar,
  Trash2,
  Globe,
  Smartphone,
  CreditCard,
  RefreshCw,
  Activity,
  Zap,
  Info,
  Link2
} from 'lucide-react'

export interface MerchantRecord {
  id: string
  business_name: string
  email?: string
  phone?: string
  business_type?: string
  website?: string
  default_number?: string
  status: 'ACTIVE' | 'SUSPENDED' | 'PENDING_VERIFICATION'
  subscription_tier: 'STARTER' | 'PRO' | 'ENTERPRISE'
  kyc_status?: string
  kyc_rejection_reason?: string
  nid_number?: string
  nid_front_url?: string
  nid_back_url?: string
  created_at?: string
  supabase_url?: string
  supabase_anon_key?: string
  // Enhanced Operational Diagnostic Fields
  shop_url?: string | null
  shop_slug?: string | null
  health?: 'HEALTHY' | 'DEGRADED' | 'CRITICAL' | 'SUSPENDED'
  device_status?: 'ONLINE' | 'STALE' | 'OFFLINE' | 'NOT_PAIRED'
  device_last_active?: string | null
  issues?: { level: 'CRITICAL' | 'WARNING' | 'INFO'; code: string; message: string }[]
  issues_count?: number
  has_mfs_numbers?: boolean
}

export default function Merchants() {
  const [rows, setRows] = useState<MerchantRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<'ALL' | 'PENDING_KYC' | 'VERIFIED_KYC' | 'ATTENTION' | 'SUSPENDED'>('ALL')
  const [showAddModal, setShowAddModal] = useState(false)
  const [selectedMerchant, setSelectedMerchant] = useState<MerchantRecord | null>(null)
  const [inspectKycMerchant, setInspectKycMerchant] = useState<MerchantRecord | null>(null)
  const [copiedId, setCopiedId] = useState<string | null>(null)
  const [actionLoadingId, setActionLoadingId] = useState<string | null>(null)
  const navigate = useNavigate()

  const loadMerchants = async () => {
    try {
      // 1. First attempt enriched backend overview endpoint
      try {
        const base = getBackendBaseUrl()
        const headers = await getAdminHeaders()
        const res = await fetch(`${base.replace(/\/$/, '')}/v1/admin/merchants-overview`, { headers })
        if (res.ok) {
          const json = await res.json()
          if (json.ok && Array.isArray(json.merchants)) {
            setRows(json.merchants)
            setLoading(false)
            return
          }
        }
      } catch (err) {
        console.warn('[Merchants] Backend overview fetch notice (using direct Supabase parallel load):', err)
      }

      // 2. Parallel client-side query across Supabase tables
      const [
        { data: merchantsData },
        { data: gwsData },
        { data: devsData },
        { data: appealsData }
      ] = await Promise.all([
        adminSupabase.from('merchants').select('*').order('created_at', { ascending: false }),
        adminSupabase.from('merchant_gateway_settings').select('*'),
        adminSupabase.from('merchant_devices').select('*'),
        adminSupabase.from('payment_appeals').select('id, merchant_id, status').eq('status', 'PENDING')
      ])

      const gwMap = new Map()
      for (const g of gwsData || []) {
        gwMap.set(g.merchant_id, g)
      }

      const devMap = new Map()
      for (const d of devsData || []) {
        devMap.set(d.merchant_id, d)
      }

      const appealCounts = new Map()
      for (const a of appealsData || []) {
        appealCounts.set(a.merchant_id, (appealCounts.get(a.merchant_id) || 0) + 1)
      }

      const enriched: MerchantRecord[] = (merchantsData || []).map((m: any) => {
        const cleanId = m.id
        const gw = gwMap.get(cleanId) || (m.user_id ? gwMap.get(m.user_id) : null) || {}
        const dev = devMap.get(cleanId) || (m.user_id ? devMap.get(m.user_id) : null) || null
        const pendingAppeals = appealCounts.get(cleanId) || 0

        const issues: { level: 'CRITICAL' | 'WARNING' | 'INFO'; code: string; message: string }[] = []

        // MFS numbers check
        const recNums = gw.receiving_numbers || {}
        const hasNumbers = Object.values(recNums).some(v => typeof v === 'string' && v.trim().length >= 10)
        if (!hasNumbers) {
          issues.push({ level: 'CRITICAL', code: 'NO_MFS', message: 'No MFS numbers set' })
        }

        // Terminal device check
        let devStatus: 'ONLINE' | 'STALE' | 'OFFLINE' | 'NOT_PAIRED' = 'NOT_PAIRED'
        if (dev) {
          const ageMs = Date.now() - new Date(dev.last_active_at).getTime()
          if (ageMs < 10 * 60 * 1000) devStatus = 'ONLINE'
          else if (ageMs < 60 * 60 * 1000) {
            devStatus = 'STALE'
            issues.push({ level: 'WARNING', code: 'PHONE_IDLE', message: 'SMS phone idle > 10m' })
          } else {
            devStatus = 'OFFLINE'
            issues.push({ level: 'CRITICAL', code: 'PHONE_OFFLINE', message: 'SMS phone offline' })
          }
        } else {
          issues.push({ level: 'WARNING', code: 'NO_PHONE', message: 'No SMS phone paired' })
        }

        // KYC check
        if (m.kyc_status === 'PENDING' || m.kyc_status === 'PENDING_REVIEW') {
          issues.push({ level: 'WARNING', code: 'KYC_PENDING', message: 'KYC review pending' })
        } else if (m.kyc_status === 'REJECTED') {
          issues.push({ level: 'CRITICAL', code: 'KYC_REJECTED', message: 'KYC rejected' })
        }

        // DB check
        const subUrl = gw.supabase_url || m.supabase_url
        if (!subUrl || subUrl === 'null') {
          issues.push({ level: 'WARNING', code: 'NO_DB', message: 'No dedicated DB' })
        }

        if (pendingAppeals > 0) {
          issues.push({ level: 'WARNING', code: 'APPEALS', message: `${pendingAppeals} dispute(s)` })
        }

        let health: 'HEALTHY' | 'DEGRADED' | 'CRITICAL' | 'SUSPENDED' = 'HEALTHY'
        if (m.status === 'SUSPENDED') {
          health = 'SUSPENDED'
        } else if (issues.some(i => i.level === 'CRITICAL')) {
          health = 'CRITICAL'
        } else if (issues.length > 0) {
          health = 'DEGRADED'
        }

        const shopUrl = m.website || (m.business_name ? `https://shop.swapnopay.top/${m.business_name.toLowerCase().replace(/[^a-z0-9]/g, '')}/` : null)

        return {
          ...m,
          health,
          issues,
          issues_count: issues.length,
          shop_url: shopUrl,
          device_status: devStatus,
          device_last_active: dev?.last_active_at,
          has_mfs_numbers: hasNumbers
        }
      })

      setRows(enriched)
    } catch (e) {
      console.warn('[Merchants] Fetch warning:', e)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadMerchants()

    const params = new URLSearchParams(window.location.search)
    if (params.get('filter') === 'pending') {
      setStatusFilter('PENDING_KYC')
    }

    const channel = adminSupabase
      .channel('merchants_realtime')
      .on('postgres_changes', { event: '*', schema: 'public', table: 'merchants' }, () => {
        loadMerchants()
      })
      .subscribe()

    return () => {
      adminSupabase.removeChannel(channel)
    }
  }, [])

  // Filtered rows
  const filteredRows = useMemo(() => {
    let result = rows
    if (statusFilter === 'PENDING_KYC') {
      result = result.filter(r => r.kyc_status === 'PENDING' || r.kyc_status === 'PENDING_REVIEW')
    } else if (statusFilter === 'VERIFIED_KYC') {
      result = result.filter(r => r.kyc_status === 'VERIFIED')
    } else if (statusFilter === 'ATTENTION') {
      result = result.filter(r => r.health === 'CRITICAL' || r.health === 'DEGRADED')
    } else if (statusFilter === 'SUSPENDED') {
      result = result.filter(r => r.status === 'SUSPENDED')
    }

    if (!search.trim()) return result
    const q = search.toLowerCase()
    return result.filter(
      r =>
        (r.business_name || '').toLowerCase().includes(q) ||
        (r.email || '').toLowerCase().includes(q) ||
        (r.phone || '').includes(q) ||
        (r.nid_number || '').includes(q) ||
        (r.shop_url || '').toLowerCase().includes(q) ||
        (r.kyc_status || '').toLowerCase().includes(q) ||
        (r.health || '').toLowerCase().includes(q) ||
        (r.subscription_tier || '').toLowerCase().includes(q)
    )
  }, [rows, search, statusFilter])

  // Toggle Merchant Status
  const handleToggleStatus = async (merchantId: string, currentStatus: string) => {
    const nextStatus = currentStatus === 'SUSPENDED' ? 'ACTIVE' : 'SUSPENDED'
    if (!window.confirm(`Are you sure you want to mark this merchant as ${nextStatus}?`)) return

    setActionLoadingId(merchantId)
    try {
      await updateMerchantAccountStatus(merchantId, nextStatus)
      await loadMerchants()
      if (selectedMerchant?.id === merchantId) {
        setSelectedMerchant(prev => prev ? { ...prev, status: nextStatus as any } : null)
      }
      alert(`Merchant status changed to ${nextStatus} successfully.`)
    } catch (err: any) {
      alert('Failed to update status: ' + err.message)
    } finally {
      setActionLoadingId(null)
    }
  }

  // Permanently Delete Merchant
  const handleDeleteMerchant = async (merchantId: string, businessName?: string) => {
    if (!window.confirm(`⚠️ PERMANENT ACCOUNT DELETION\n\nAre you sure you want to delete "${businessName || merchantId}"?\n\nThis will purge all merchant data, dynamic API keys, KYC documents, and records. This action cannot be undone.`)) {
      return
    }
    const check = window.prompt(`To confirm permanent deletion, type "DELETE" below:`)
    if (check !== 'DELETE') {
      alert('Deletion cancelled: confirmation keyword did not match.')
      return
    }

    setActionLoadingId(merchantId)
    try {
      await deleteMerchantAccount(merchantId)
      await loadMerchants()
      if (selectedMerchant?.id === merchantId) {
        setSelectedMerchant(null)
      }
      alert('Merchant account and all associated data have been permanently deleted.')
    } catch (err: any) {
      alert('Failed to delete merchant: ' + err.message)
    } finally {
      setActionLoadingId(null)
    }
  }

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text)
    setCopiedId(text)
    setTimeout(() => setCopiedId(null), 2000)
  }

  const pendingCount = rows.filter(r => r.kyc_status === 'PENDING' || r.kyc_status === 'PENDING_REVIEW').length
  const attentionCount = rows.filter(r => r.health === 'CRITICAL' || r.health === 'DEGRADED').length

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
      {/* ──────────────── Page Header ──────────────── */}
      <div style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: 16
      }}>
        <div>
          <h1 style={{ fontSize: 22, fontWeight: 800, color: 'var(--text-primary)', letterSpacing: '-0.4px', margin: 0, display: 'flex', alignItems: 'center', gap: 10 }}>
            <span>Merchants & Storefronts</span>
            <span style={{ fontSize: 12, fontWeight: 700, padding: '2px 8px', borderRadius: 12, background: 'var(--bg-subtle)', color: 'var(--text-muted)' }}>
              {rows.length} Total
            </span>
          </h1>
          <div style={{ fontSize: 12.5, color: 'var(--text-muted)', marginTop: 4 }}>
            Real-time status monitoring, storefront URLs, Android SMS terminals, MFS gateway health, and issues diagnostics.
          </div>
        </div>

        <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
          <button
            onClick={() => loadMerchants()}
            className="btn btn-secondary btn-sm"
            title="Refresh merchant statuses"
          >
            <RefreshCw size={13} />
            <span>Refresh</span>
          </button>
          <button
            onClick={() => setShowAddModal(true)}
            className="btn btn-primary btn-sm"
          >
            <Plus size={14} />
            <span>Add Merchant</span>
          </button>
        </div>
      </div>

      {/* ──────────────── Filter & Search Toolbar ──────────────── */}
      <div className="card" style={{ padding: '12px 16px' }}>
        <div style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          gap: 12
        }}>
          {/* Status Tabs */}
          <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
            {[
              { id: 'ALL', label: `All Merchants (${rows.length})` },
              { id: 'ATTENTION', label: `Needs Attention (${attentionCount})`, alert: attentionCount > 0 },
              { id: 'PENDING_KYC', label: `Pending KYC (${pendingCount})`, alert: pendingCount > 0 },
              { id: 'VERIFIED_KYC', label: `Verified (${rows.filter(r => r.kyc_status === 'VERIFIED').length})` },
              { id: 'SUSPENDED', label: `Suspended (${rows.filter(r => r.status === 'SUSPENDED').length})` }
            ].map(tab => {
              const isActive = statusFilter === tab.id
              return (
                <button
                  key={tab.id}
                  onClick={() => setStatusFilter(tab.id as any)}
                  className="btn btn-sm"
                  style={{
                    background: isActive ? 'var(--brand-primary)' : 'var(--bg-subtle)',
                    color: isActive ? 'var(--brand-contrast)' : 'var(--text-secondary)',
                    border: tab.alert ? '1px solid var(--warning-border)' : '1px solid transparent',
                    fontWeight: isActive ? 700 : 500
                  }}
                >
                  {tab.label}
                </button>
              )
            })}
          </div>

          {/* Search Input */}
          <div style={{
            display: 'flex',
            alignItems: 'center',
            gap: 8,
            background: 'var(--bg-subtle)',
            border: '1px solid var(--border-default)',
            borderRadius: 'var(--radius-sm)',
            padding: '6px 12px',
            width: 320,
            maxWidth: '100%'
          }}>
            <Search size={14} color="var(--text-muted)" />
            <input
              type="text"
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Filter by name, URL, phone, issue..."
              style={{
                border: 'none',
                outline: 'none',
                background: 'transparent',
                fontSize: 12.5,
                color: 'var(--text-primary)',
                width: '100%',
                fontFamily: 'inherit'
              }}
            />
          </div>
        </div>
      </div>

      {/* ──────────────── Merchants Table ──────────────── */}
      <div className="table-container">
        {loading ? (
          <div style={{ padding: '50px 20px', textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
            <RefreshCw size={24} className="animate-spin" style={{ color: 'var(--brand-primary)', margin: '0 auto 12px' }} />
            <div>Loading live merchant registry & health audit...</div>
          </div>
        ) : filteredRows.length === 0 ? (
          <div style={{ padding: '40px 20px', textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
            No merchants match your filter criteria.
          </div>
        ) : (
          <table className="enterprise-table">
            <thead>
              <tr>
                <th>Merchant & Store</th>
                <th>Storefront & URL</th>
                <th>Running Status</th>
                <th>SMS Terminal</th>
                <th>Issues & Diagnostics</th>
                <th>KYC</th>
                <th>Tier</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filteredRows.map(r => {
                const initial = (r.business_name || 'M')[0].toUpperCase()
                const isSuspended = r.status === 'SUSPENDED'
                const isVerifiedKyc = r.kyc_status === 'VERIFIED'
                const isPendingKyc = r.kyc_status === 'PENDING' || r.kyc_status === 'PENDING_REVIEW'
                const health = r.health || (isSuspended ? 'SUSPENDED' : 'HEALTHY')
                const issues = r.issues || []

                return (
                  <tr
                    key={r.id}
                    className="clickable"
                    onClick={() => setSelectedMerchant(r)}
                    title="Click to view full slide-over diagnostics"
                  >
                    {/* Merchant & Store */}
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                        <div style={{
                          width: 34,
                          height: 34,
                          borderRadius: 'var(--radius-sm)',
                          background: 'var(--brand-subtle)',
                          color: 'var(--brand-primary)',
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          fontWeight: 800,
                          fontSize: 13,
                          flexShrink: 0
                        }}>
                          {initial}
                        </div>
                        <div>
                          <div style={{ fontWeight: 700, color: 'var(--text-primary)', fontSize: 13.5 }}>
                            {r.business_name}
                          </div>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 4, marginTop: 1 }}>
                            <span style={{ fontSize: 11, color: 'var(--text-subtle)', fontFamily: 'var(--font-mono)' }}>
                              {r.id.slice(0, 8)}...
                            </span>
                            <button
                              onClick={(e) => { e.stopPropagation(); copyToClipboard(r.id); }}
                              className="btn-icon btn-ghost"
                              style={{ width: 16, height: 16 }}
                              title="Copy Merchant UUID"
                            >
                              <Copy size={10} color={copiedId === r.id ? 'var(--success)' : 'var(--text-subtle)'} />
                            </button>
                          </div>
                        </div>
                      </div>
                    </td>

                    {/* Storefront & URL */}
                    <td>
                      {r.shop_url ? (
                        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                          <a
                            href={r.shop_url}
                            target="_blank"
                            rel="noreferrer"
                            onClick={(e) => e.stopPropagation()}
                            style={{
                              fontSize: 11.5,
                              color: 'var(--brand-primary)',
                              textDecoration: 'none',
                              maxWidth: 180,
                              overflow: 'hidden',
                              textOverflow: 'ellipsis',
                              whiteSpace: 'nowrap',
                              fontWeight: 600
                            }}
                            title={r.shop_url}
                          >
                            {r.shop_url.replace(/^https?:\/\//, '')}
                          </a>
                          <a
                            href={r.shop_url}
                            target="_blank"
                            rel="noreferrer"
                            onClick={(e) => e.stopPropagation()}
                            className="btn-icon btn-ghost"
                            style={{ width: 18, height: 18 }}
                            title="Open Storefront in New Tab"
                          >
                            <ExternalLink size={11} color="var(--brand-primary)" />
                          </a>
                        </div>
                      ) : (
                        <span style={{ fontSize: 11, color: 'var(--text-muted)' }}>No storefront</span>
                      )}
                      <div style={{ fontSize: 10.5, color: 'var(--text-muted)', marginTop: 2 }}>
                        {r.phone || r.email || 'No contact'}
                      </div>
                    </td>

                    {/* Running Status Badge */}
                    <td>
                      <span className={`status-pill ${
                        health === 'HEALTHY' ? 'success' :
                        health === 'DEGRADED' ? 'warning' :
                        health === 'CRITICAL' ? 'danger' : 'neutral'
                      }`} style={{ fontSize: 11, fontWeight: 700 }}>
                        <span className="status-dot" />
                        {health === 'HEALTHY' ? 'Live & Optimal' :
                         health === 'DEGRADED' ? 'Degraded' :
                         health === 'CRITICAL' ? 'Action Needed' : 'Suspended'}
                      </span>
                    </td>

                    {/* SMS Terminal Device */}
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                        <Smartphone size={13} color={
                          r.device_status === 'ONLINE' ? 'var(--success)' :
                          r.device_status === 'STALE' ? 'var(--warning)' : 'var(--danger)'
                        } />
                        <span style={{
                          fontSize: 11,
                          fontWeight: 700,
                          color: r.device_status === 'ONLINE' ? 'var(--success)' :
                                 r.device_status === 'STALE' ? '#D97706' : 'var(--text-muted)'
                        }}>
                          {r.device_status === 'ONLINE' ? 'Online' :
                           r.device_status === 'STALE' ? 'Idle' :
                           r.device_status === 'OFFLINE' ? 'Offline' : 'Not Linked'}
                        </span>
                      </div>
                    </td>

                    {/* Issues & Diagnostics */}
                    <td>
                      {issues.length === 0 ? (
                        <span style={{
                          display: 'inline-flex',
                          alignItems: 'center',
                          gap: 4,
                          fontSize: 11,
                          color: 'var(--success)',
                          fontWeight: 600
                        }}>
                          <CheckCircle2 size={12} />
                          <span>All clear</span>
                        </span>
                      ) : (
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                          {issues.slice(0, 2).map((iss, i) => (
                            <span
                              key={i}
                              style={{
                                fontSize: 10,
                                fontWeight: 700,
                                padding: '2px 6px',
                                borderRadius: 4,
                                background: iss.level === 'CRITICAL' ? 'rgba(239,68,68,0.12)' : 'rgba(245,158,11,0.12)',
                                color: iss.level === 'CRITICAL' ? '#EF4444' : '#D97706',
                                border: `1px solid ${iss.level === 'CRITICAL' ? 'rgba(239,68,68,0.25)' : 'rgba(245,158,11,0.25)'}`
                              }}
                              title={iss.message}
                            >
                              {iss.code.replace(/_/g, ' ')}
                            </span>
                          ))}
                          {issues.length > 2 && (
                            <span style={{ fontSize: 10, color: 'var(--text-muted)', alignSelf: 'center' }}>
                              +{issues.length - 2} more
                            </span>
                          )}
                        </div>
                      )}
                    </td>

                    {/* KYC Verification */}
                    <td>
                      {isVerifiedKyc ? (
                        <span className="status-pill success" style={{ fontSize: 10.5 }}>
                          <span className="status-dot" />
                          Verified
                        </span>
                      ) : isPendingKyc ? (
                        <span
                          className="status-pill warning"
                          onClick={(e) => {
                            e.stopPropagation()
                            setInspectKycMerchant(r)
                          }}
                          style={{ cursor: 'pointer', fontSize: 10.5 }}
                          title="Click to inspect NID documents"
                        >
                          <span className="status-dot" />
                          Pending Review
                        </span>
                      ) : (
                        <span className="status-pill neutral" style={{ fontSize: 10.5 }}>
                          <span className="status-dot" />
                          Unverified
                        </span>
                      )}
                    </td>

                    {/* Tier */}
                    <td>
                      <span style={{
                        fontSize: 10.5,
                        fontWeight: 700,
                        fontFamily: 'var(--font-mono)',
                        padding: '2px 6px',
                        borderRadius: 'var(--radius-xs)',
                        background: r.subscription_tier === 'ENTERPRISE' ? 'var(--brand-subtle)' : 'var(--bg-subtle)',
                        color: r.subscription_tier === 'ENTERPRISE' ? 'var(--brand-primary)' : 'var(--text-secondary)'
                      }}>
                        {r.subscription_tier || 'STARTER'}
                      </span>
                    </td>

                    {/* Actions */}
                    <td style={{ textAlign: 'right' }}>
                      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 6 }}>
                        <button
                          onClick={(e) => {
                            e.stopPropagation()
                            navigate(`/merchants/${r.id}`)
                          }}
                          className="btn btn-primary btn-sm"
                          style={{ fontSize: 11, padding: '4px 8px' }}
                          title="Open full merchant diagnostics and controls"
                        >
                          <Zap size={11} />
                          <span>Diagnostics</span>
                        </button>
                        <button
                          onClick={(e) => {
                            e.stopPropagation()
                            handleToggleStatus(r.id, r.status)
                          }}
                          disabled={actionLoadingId === r.id}
                          className={`btn btn-sm ${isSuspended ? 'btn-secondary' : 'btn-danger'}`}
                          style={{ fontSize: 11, padding: '4px 8px' }}
                          title={isSuspended ? 'Reactivate merchant account' : 'Suspend merchant account'}
                        >
                          {actionLoadingId === r.id ? '...' : (isSuspended ? 'Activate' : 'Suspend')}
                        </button>
                        <button
                          onClick={(e) => {
                            e.stopPropagation()
                            handleDeleteMerchant(r.id, r.business_name)
                          }}
                          disabled={actionLoadingId === r.id}
                          className="btn btn-sm btn-ghost"
                          style={{ color: 'var(--danger)', padding: '4px 6px' }}
                          title="Permanently delete merchant"
                        >
                          <Trash2 size={12} />
                        </button>
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        )}
      </div>

      {/* ──────────────── Detail Drawer (Slide-Over Sheet) ──────────────── */}
      <DetailDrawer
        isOpen={Boolean(selectedMerchant)}
        onClose={() => setSelectedMerchant(null)}
        title={selectedMerchant?.business_name || 'Merchant Profile'}
        subtitle={`ID: ${selectedMerchant?.id}`}
        badge={
          <span className={`status-pill ${selectedMerchant?.status === 'ACTIVE' ? 'success' : 'danger'}`}>
            <span className="status-dot" />
            {selectedMerchant?.status || 'ACTIVE'}
          </span>
        }
        footer={
          <>
            <button
              onClick={() => setSelectedMerchant(null)}
              className="btn btn-secondary btn-sm"
            >
              Close
            </button>
            {selectedMerchant && (
              <>
                <button
                  onClick={() => handleToggleStatus(selectedMerchant.id, selectedMerchant.status)}
                  disabled={actionLoadingId === selectedMerchant.id}
                  className={`btn btn-sm ${selectedMerchant.status === 'SUSPENDED' ? 'btn-secondary' : 'btn-danger'}`}
                >
                  {actionLoadingId === selectedMerchant.id ? 'Updating...' : (selectedMerchant.status === 'SUSPENDED' ? 'Activate' : 'Suspend')}
                </button>
                <button
                  onClick={() => navigate(`/merchants/${selectedMerchant.id}`)}
                  className="btn btn-primary btn-sm"
                >
                  <Zap size={13} />
                  <span>Full Diagnostics & Controls</span>
                </button>
              </>
            )}
          </>
        }
      >
        {selectedMerchant && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            {/* Quick Diagnostic Card */}
            <div style={{
              background: 'var(--bg-surface)',
              border: '1px solid var(--border-default)',
              borderRadius: 8,
              padding: 14,
              display: 'flex',
              flexDirection: 'column',
              gap: 10
            }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--text-primary)', display: 'flex', alignItems: 'center', gap: 6 }}>
                  <Activity size={15} color="var(--brand-primary)" />
                  Operational Health Status
                </span>
                <span className={`status-pill ${
                  selectedMerchant.health === 'HEALTHY' ? 'success' :
                  selectedMerchant.health === 'DEGRADED' ? 'warning' : 'danger'
                }`} style={{ fontSize: 11 }}>
                  <span className="status-dot" />
                  {selectedMerchant.health || 'ACTIVE'}
                </span>
              </div>
              <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>
                SMS Terminal: <strong style={{ color: 'var(--text-primary)' }}>{selectedMerchant.device_status || 'NOT PAIRED'}</strong>
              </div>
              <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>
                MFS Receiving Numbers: <strong style={{ color: selectedMerchant.has_mfs_numbers ? 'var(--success)' : 'var(--danger)' }}>
                  {selectedMerchant.has_mfs_numbers ? 'Configured ✅' : 'Missing ⚠️'}
                </strong>
              </div>
            </div>

            {/* Storefront & Checkout URLs */}
            <div style={{
              background: 'var(--bg-surface)',
              border: '1px solid var(--border-default)',
              borderRadius: 8,
              padding: 14,
              display: 'flex',
              flexDirection: 'column',
              gap: 10
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                <Link2 size={15} color="var(--brand-primary)" />
                <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--text-primary)' }}>Storefront & Gateway URLs</span>
              </div>

              <div>
                <div style={{ fontSize: 11, fontWeight: 700, color: 'var(--text-muted)', marginBottom: 2 }}>Storefront Website:</div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                  <input
                    className="input"
                    readOnly
                    value={selectedMerchant.shop_url || selectedMerchant.website || 'Not provisioned'}
                    style={{ fontSize: 11, fontFamily: 'var(--font-mono)' }}
                  />
                  {selectedMerchant.shop_url && (
                    <a href={selectedMerchant.shop_url} target="_blank" rel="noreferrer" className="btn btn-secondary btn-sm" title="Open Storefront">
                      <ExternalLink size={12} />
                    </a>
                  )}
                </div>
              </div>

              <div>
                <div style={{ fontSize: 11, fontWeight: 700, color: 'var(--text-muted)', marginBottom: 2 }}>Checkout Widget:</div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                  <input
                    className="input"
                    readOnly
                    value={`https://pay.swapnopay.top/widget.html?merchant_id=${selectedMerchant.id}`}
                    style={{ fontSize: 11, fontFamily: 'var(--font-mono)' }}
                  />
                  <a href={`https://pay.swapnopay.top/widget.html?merchant_id=${selectedMerchant.id}`} target="_blank" rel="noreferrer" className="btn btn-primary btn-sm" title="Test Gateway Checkout">
                    <ExternalLink size={12} />
                  </a>
                </div>
              </div>
            </div>

            {/* Quick Overview Card */}
            <div style={{
              background: 'var(--bg-surface)',
              border: '1px solid var(--border-default)',
              borderRadius: 8,
              padding: 14,
              display: 'flex',
              flexDirection: 'column',
              gap: 10
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                <Store size={15} color="var(--brand-primary)" />
                <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--text-primary)' }}>Contact & Subscription</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12 }}>
                <span style={{ color: 'var(--text-muted)' }}>Email:</span>
                <span style={{ color: 'var(--text-primary)' }}>{selectedMerchant.email || 'None'}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12 }}>
                <span style={{ color: 'var(--text-muted)' }}>Phone:</span>
                <span style={{ color: 'var(--text-primary)' }}>{selectedMerchant.phone || 'None'}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12 }}>
                <span style={{ color: 'var(--text-muted)' }}>Subscription:</span>
                <span style={{ fontWeight: 700, fontFamily: 'var(--font-mono)', color: 'var(--brand-primary)' }}>
                  {selectedMerchant.subscription_tier || 'STARTER'}
                </span>
              </div>
            </div>
          </div>
        )}
      </DetailDrawer>

      {/* KYC Inspection Modal */}
      {inspectKycMerchant && (
        <KycInspectionModal
          isOpen={Boolean(inspectKycMerchant)}
          merchant={inspectKycMerchant}
          onClose={() => setInspectKycMerchant(null)}
          onActionComplete={() => {
            loadMerchants()
            setInspectKycMerchant(null)
          }}
        />
      )}

      {/* Add Merchant Modal */}
      <AddMerchantModal
        isOpen={showAddModal}
        onClose={() => setShowAddModal(false)}
        onMerchantCreated={() => {
          setShowAddModal(false)
          loadMerchants()
        }}
      />
    </div>
  )
}
