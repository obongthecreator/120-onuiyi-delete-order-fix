<?php
/**
 * Admin Panel Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check admin access
if (!Stand120_Auth::is_admin()) {
    wp_redirect(home_url('/120-stand/'));
    exit;
}

$page_title = 'Admin Panel - 120 Stand Inventory';
$current_user = Stand120_Auth::get_current_user_data();

include STAND120_PLUGIN_DIR . 'templates/partials/header.php';
?>

<!-- Staff Info Bar -->
<div class="staff-info-bar">
    <div class="staff-info">
        <div class="staff-avatar">
            <?php echo strtoupper(substr($current_user['display_name'], 0, 1)); ?>
        </div>
        <div class="staff-details">
            <h4><?php echo esc_html($current_user['display_name']); ?></h4>
            <span>Administrator</span>
        </div>
    </div>
    <div class="datetime-display">
        <div class="date-display">
            <iconify-icon icon="solar:calendar-linear"></iconify-icon>
            <span class="date-text"><?php echo date_i18n('l, F j, Y'); ?></span>
        </div>
        <div class="time-display">
            <iconify-icon icon="solar:clock-circle-linear"></iconify-icon>
            <span class="digital-clock">--:--:--</span>
        </div>
    </div>
</div>

<h1 class="page-title">
    <iconify-icon icon="solar:settings-linear"></iconify-icon>
    Admin Panel
</h1>

<!-- Tabs -->
<div class="tabs">
    <button class="tab-btn active" data-tab="products-tab">
        <iconify-icon icon="solar:box-linear"></iconify-icon> Products
    </button>
    <button class="tab-btn" data-tab="staff-tab">
        <iconify-icon icon="solar:users-group-two-rounded-linear"></iconify-icon> Staff
    </button>
    <button class="tab-btn" data-tab="opening-tab">
        <iconify-icon icon="solar:pen-linear"></iconify-icon> Opening Values
    </button>
    <button class="tab-btn" data-tab="settings-tab">
        <iconify-icon icon="solar:tuning-2-linear"></iconify-icon> Settings
    </button>
    <button class="tab-btn" data-tab="orders-tab">
        <iconify-icon icon="solar:cart-large-2-linear"></iconify-icon> Orders
    </button>
    <?php if (Stand120_Auth::is_super_admin()): ?>
    <button class="tab-btn" data-tab="super-admin-tab">
        <iconify-icon icon="solar:shield-check-linear"></iconify-icon> Super Admin
    </button>
    <?php endif; ?>
</div>

<!-- Products Tab -->
<div id="products-tab" class="tab-content active">
    <div class="glass-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: var(--primary-color);">
                <iconify-icon icon="solar:box-linear"></iconify-icon> Manage Products
            </h3>
            <div class="admin-actions">
                <button id="addProduct" class="btn btn-primary btn-sm">
                    <iconify-icon icon="solar:add-circle-linear"></iconify-icon> Add Product
                </button>
                <button id="saveProducts" class="btn btn-success btn-sm">
                    <iconify-icon icon="solar:diskette-linear"></iconify-icon> Save All
                </button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table" id="productsTable">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Price (₦)</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data loaded via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Staff Tab -->
<div id="staff-tab" class="tab-content">
    <div class="glass-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: var(--primary-color);">
                <iconify-icon icon="solar:users-group-rounded-linear"></iconify-icon> Manage Staff
            </h3>
            <button id="addStaff" class="btn btn-primary btn-sm">
                <iconify-icon icon="solar:user-plus-linear"></iconify-icon> Add Staff
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="table" id="staffTable">
                <thead>
                    <tr>
                        <th>Full Name</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data loaded via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Opening Values Tab -->
<div id="opening-tab" class="tab-content">
    <div class="glass-card">
        <h3 style="margin-bottom: 20px; color: var(--primary-color);">
            <iconify-icon icon="solar:pen-linear"></iconify-icon> Set Opening Values
        </h3>
        
        <p style="color: var(--text-muted); margin-bottom: 20px;">
            <iconify-icon icon="solar:info-circle-linear"></iconify-icon> 
            Set initial opening values for inventory tracking. These values will be used as starting points.
        </p>
        
        <div class="form-group">
            <label class="form-label">Select Date</label>
            <input type="date" id="openingDate" class="form-control" value="<?php echo date('Y-m-d'); ?>" style="max-width: 200px;">
        </div>
        
        <!-- Order Preparation Opening -->
        <div class="admin-section">
            <h4 class="admin-section-title">Order Preparation Opening Values</h4>
            <div class="table-responsive">
                <table class="table" id="prepOpeningTable">
                    <thead>
                        <tr>
                            <th>Menu Item</th>
                            <th>Opening Value (Cup/Bottle)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data loaded via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Stock Inventory Opening -->
        <div class="admin-section">
            <h4 class="admin-section-title">Stock Inventory Opening Values</h4>
            <div class="table-responsive">
                <table class="table" id="stockOpeningTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Opening Packs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data loaded via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Chopping Inventory Opening -->
        <div class="admin-section">
            <h4 class="admin-section-title">Chopping Inventory Opening Values</h4>
            <div class="table-responsive">
                <table class="table" id="chopOpeningTable">
                    <thead>
                        <tr>
                            <th>Fruit</th>
                            <th>Opening (Whole)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data loaded via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <button id="saveOpeningValues" class="btn btn-success">
            <iconify-icon icon="solar:diskette-linear"></iconify-icon> Save Opening Values
        </button>
    </div>
</div>

<!-- Settings Tab -->
<div id="settings-tab" class="tab-content">
    <div class="glass-card">
        <h3 style="margin-bottom: 20px; color: var(--primary-color);">
            <iconify-icon icon="solar:tuning-2-linear"></iconify-icon> System Settings
        </h3>
        
        <div class="form-group">
            <label class="form-label">Business Name</label>
            <input type="text" class="form-control" value="120 Stand" readonly>
        </div>
        
        <div class="form-group">
            <label class="form-label">Currency Symbol</label>
            <input type="text" class="form-control" value="₦" readonly>
        </div>
        
        <div class="form-group">
            <label class="form-label">Day Reset Time</label>
            <input type="text" class="form-control" value="11:59 PM" readonly>
            <small style="color: var(--text-muted);">Inventory values reset to new day after this time</small>
        </div>
        
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-glass);">
            <h4 style="color: var(--text-secondary); margin-bottom: 16px;">Data Management</h4>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <button id="exportData" class="btn btn-secondary">
                    <iconify-icon icon="solar:download-minimalistic-linear"></iconify-icon> Export Data
                </button>
                <button id="clearCache" class="btn btn-secondary">
                    <iconify-icon icon="solar:refresh-linear"></iconify-icon> Clear Cache
                </button>
                <?php if (Stand120_Auth::is_super_admin()): ?>
                <button id="clearAllRecords" class="btn btn-danger">
                    <iconify-icon icon="solar:trash-bin-trash-linear"></iconify-icon> Clear All Records
                </button>
                <?php endif; ?>
            </div>
            <?php if (!Stand120_Auth::is_super_admin()): ?>
            <p style="color: var(--text-muted); margin-top: 12px; font-size: 0.85rem;">
                <iconify-icon icon="solar:info-circle-linear"></iconify-icon>
                Only Super Admins can clear all records. Contact a Super Admin if needed.
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Order Management Tab -->
<div id="orders-tab" class="tab-content">
    <div class="glass-card">
        <h3 style="margin-bottom: 20px; color: var(--primary-color);">
            <iconify-icon icon="solar:cart-large-2-linear"></iconify-icon> Order Management
        </h3>
        <p style="color: var(--text-muted); margin-bottom: 20px;">
            <iconify-icon icon="solar:info-circle-linear"></iconify-icon>
            Delete orders to remove them and their effects on financial summaries. 
            <?php if (!Stand120_Auth::is_super_admin()): ?>
            <strong>Note:</strong> You can only delete orders from today.
            <?php endif; ?>
        </p>
        
        <div class="filter-section" style="margin-bottom: 20px;">
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" id="orderDateFrom" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" id="orderDateTo" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button id="loadOrders" class="btn btn-primary">
                    <iconify-icon icon="solar:magnifer-linear"></iconify-icon> Load Orders
                </button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table" id="adminOrdersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Staff</th>
                        <th>Items</th>
                        <th>Total (₦)</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        
        <div id="orderPagination" class="pagination" style="margin-top: 16px;">
            <button class="pagination-btn" id="prevOrderPage" disabled><iconify-icon icon="solar:alt-arrow-left-linear"></iconify-icon> Previous</button>
            <span class="pagination-info">Page <span id="orderCurrentPage">1</span> of <span id="orderTotalPages">1</span></span>
            <button class="pagination-btn" id="nextOrderPage">Next <iconify-icon icon="solar:alt-arrow-right-linear"></iconify-icon></button>
        </div>
    </div>
</div>

<?php if (Stand120_Auth::is_super_admin()): ?>
<!-- Super Admin Tab -->
<div id="super-admin-tab" class="tab-content">
    <!-- System Health Section -->
    <div class="glass-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: var(--primary-color);">
                <iconify-icon icon="solar:shield-check-linear"></iconify-icon> System Health Diagnostics
            </h3>
            <button id="refreshDiagnostics" class="btn btn-primary btn-sm">
                <iconify-icon icon="solar:refresh-linear"></iconify-icon> Refresh
            </button>
        </div>

        <p style="color: var(--text-muted); margin-bottom: 20px;">
            <iconify-icon icon="solar:info-circle-linear"></iconify-icon>
            Overview of all forms and features. Shows what is working, what needs attention, and how to fix issues.
        </p>

        <!-- Summary Cards -->
        <div id="diagnosticsSummary" class="diagnostics-summary">
            <div class="diagnostics-summary-card diagnostics-good">
                <iconify-icon icon="solar:check-circle-linear" style="font-size: 1.5rem;"></iconify-icon>
                <span class="diagnostics-summary-count" id="diagGoodCount">-</span>
                <span class="diagnostics-summary-label">Working</span>
            </div>
            <div class="diagnostics-summary-card diagnostics-warning">
                <iconify-icon icon="solar:danger-triangle-linear" style="font-size: 1.5rem;"></iconify-icon>
                <span class="diagnostics-summary-count" id="diagWarningCount">-</span>
                <span class="diagnostics-summary-label">Warnings</span>
            </div>
            <div class="diagnostics-summary-card diagnostics-error">
                <iconify-icon icon="solar:close-circle-linear" style="font-size: 1.5rem;"></iconify-icon>
                <span class="diagnostics-summary-count" id="diagErrorCount">-</span>
                <span class="diagnostics-summary-label">Errors</span>
            </div>
        </div>

        <!-- Diagnostics List -->
        <div id="diagnosticsList" class="diagnostics-list">
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <iconify-icon icon="solar:refresh-linear" style="font-size: 2rem;"></iconify-icon>
                <p style="margin-top: 8px;">Loading diagnostics...</p>
            </div>
        </div>
    </div>

    <!-- Danger Zone Section -->
    <div class="glass-card" style="margin-top: 20px; border: 1px solid var(--danger-color);">
        <h3 style="margin-bottom: 16px; color: var(--danger-color);">
            <iconify-icon icon="solar:danger-triangle-linear"></iconify-icon> Danger Zone
        </h3>
        <p style="color: var(--text-muted); margin-bottom: 20px;">
            <iconify-icon icon="solar:info-circle-linear"></iconify-icon>
            These actions are irreversible. Only use them when absolutely necessary.
        </p>
        <button id="superAdminClearAll" class="btn btn-danger">
            <iconify-icon icon="solar:trash-bin-trash-linear"></iconify-icon> Delete All Records Across Site
        </button>
        <p style="color: var(--text-muted); margin-top: 12px; font-size: 0.85rem;">
            This will permanently delete all orders, inventory records, financial summaries, expenses, activity logs, and sync queue data. Products and staff will not be affected.
        </p>
    </div>
</div>
<?php endif; ?>

<script>
(function($) {
    $(document).ready(function() {
        if (typeof AdminPanel !== 'undefined') {
            AdminPanel.init();
        }
    });
})(jQuery);
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
