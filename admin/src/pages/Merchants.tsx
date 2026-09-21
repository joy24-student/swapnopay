import React, { useEffect, useState, useMemo } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { adminSupabase } from '../adminSupabaseClient'
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
  Clock,
  Ban,
  Database,
  Mail,
  Phone,
  Store,
  Calendar
} from 'lucide-react'

interface MerchantRecord {
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
}

export default function Merchants() {
  const [rows, setRows] = useState<MerchantRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState<'ALL' | 'PENDING_KYC' | 'VERIFIED_KYC' | 'SUSPENDED'>('ALL')
  const [showAddModal, setShowAddModal] = useState(false)
  const [selectedMerchant, setSelectedMerchant] = useState<MerchantRecord | null>(null)
  const [inspectKycMerchant, setInspectKycMerchant] = useState<MerchantRecord | null>(null)
  const [copiedId, setCopiedId] = useState<string | null>(null)
  const navigate = useNavigate()

  const loadMerchants = async () => {
    try {
      let loadedData: MerchantRecord[] | null = null

      try {
        const { data, error } = await adminSupabase
          .from('merchants')
          .select('*')
          .order('created_at', { ascending: false })
        if (!error && data && data.length > 0) {
          loadedData = data as MerchantRecord[]
        }
      } catch (e) {
        console.warn('[Merchants] Supabase fetch notice:', e)
      }

      // Backend fallback if direct Supabase query fails or returns empty
      if (!loadedData || loadedData.length === 0) {
        try {
          const masterSecret = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem('swapnopay_admin_secret') : null
          const base = (import.meta as any).env?.VITE_BACKEND_URL || 'https://api.swapnopay.top'
          const headers: Record<string, string> = { 'Accept': 'application/json' }
          if (masterSecret) headers['X-Admin-Secret'] = masterSecret
          const { data: { session } } = await adminSupabase.auth.getSession()
          if (session?.access_token) headers['Authorization'] = `Bearer ${session.access_token}`

          const res = await fetch(`${base.replace(/\/$/, '')}/v1/admin/merchants`, { headers })
          if (res.ok) {
            const json = await res.json()
            if (json.ok && Array.isArray(json.merchants)) {
              loadedData = json.merchants as MerchantRecord[]
            }
          }
        } catch (bkErr) {
          console.warn('[Merchants] Backend fallback notice:', bkErr)
        }
      }

      if (loadedData) {
        setRows(loadedData)
      }
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
        (r.kyc_status || '').toLowerCase().includes(q) ||
        (r.subscription_tier || '').toLowerCase().includes(q)
    )
  }, [rows, search, statusFilter])

  // Toggle Merchant Status
  const handleToggleStatus = async (merchantId: string, currentStatus: string) => {
    const nextStatus = currentStatus === 'SUSPENDED' ? 'ACTIVE' : 'SUSPENDED'
    if (!window.confirm(`Are you sure you want to mark this merchant as ${nextStatus}?`)) return

    try {
      const { error } = await adminSupabase
        .from('merchants')
        .update({ status: nextStatus, updated_at: new Date().toISOString() })
        .eq('id', merchantId)

      if (error) throw new Error(error.message)
      await loadMerchants()
      if (selectedMerchant?.id === merchantId) {
        setSelectedMerchant(prev => prev ? { ...prev, status: nextStatus } : null)
      }
    } catch (err: any) {
      alert('Failed to update status: ' + err.message)
    }
  }

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text)
    setCopiedId(text)
    setTimeout(() => setCopiedId(null), 2000)
  }

  const pendingCount = rows.filter(r => r.kyc_status === 'PENDING' || r.kyc_status === 'PENDING_REVIEW').length

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 24 }}>
      {/* ──────────────── Page Header ──────────────── */}
      <div style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: 16
      }}>
        <div>
          <h1 style={{ fontSize: 22, fontWeight: 800, color: 'var(--text-primary)', letterSpacing: '-0.4px', margin: 0 }}>
            Merchants & Storefronts
          </h1>
          <div style={{ fontSize: 12.5, color: 'var(--text-muted)', marginTop: 4 }}>
            Manage registered retail stores, subscription tiers, Supabase database bindings, and KYC compliance.
          </div>
        </div>

        <button
          onClick={() => setShowAddModal(true)}
          className="btn btn-primary btn-sm"
        >
          <Plus size={14} />
          <span>Add Merchant</span>
        </button>
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
              placeholder="Filter by name, phone, NID, email..."
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

      {/* ──────────────── Merchants Table (Linear/Vercel Style) ──────────────── */}
      <div className="table-container">
        {loading ? (
          <div style={{ padding: '40px 20px', textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
            Loading merchants registry from database...
          </div>
        ) : filteredRows.length === 0 ? (
          <div style={{ padding: '40px 20px', textAlign: 'center', color: 'var(--text-muted)', fontSize: 13 }}>
            No merchants match your filter criteria.
          </div>
        ) : (
          <table className="enterprise-table">
            <thead>
              <tr>
                <th>Merchant / Store</th>
                <th>Contact</th>
                <th>Category</th>
                <th>Status</th>
                <th>KYC Verification</th>
                <th>Tier</th>
                <th>Registered</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filteredRows.map(r => {
                const initial = (r.business_name || 'M')[0].toUpperCase()
                const isSuspended = r.status === 'SUSPENDED'
                const isVerifiedKyc = r.kyc_status === 'VERIFIED'
                const isPendingKyc = r.kyc_status === 'PENDING' || r.kyc_status === 'PENDING_REVIEW'

                return (
                  <tr
                    key={r.id}
                    className="clickable"
                    onClick={() => setSelectedMerchant(r)}
                    title="Click to view detailed slide-over sheet"
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

                    {/* Contact */}
                    <td>
                      <div style={{ fontSize: 12.5, color: 'var(--text-primary)' }}>{r.email || '—'}</div>
                      <div style={{ fontSize: 11.5, color: 'var(--text-muted)' }}>{r.phone || '—'}</div>
                    </td>

                    {/* Category / Type */}
                    <td>
                      <span style={{
                        padding: '2px 7px',
                        borderRadius: 'var(--radius-xs)',
                        fontSize: 11,
                        fontWeight: 600,
                        background: 'var(--bg-subtle)',
                        color: 'var(--text-secondary)'
                      }}>
                        {r.business_type || 'Retail Store'}
                      </span>
                    </td>

                    {/* Status */}
                    <td>
                      <span className={`status-pill ${isSuspended ? 'danger' : 'success'}`}>
                        <span className="status-dot" />
                        {isSuspended ? 'Suspended' : 'Active'}
                      </span>
                    </td>

                    {/* KYC Verification */}
                    <td>
                      {isVerifiedKyc ? (
                        <span className="status-pill success">
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
                          style={{ cursor: 'pointer' }}
                          title="Click to inspect NID documents"
                        >
                          <span className="status-dot" />
                          Pending Review
                        </span>
                      ) : (
                        <span className="status-pill neutral">
                          <span className="status-dot" />
                          Unverified
                        </span>
                      )}
                    </td>

                    {/* Tier */}
                    <td>
                      <span style={{
                        fontSize: 11,
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

                    {/* Registered Date */}
                    <td style={{ fontSize: 12, color: 'var(--text-muted)' }}>
                      {r.created_at ? new Date(r.created_at).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) : 'Recent'}
                    </td>

                    {/* Actions */}
                    <td style={{ textAlign: 'right' }}>
                      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 6 }}>
                        <button
                          onClick={(e) => {
                            e.stopPropagation()
                            navigate(`/merchants/${r.id}`)
                          }}
                          className="btn btn-secondary btn-sm"
                          title="Open full merchant detail page"
                        >
                          <ExternalLink size={12} />
                          <span>View</span>
                        </button>
                        <button
                          onClick={(e) => {
                            e.stopPropagation()
                            handleToggleStatus(r.id, r.status)
                          }}
                          className={`btn btn-sm ${isSuspended ? 'btn-secondary' : 'btn-danger'}`}
                          title={isSuspended ? 'Reactivate merchant account' : 'Suspend merchant account'}
                        >
                          {isSuspended ? 'Activate' : 'Suspend'}
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
        subtitle={`Registered ${selectedMerchant?.created_at ? new Date(selectedMerchant.created_at).toLocaleDateString() : 'Recently'}`}
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
              <button
                onClick={() => navigate(`/merchants/${selectedMerchant.id}`)}
                className="btn btn-primary btn-sm"
              >
                <span>Full Profile Page</span>
                <ExternalLink size={13} />
              </button>
            )}
          </>
        }
      >
        {selectedMerchant && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
            {/* Quick Overview Card */}
            <div style={{
              background: 'var(--bg-surface)',
              border: '1px solid var(--border-default)',
              borderRadius: 'var(--radius-md)',
              padding: 16,
              display: 'flex',
              flexDirection: 'column',
              gap: 12
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <Store size={18} color="var(--brand-primary)" />
                <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--text-primary)' }}>Business Information</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5 }}>
                <span style={{ color: 'var(--text-muted)' }}>Business Type:</span>
                <span style={{ fontWeight: 600, color: 'var(--text-primary)' }}>{selectedMerchant.business_type || 'Retail'}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5 }}>
                <span style={{ color: 'var(--text-muted)' }}>Email:</span>
                <span style={{ color: 'var(--text-primary)' }}>{selectedMerchant.email || 'None'}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5 }}>
                <span style={{ color: 'var(--text-muted)' }}>Phone:</span>
                <span style={{ color: 'var(--text-primary)' }}>{selectedMerchant.phone || 'None'}</span>
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5 }}>
                <span style={{ color: 'var(--text-muted)' }}>Subscription Tier:</span>
                <span style={{ fontWeight: 700, fontFamily: 'var(--font-mono)', color: 'var(--brand-primary)' }}>
                  {selectedMerchant.subscription_tier || 'STARTER'}
                </span>
              </div>
            </div>

            {/* KYC Status Card */}
            <div style={{
              background: 'var(--bg-surface)',
              border: '1px solid var(--border-default)',
              borderRadius: 'var(--radius-md)',
              padding: 16,
              display: 'flex',
              flexDirection: 'column',
              gap: 10
            }}>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <ShieldCheck size={18} color="var(--brand-primary)" />
                  <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--text-primary)' }}>KYC Compliance</span>
                </div>
                <span className={`status-pill ${selectedMerchant.kyc_status === 'VERIFIED' ? 'success' : selectedMerchant.kyc_status === 'PENDING' ? 'warning' : 'neutral'}`}>
                  <span className="status-dot" />
                  {selectedMerchant.kyc_status || 'UNVERIFIED'}
                </span>
              </div>
              <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>
                NID Number: <strong style={{ color: 'var(--text-primary)' }}>{selectedMerchant.nid_number || 'Not Submitted'}</strong>
              </div>
              {(selectedMerchant.kyc_status === 'PENDING' || selectedMerchant.kyc_status === 'PENDING_REVIEW') && (
                <button
                  onClick={() => {
                    setInspectKycMerchant(selectedMerchant)
                  }}
                  className="btn btn-primary btn-sm"
                  style={{ width: '100%', marginTop: 6 }}
                >
                  <ShieldCheck size={14} />
                  <span>Inspect Submitted NID Documents</span>
                </button>
              )}
            </div>

            {/* Supabase Database Connection */}
            <div style={{
              background: 'var(--bg-surface)',
              border: '1px solid var(--border-default)',
              borderRadius: 'var(--radius-md)',
              padding: 16,
              display: 'flex',
              flexDirection: 'column',
              gap: 8
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <Database size={18} color="var(--brand-primary)" />
                <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--text-primary)' }}>Connected Database</span>
              </div>
              <div style={{ fontSize: 12, color: 'var(--text-muted)' }}>
                {selectedMerchant.supabase_url ? (
                  <span style={{ fontFamily: 'var(--font-mono)', color: 'var(--success-text)' }}>
                    {selectedMerchant.supabase_url}
                  </span>
                ) : (
                  <span>Using Default Central Platform Database</span>
                )}
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
