<?php
/**
 * Financial Summary Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Financial Summary - 120 Stand Inventory';
$current_user = Stand120_Auth::get_current_user_data();
$is_admin = Stand120_Auth::is_admin();
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
        <i class="fas fa-wallet"></i>
        Financial Summary
    </h1>
    <div style="display: flex; gap: 12px; align-items: center;">
        <input type="date" id="finDate" class="form-control" value="<?php echo $today; ?>" style="max-width: 200px;">
        <a href="<?php echo home_url('/120-stand/financial-summary-history/'); ?>" class="history-btn">
            <i class="fas fa-history"></i> View History
        </a>
    </div>
</div>

<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <i class="fas fa-calculator"></i> Daily Financial Report
    </h3>
    
    <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 0.9rem;">
        <i class="fas fa-info-circle"></i> 
        Cash Left = (Cash Sales + Old Cash + Card Expense Cash Left + Extras) - Cash Expense. 
        Only Extras, Cash Expense, and Card Expense Cash Left fields are editable. Values auto-save.
    </p>
    
    <div class="table-responsive">
        <table class="table">
            <tbody>
                <tr>
                    <td style="font-weight: 600; width: 40%;">
                        <i class="fas fa-chart-line" style="color: var(--primary-color);"></i>
                        Total Sales
                    </td>
                    <td id="totalSales" class="formatted-number" style="font-size: 1.2rem; font-weight: 600;">₦0</td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">
                        <i class="fas fa-exchange-alt" style="color: var(--info-color);"></i>
                        Transfer/Card Sales
                    </td>
                    <td id="transferSales" class="formatted-number">₦0</td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">
                        <i class="fas fa-money-bill-wave" style="color: var(--success-color);"></i>
                        Cash Sales
                    </td>
                    <td id="cashSales" class="formatted-number">₦0</td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">
                        <i class="fas fa-motorcycle" style="color: var(--warning-color);"></i>
                        Delivery Fees
                    </td>
                    <td id="deliveryFees" class="formatted-number">₦0</td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">
                        <i class="fas fa-plus-circle" style="color: var(--success-color);"></i>
                        Extras Amount (₦)
                    </td>
                    <td>
                        <input type="text" id="extrasAmount" class="table-input number-input" placeholder="0" style="max-width: 150px;">
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">
                        <i class="fas fa-comment" style="color: var(--text-muted);"></i>
                        Extras Remark
                    </td>
                    <td>
                        <input type="text" id="extrasRemark" class="table-input" placeholder="Enter extras description..." style="max-width: 300px;">
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">
                        <i class="fas fa-minus-circle" style="color: var(--danger-color);"></i>
                        Cash Expense (₦)
                    </td>
                    <td>
                        <input type="text" id="expensesAmount" class="table-input number-input" placeholder="0" style="max-width: 150px;">
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">
                        <i class="fas fa-comment" style="color: var(--text-muted);"></i>
                        Cash Expense Remark
                    </td>
                    <td>
                        <input type="text" id="expensesRemark" class="table-input" placeholder="Enter cash expense description..." style="max-width: 300px;">
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">
                        <i class="fas fa-credit-card" style="color: var(--info-color);"></i>
                        Card Expense Cash Left (₦)
                    </td>
                    <td>
                        <input type="text" id="marketCardExpense" class="table-input number-input" placeholder="0" style="max-width: 150px;">
                    </td>
                </tr>
                <tr>
                    <td style="font-weight: 600;">
                        <i class="fas fa-clock" style="color: var(--text-muted);"></i>
                        Old Cash (Yesterday's Cash Left)
                    </td>
                    <?php if ($is_admin): ?>
                    <td>
                        <input type="text" id="oldCash" class="table-input number-input" placeholder="0" style="max-width: 150px;">
                    </td>
                    <?php else: ?>
                    <td id="oldCash" class="formatted-number">₦0</td>
                    <?php endif; ?>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Cash Left Highlight -->
    <div class="grand-total-section" style="margin-top: 24px;">
        <span class="grand-total-label">
            <i class="fas fa-cash-register"></i> Cash Left
        </span>
        <span id="cashLeft" class="grand-total-value">₦0</span>
    </div>
    
    <p style="color: var(--text-muted); margin-top: 16px; font-size: 0.85rem; text-align: center;">
        <i class="fas fa-info-circle"></i> 
        Formula: Cash Left = (Cash Sales + Old Cash + Card Expense Cash Left + Extras) - Cash Expense
    </p>
</div>

<script>
(function($) {
    $(document).ready(function() {
        if (typeof FinancialSummary !== 'undefined') {
            FinancialSummary.init();
            
            // Reload data when date changes
            $('#finDate').on('change', function() {
                FinancialSummary.loadData();
            });
        }
    });
})(jQuery);
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>