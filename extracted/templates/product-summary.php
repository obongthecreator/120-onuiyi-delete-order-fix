<?php
/**
 * Product Summary Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Product Summary - 120 Stand Inventory';
$current_user = Stand120_Auth::get_current_user_data();
$today = date('Y-m-d');

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
            <span><?php echo ucfirst($current_user['role']); ?></span>
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
    <iconify-icon icon="solar:chart-2-linear"></iconify-icon>
    Product Summary
</h1>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card glass-card">
        <div class="summary-card-icon">
            <iconify-icon icon="solar:bag-4-linear"></iconify-icon>
        </div>
        <span class="summary-card-label">Total Products Sold</span>
        <span id="totalProductsSold" class="summary-card-value">0</span>
    </div>
    
    <div class="summary-card glass-card">
        <div class="summary-card-icon">
            <iconify-icon icon="solar:money-bag-linear"></iconify-icon>
        </div>
        <span class="summary-card-label">Total Revenue</span>
        <span id="totalRevenue" class="summary-card-value"><span class="naira">₦</span>0</span>
    </div>
    
    <div class="summary-card glass-card">
        <div class="summary-card-icon">
            <iconify-icon icon="solar:users-group-two-rounded-linear"></iconify-icon>
        </div>
        <span class="summary-card-label">Active Staff Today</span>
        <span id="activeStaff" class="summary-card-value">0</span>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="filter-group">
        <label>From Date</label>
        <input type="date" id="dateFrom" class="form-control" value="<?php echo $today; ?>">
    </div>
    <div class="filter-group">
        <label>To Date</label>
        <input type="date" id="dateTo" class="form-control" value="<?php echo $today; ?>">
    </div>
    <div class="filter-group">
        <label>&nbsp;</label>
        <button id="filterBtn" class="btn btn-primary">
            <iconify-icon icon="solar:filter-linear"></iconify-icon> Apply Filter
        </button>
    </div>
</div>

<!-- Sales Breakdown Table -->
<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:document-text-linear"></iconify-icon> Sales Breakdown
    </h3>
    
    <div class="table-responsive">
        <table class="table" id="summaryTable">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Staff</th>
                    <th>Quantity</th>
                    <th>Amount (₦)</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data loaded via JavaScript -->
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="pagination">
        <button class="pagination-btn" id="prevPage" disabled>
            <iconify-icon icon="solar:alt-arrow-left-linear"></iconify-icon> Previous
        </button>
        <span class="pagination-info">Page <span id="currentPage">1</span> of <span id="totalPages">1</span></span>
        <button class="pagination-btn" id="nextPage">
            Next <iconify-icon icon="solar:alt-arrow-right-linear"></iconify-icon>
        </button>
    </div>
</div>

<script>
(function($) {
    $(document).ready(function() {
        if (typeof ProductSummary !== 'undefined') {
            ProductSummary.init();
        }
    });
})(jQuery);
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
