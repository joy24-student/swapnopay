<?php
require_once __DIR__ . '/inc/guard.php';
require_once __DIR__ . '/header.php';
?>

<div class="content-wrapper" style="margin-left: 0; background: #f8fafc; padding: 24px;">
    <section class="content-header" style="padding: 0 0 20px 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
            <div>
                <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0; letter-spacing: -0.5px;">
                    <i class="fa fa-bell-o" style="color: #0284c7; margin-right: 8px;"></i> Store Notifications Center
                </h1>
                <p style="font-size: 13px; color: #64748b; margin: 0;">
                    Monitor real-time incoming orders, stock thresholds, customer registrations, and system alerts
                </p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-default" onclick="window.snAdminNotifications && window.snAdminNotifications.toggleSound()" style="font-weight: 600; border-radius: 8px; font-size: 13px;">
                    <i class="fa fa-volume-up"></i> Sound Settings
                </button>
                <button type="button" class="btn btn-default" onclick="window.snAdminNotifications && window.snAdminNotifications.markAllRead()" style="font-weight: 600; border-radius: 8px; font-size: 13px;">
                    <i class="fa fa-check"></i> Mark All as Read
                </button>
                <button type="button" class="btn btn-primary" onclick="window.snAdminNotifications && window.snAdminNotifications.openAnnounceModal()" style="font-weight: 700; border-radius: 8px; background: #0284c7; border-color: #0284c7; font-size: 13px;">
                    <i class="fa fa-plus"></i> Post Announcement
                </button>
            </div>
        </div>
    </section>

    <!-- KPI Summary Row -->
    <div class="row" style="margin-bottom: 24px;">
        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 12px;">
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">UNREAD ALERTS</div>
                <div style="font-size: 24px; font-weight: 800; color: #ef4444;" id="pageKpiUnread">0</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 12px;">
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">ORDER NOTIFICATIONS</div>
                <div style="font-size: 24px; font-weight: 800; color: #0f172a;" id="pageKpiOrders">0</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 12px;">
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">STOCK & INVENTORY ALERTS</div>
                <div style="font-size: 24px; font-weight: 800; color: #d97706;" id="pageKpiStock">0</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 col-xs-12" style="margin-bottom: 12px;">
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 4px;">CUSTOMER SIGNUPS</div>
                <div style="font-size: 24px; font-weight: 800; color: #2563eb;" id="pageKpiCustomers">0</div>
            </div>
        </div>
    </div>

    <!-- Main Card Container -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
        
        <!-- Controls Bar -->
        <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: #fafbfc;">
            <!-- Category Pills -->
            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                <button type="button" class="btn btn-sm btn-dark page-tab active" data-cat="all" onclick="filterPageCategory('all', this)" style="border-radius: 999px; font-weight: 700; padding: 6px 14px; background: #0f172a; color: #fff; border: none;">
                    All Notifications
                </button>
                <button type="button" class="btn btn-sm btn-default page-tab" data-cat="orders" onclick="filterPageCategory('orders', this)" style="border-radius: 999px; font-weight: 600; padding: 6px 14px;">
                    Orders
                </button>
                <button type="button" class="btn btn-sm btn-default page-tab" data-cat="inventory" onclick="filterPageCategory('inventory', this)" style="border-radius: 999px; font-weight: 600; padding: 6px 14px;">
                    Stock Warnings
                </button>
                <button type="button" class="btn btn-sm btn-default page-tab" data-cat="customers" onclick="filterPageCategory('customers', this)" style="border-radius: 999px; font-weight: 600; padding: 6px 14px;">
                    Customers
                </button>
                <button type="button" class="btn btn-sm btn-default page-tab" data-cat="system" onclick="filterPageCategory('system', this)" style="border-radius: 999px; font-weight: 600; padding: 6px 14px;">
                    System Notices
                </button>
            </div>

            <!-- Search Field -->
            <div style="width: 280px; position: relative;">
                <input type="text" id="pageSearchInput" class="form-control" placeholder="Search notifications..." style="border-radius: 8px; font-size: 13px;" oninput="filterPageSearch(this.value)">
            </div>
        </div>

        <!-- Full List Area -->
        <div id="pageNotificationList" style="min-height: 250px;">
            <div style="padding: 50px 20px; text-align: center; color: #64748b;">
                <i class="fa fa-spinner fa-spin fa-2x" style="color: #0284c7; margin-bottom: 12px;"></i>
                <p style="margin: 0; font-size: 14px;">Loading store notifications...</p>
            </div>
        </div>

    </div>
</div>

<script>
let pageCategory = 'all';
let pageSearch = '';
let pageNotifs = [];

