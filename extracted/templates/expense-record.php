<?php
/**
 * Card Expense Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Card Expense - 120 Stand Inventory';
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
            <i class="fas fa-calendar-alt"></i>
            <span class="date-text"><?php echo date_i18n('l, F j, Y'); ?></span>
        </div>
        <div class="time-display">
            <i class="fas fa-clock"></i>
            <span class="digital-clock">--:--:--</span>
        </div>
    </div>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
    <h1 class="page-title" style="margin-bottom: 0;">
        <i class="fas fa-receipt"></i>
        Card Expense
    </h1>
    <div style="display: flex; gap: 12px; align-items: center;">
        <input type="date" id="expenseDate" class="form-control" value="<?php echo $today; ?>" style="max-width: 200px;">
        <a href="<?php echo home_url('/120-stand/expense-history/'); ?>" class="history-btn">
            <i class="fas fa-history"></i> View History
        </a>
    </div>
</div>

<div class="glass-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color: var(--primary-color);">
            <i class="fas fa-file-invoice-dollar"></i> Daily Card Expenses
        </h3>
        <button id="addExpenseRow" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Item
        </button>
    </div>
    
    <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 0.9rem;">
        <i class="fas fa-info-circle"></i> 
        Enter each expense item with description and amount. Total is auto-calculated and stored in Card Expense history.
    </p>
    
    <div class="table-responsive">
        <table class="table" id="expenseTable">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount (₦)</th>
                    <th style="width: 50px;"></th>
                </tr>
            </thead>
            <tbody id="expenseBody">
                <!-- Data loaded via JavaScript -->
            </tbody>
        </table>
    </div>
    
    <!-- Total Section -->
    <div class="grand-total-section" style="margin-top: 24px;">
        <span class="grand-total-label">
            <i class="fas fa-calculator"></i> Total Expenses
        </span>
        <span id="expenseTotal" class="grand-total-value"><span class="naira">₦</span>0</span>
    </div>
    
    <div style="margin-top: 16px; text-align: center;">
        <button id="saveExpenses" class="btn btn-success">
            <i class="fas fa-save"></i> Save Expenses
        </button>
    </div>
</div>

<script>
(function($) {
    $(document).ready(function() {
        if (typeof MarketExpense !== 'undefined') {
            MarketExpense.init();
            
            // Reload data when date changes
            $('#expenseDate').on('change', function() {
                MarketExpense.loadData();
            });
        }
    });
})(jQuery);
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>