<?php 
require_once __DIR__ . '/header.php';

$page = max(1, min(100000, (int)($_GET['page'] ?? 1)));
$filter = (string)($_GET['status'] ?? '');
$search = trim((string)($_GET['search'] ?? ''));
$statuses = ['Pending', 'Completed', 'Cancelled'];

if (!in_array($filter, $statuses, true)) $filter = '';

$whereClauses = [];
$params = [];

if ($filter !== '') {
    $whereClauses[] = 'payment_status = ?';
    $params[] = $filter;
}

if ($search !== '') {
    $whereClauses[] = '(payment_id ILIKE ? OR customer_name ILIKE ? OR customer_email ILIKE ? OR shipping_phone ILIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';

// Count total
$countStmt = $pdo->prepare('SELECT count(*) FROM tbl_payment' . $whereSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// Fetch orders with pagination
$limit = 25;
$offset = ($page - 1) * $limit;
$query = $pdo->prepare('SELECT * FROM tbl_payment' . $whereSql . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
$query->execute($params);
$orders = $query->fetchAll(PDO::FETCH_ASSOC);

// High-level aggregate metrics for top KPI bar
$kpiTotal = (int)$pdo->query("SELECT count(*) FROM tbl_payment")->fetchColumn();
$kpiPaid = (int)$pdo->query("SELECT count(*) FROM tbl_payment WHERE payment_status = 'Completed'")->fetchColumn();
$kpiPendingDelivery = (int)$pdo->query("SELECT count(*) FROM tbl_payment WHERE shipping_status NOT IN ('Delivered', 'Cancelled')")->fetchColumn();
$kpiRevenue = (float)$pdo->query("SELECT coalesce(sum(paid_amount), 0) FROM tbl_payment WHERE payment_status = 'Completed'")->fetchColumn();

function orderText($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<style>
.order-kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.order-kpi-title {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.order-kpi-val {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    margin: 4px 0 2px;
}
.order-table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.order-badge-status {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.order-action-btn {
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 8px;
}
</style>

<section class="content-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h1 style="margin: 0; font-weight: 800; font-size: 22px; color: #0f172a;">
                Website Orders & Invoices
            </h1>
            <p style="margin: 3px 0 0; color: #64748b; font-size: 13px;">Manage customer transactions, generate official receipts, and track fulfillment</p>
        </div>
        <div>
            <span class="badge" style="background: #0f172a; font-size: 12px; padding: 6px 12px; border-radius: 20px;">
                Total Orders: <?= number_format($kpiTotal) ?>
            </span>
        </div>
    </div>
</section>

<section class="content">
    <!-- Top KPI Cards -->
    <div class="row">
        <div class="col-lg-3 col-sm-6">
            <div class="order-kpi-card">
                <div class="order-kpi-title">Total Orders</div>
                <div class="order-kpi-val"><?= number_format($kpiTotal) ?></div>
                <div style="font-size: 12px; color: #64748b;">Lifetime web shop orders</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="order-kpi-card">
                <div class="order-kpi-title">Completed Payments</div>
                <div class="order-kpi-val" style="color: #059669;"><?= number_format($kpiPaid) ?></div>
                <div style="font-size: 12px; color: #059669; font-weight: 600;"><i class="fa fa-check-circle"></i> Revenue Settled</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="order-kpi-card">
                <div class="order-kpi-title">Pending Fulfillment</div>
                <div class="order-kpi-val" style="color: #d97706;"><?= number_format($kpiPendingDelivery) ?></div>
                <div style="font-size: 12px; color: #d97706; font-weight: 600;"><i class="fa fa-truck"></i> Ready for Courier Dispatch</div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="order-kpi-card">
                <div class="order-kpi-title">Settled Revenue</div>
                <div class="order-kpi-val" style="color: #2563eb;">BDT <?= number_format($kpiRevenue, 2) ?></div>
                <div style="font-size: 12px; color: #64748b;">Paid Order Volume</div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php foreach (['order_notice' => 'success', 'order_error' => 'danger'] as $key => $kind): ?>
        <?php if (!empty($_SESSION[$key])): ?>
            <div role="status" class="alert alert-<?= $kind ?>" style="border-radius: 8px;">
                <i class="fa fa-<?= $kind === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= orderText($_SESSION[$key]) ?>
            </div>
            <?php unset($_SESSION[$key]); ?>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Main Orders Table Card -->
    <div class="order-table-card">
        <!-- Filter and Search Bar -->
        <form method="get" class="form-inline" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <div class="form-group" style="margin: 0;">
                    <label for="order-status" style="margin-right: 6px; font-size: 12px; color: #475569;">Payment Status:</label>
                    <select id="order-status" name="status" class="form-control input-sm" style="border-radius: 6px;">
                        <option value="">All Orders</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= $status ?>" <?= $filter === $status ? 'selected' : '' ?>><?= $status ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary btn-sm" type="submit" style="border-radius: 6px; font-weight: 600;">
                    <i class="fa fa-filter"></i> Apply Filter
                </button>
                <?php if ($filter !== '' || $search !== ''): ?>
                    <a href="order.php" class="btn btn-default btn-sm" style="border-radius: 6px;">Reset</a>
                <?php endif; ?>
            </div>

            <div class="form-group" style="margin: 0;">
                <div class="input-group">
                    <input type="text" name="search" value="<?= orderText($search) ?>" placeholder="Search ref, customer, phone..." class="form-control input-sm" style="border-radius: 6px 0 0 6px; width: 220px;">
                    <span class="input-group-btn">
                        <button class="btn btn-default btn-sm" type="submit" style="border-radius: 0 6px 6px 0;"><i class="fa fa-search"></i></button>
                    </span>
                </div>
            </div>
        </form>

        <!-- Orders Table -->
        <div class="table-responsive">
            <table class="table table-hover" style="margin-bottom: 0;">
                <thead>
                    <tr style="background: #f8fafc; color: #475569; font-size: 12px; text-transform: uppercase;">
                        <th>Order & Receipt #</th>
                        <th>Customer & Contact</th>
                        <th>Amount</th>
                        <th>Payment Status</th>
                        <th>Fulfillment</th>
                        <th style="text-align: right;">Receipt & Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$orders): ?>
                        <tr>
                            <td colspan="6" style="padding: 48px; text-align: center; color: #94a3b8;">
                                <i class="fa fa-folder-open-o fa-3x" style="margin-bottom: 10px;"></i><br>
                                No website orders found matching the filter criteria.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($orders as $order): 
                        $reference = $order['payment_id'];
                        $cash = in_array($order['payment_method'], ['COD', 'Cash on Delivery', 'Cash'], true);
                        $cancelled = $order['payment_status'] === 'Cancelled';
                        $paid = $order['payment_status'] === 'Completed';
                        $shipStatus = $order['shipping_status'] ?: 'Pending';
                    ?>
                        <tr>
                            <!-- Order Reference & Date -->
                            <td>
                                <a href="order-summary.php?payment_id=<?= rawurlencode($reference) ?>" style="font-weight: 700; color: #2563eb; font-family: monospace; font-size: 13px;">
                                    <i class="fa fa-file-text-o"></i> <?= orderText($reference) ?>
                                </a>
                                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                    <?= !empty($order['payment_date']) ? date('M d, Y · h:i A', strtotime($order['payment_date'])) : '-' ?>
                                </div>
                            </td>

                            <!-- Customer & Destination -->
                            <td>
                                <strong style="color: #0f172a; font-size: 13px;"><?= orderText($order['customer_name'] ?: 'Guest') ?></strong>
                                <div style="font-size: 11px; color: #64748b;">
                                    <?php if (!empty($order['customer_email'])): ?>
                                        <i class="fa fa-envelope-o"></i> <?= orderText($order['customer_email']) ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($order['shipping_phone'] ?: $order['billing_phone'])): ?>
                                        <i class="fa fa-phone"></i> <?= orderText($order['shipping_phone'] ?: $order['billing_phone']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Total Amount -->
                            <td style="white-space: nowrap; font-weight: 800; font-size: 14px; color: #0f172a;">
                                BDT <?= number_format((float)$order['paid_amount'], 2) ?>
                            </td>

                            <!-- Payment Status & Method -->
                            <td>
                                <?php if ($paid): ?>
                                    <span class="order-badge-status" style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">
                                        <i class="fa fa-check-circle"></i> Paid
                                    </span>
                                <?php elseif ($cancelled): ?>
                                    <span class="order-badge-status" style="background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
                                        <i class="fa fa-times-circle"></i> Cancelled
                                    </span>
                                <?php else: ?>
                                    <span class="order-badge-status" style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a;">
                                        <i class="fa fa-clock-o"></i> Pending
                                    </span>
                                <?php endif; ?>
                                <div style="font-size: 11px; color: #64748b; margin-top: 3px;">
                                    <?= orderText($order['payment_method'] ?: 'Online') ?>
                                </div>
                            </td>

                            <!-- Fulfillment Status -->
                            <td>
                                <?php if ($shipStatus === 'Delivered'): ?>
                                    <span class="order-badge-status" style="background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;">
                                        <i class="fa fa-check"></i> Delivered
                                    </span>
                                <?php elseif ($shipStatus === 'Shipped'): ?>
                                    <span class="order-badge-status" style="background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe;">
                                        <i class="fa fa-truck"></i> Shipped
                                    </span>
                                <?php elseif ($shipStatus === 'Processing'): ?>
                                    <span class="order-badge-status" style="background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa;">
                                        <i class="fa fa-refresh"></i> Processing
                                    </span>
                                <?php else: ?>
                                    <span class="order-badge-status" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">
                                        Pending
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($order['shipping_city'])): ?>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                        <i class="fa fa-map-marker text-muted"></i> <?= orderText($order['shipping_city']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Actions & Receipt View -->
                            <td style="text-align: right; white-space: nowrap;">
                                <a class="btn btn-default order-action-btn" href="order-summary.php?payment_id=<?= rawurlencode($reference) ?>" title="View Official Receipt">
                                    <i class="fa fa-eye text-primary"></i> Receipt
                                </a>
                                <a class="btn btn-default order-action-btn" href="order-summary.php?payment_id=<?= rawurlencode($reference) ?>&print=1" target="_blank" title="Print Official Receipt / Packing Slip">
                                    <i class="fa fa-print text-muted"></i>
                                </a>

                                <?php if (!$cancelled && $cash && !$paid): ?>
                                    <form method="post" action="order-change-status.php" style="display:inline-block; margin:0;">
                                        <input type="hidden" name="id" value="<?= orderText($reference) ?>">
                                        <button class="btn btn-success order-action-btn" type="submit" title="Confirm cash received">
                                            <i class="fa fa-check"></i> Cash
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!$cancelled && ($cash || $paid) && !in_array($shipStatus, ['Delivered', 'Completed'], true)): ?>
                                    <form method="post" action="shipping-change-status.php" style="display:inline-block; margin:0;">
                                        <input type="hidden" name="id" value="<?= orderText($reference) ?>">
                                        <input type="hidden" name="task" value="<?= $shipStatus === 'Shipped' ? 'Delivered' : 'Shipped' ?>">
                                        <button class="btn btn-info order-action-btn" type="submit" title="<?= $shipStatus === 'Shipped' ? 'Mark delivered' : 'Mark shipped' ?>">
                                            <i class="fa fa-truck"></i> <?= $shipStatus === 'Shipped' ? 'Delivered' : 'Ship' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!$cancelled && !$paid && !in_array($shipStatus, ['Shipped', 'Delivered', 'Completed'], true)): ?>
                                    <a class="btn btn-danger order-action-btn" href="order-delete.php?id=<?= rawurlencode($reference) ?>" onclick="return confirm('Cancel this order?')" title="Cancel Order">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <?php $totalPages = max(1, (int)ceil($total / $limit)); ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; flex-wrap: wrap; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
            <div style="font-size: 12px; color: #64748b;">
                Showing <strong><?= min($total, ($offset + 1)) ?></strong> to <strong><?= min($total, ($offset + count($orders))) ?></strong> of <strong><?= number_format($total) ?></strong> orders
            </div>
            <nav aria-label="Order pages">
                <ul class="pagination pagination-sm" style="margin: 0;">
                    <?php if ($page > 1): ?>
                        <li><a href="?page=<?= $page - 1 ?>&status=<?= rawurlencode($filter) ?>&search=<?= rawurlencode($search) ?>">&laquo; Prev</a></li>
                    <?php else: ?>
                        <li class="disabled"><span>&laquo; Prev</span></li>
                    <?php endif; ?>

                    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <li class="<?= $p === $page ? 'active' : '' ?>">
                            <a href="?page=<?= $p ?>&status=<?= rawurlencode($filter) ?>&search=<?= rawurlencode($search) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <li><a href="?page=<?= $page + 1 ?>&status=<?= rawurlencode($filter) ?>&search=<?= rawurlencode($search) ?>">Next &raquo;</a></li>
                    <?php else: ?>
                        <li class="disabled"><span>Next &raquo;</span></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