async function loadPageNotifications() {
    try {
        const res = await fetch('admin_notifications_api.php?action=get_notifications');
        const data = await res.json();
        if (data.status === 'success') {
            pageNotifs = data.notifications || [];
            
            // Update KPI cards
            const unread = parseInt(data.unread_count, 10) || 0;
            document.getElementById('pageKpiUnread').textContent = unread;
            document.getElementById('pageKpiOrders').textContent = data.counts.orders || 0;
            document.getElementById('pageKpiStock').textContent = data.counts.inventory || 0;
            document.getElementById('pageKpiCustomers').textContent = data.counts.customers || 0;

            renderPageList();
        }
    } catch (e) {
        document.getElementById('pageNotificationList').innerHTML = '<div style="padding:40px; text-align:center; color:#ef4444;">Error loading notifications.</div>';
    }
}

function filterPageCategory(cat, btn) {
    pageCategory = cat;
    document.querySelectorAll('.page-tab').forEach(b => {
        b.style.background = '#ffffff';
        b.style.color = '#334155';
        b.style.border = '1px solid #cbd5e1';
    });
    btn.style.background = '#0f172a';
    btn.style.color = '#ffffff';
    btn.style.border = '1px solid #0f172a';
    renderPageList();
}

function filterPageSearch(val) {
    pageSearch = (val || '').trim().toLowerCase();
    renderPageList();
}

function renderPageList() {
    const listEl = document.getElementById('pageNotificationList');
    if (!listEl) return;

    let items = pageNotifs.filter(item => {
        if (pageCategory !== 'all' && item.category !== pageCategory) return false;
        if (pageSearch) {
            const h = `${item.title} ${item.message} ${item.category}`.toLowerCase();
            if (!h.includes(pageSearch)) return false;
        }
        return true;
    });

    if (items.length === 0) {
        listEl.innerHTML = '<div style="padding: 60px 20px; text-align: center; color: #64748b;"><i class="fa fa-bell-slash-o fa-3x" style="color: #cbd5e1; margin-bottom: 12px;"></i><h4>No notifications found</h4><p style="font-size: 13px;">No notifications match your current filter.</p></div>';
        return;
    }

    let html = '<div class="table-responsive"><table class="table" style="margin-bottom: 0;">';
    html += '<thead><tr style="background:#f8fafc; font-size:12px; color:#475569; text-transform:uppercase; letter-spacing:0.5px;"><th style="width:50px;"></th><th>Notification</th><th style="width:140px;">Category</th><th style="width:130px;">Time</th><th style="width:140px; text-align:right;">Actions</th></tr></thead>';
    html += '<tbody>';

    items.forEach(it => {
        const isUnread = !it.is_read;
        const bg = isUnread ? '#f0f7ff' : '#ffffff';
        const sevColor = it.severity === 'danger' ? '#ef4444' : (it.severity === 'warning' ? '#f59e0b' : (it.severity === 'success' ? '#10b981' : '#0284c7'));
        
        html += `<tr style="background:${bg}; border-bottom: 1px solid #f1f5f9;">
            <td style="vertical-align:middle; text-align:center;">
                <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:${sevColor};"></span>
            </td>
            <td style="vertical-align:middle;">
                <div style="font-weight:700; font-size:13.5px; color:#0f172a; margin-bottom:2px;">
                    ${isUnread ? '<span style="color:#2563eb; font-weight:800; margin-right:4px;">●</span>' : ''}
                    ${it.title}
                </div>
                <div style="font-size:12.5px; color:#475569;">${it.message}</div>
            </td>
            <td style="vertical-align:middle;">
                <span class="label" style="background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; font-size:11px; padding:4px 8px; border-radius:4px; text-transform:capitalize;">${it.category}</span>
            </td>
            <td style="vertical-align:middle; font-size:12px; color:#64748b;">
                ${it.time_human}
            </td>
            <td style="vertical-align:middle; text-align:right;">
                ${it.action_url && it.action_url !== 'javascript:void(0)' ? `
                    <a href="${it.action_url}" class="btn btn-xs btn-primary" style="font-weight:600; border-radius:6px; padding:4px 10px;">
                        ${it.action_label || 'View'}
                    </a>
                ` : ''}
                ${isUnread ? `
                    <button type="button" class="btn btn-xs btn-default" onclick="window.snAdminNotifications && window.snAdminNotifications.markRead('${it.key}'); this.closest('tr').style.background='#fff'; this.remove();" style="border-radius:6px; padding:4px 8px;" title="Mark Read">
                        <i class="fa fa-check"></i>
                    </button>
                ` : ''}
            </td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    listEl.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', loadPageNotifications);
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
