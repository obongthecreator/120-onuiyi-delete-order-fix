<?php
/**
 * Take Order Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Take Order - 120 Stand Inventory';
$current_user = Stand120_Auth::get_current_user_data();
$menu_items = Stand120_Database::get_menu_items();

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

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
    <h1 class="page-title" style="margin-bottom: 0;">
        <iconify-icon icon="solar:cart-plus-linear"></iconify-icon>
        Take Order
    </h1>
    <a href="<?php echo home_url('/120-stand/take-order-history/'); ?>" class="history-btn">
        <iconify-icon icon="solar:history-linear"></iconify-icon> View Order History
    </a>
</div>

<!-- Order Form -->
<form id="orderForm">
    <!-- Order Items Table -->
    <div class="glass-card">
        <h3 style="margin-bottom: 16px; color: var(--primary-color);">
            <iconify-icon icon="solar:list-linear"></iconify-icon> Order Items
        </h3>
        
        <div class="table-responsive">
            <table class="table" id="orderTable">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Price (₦)</th>
                        <th>Quantity</th>
                        <th>Total (₦)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($menu_items as $item): ?>
                    <tr data-product-id="<?php echo $item->id; ?>">
                        <td><?php echo esc_html($item->name); ?></td>
                        <td class="price-cell formatted-number"><span class="naira">₦</span><?php echo number_format($item->price, 0); ?></td>
                        <td>
                            <input type="number" class="table-input qty-input" value="0" min="0" data-price="<?php echo $item->price; ?>">
                        </td>
                        <td class="total-cell formatted-number"><span class="naira">₦</span>0</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="text-align: right; margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-glass);">
            <span style="font-size: 1.1rem; color: var(--text-secondary);">Subtotal: </span>
            <span id="subtotal" class="formatted-number" style="font-size: 1.3rem;"><span class="naira">₦</span>0</span>
        </div>
    </div>
    
    <!-- Payment Section -->
    <div class="glass-card">
        <h3 style="margin-bottom: 20px; color: var(--primary-color);">
            <iconify-icon icon="solar:card-linear"></iconify-icon> Payment Details
        </h3>
        
        <!-- Payment Method Selection -->
        <div class="form-group">
            <label class="form-label">Payment Method</label>
            <div class="payment-method-select">
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="transfer">
                    <iconify-icon icon="solar:card-linear"></iconify-icon>
                    <span>Transfer/Card</span>
                </label>
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="cash">
                    <iconify-icon icon="solar:banknote-2-linear"></iconify-icon>
                    <span>Cash</span>
                </label>
                <label class="payment-option">
                    <input type="radio" name="payment_method" value="both">
                    <iconify-icon icon="solar:wallet-money-linear"></iconify-icon>
                    <span>Transfer/Card + Cash</span>
                </label>
            </div>
        </div>
        
        <!-- Cash Amount Section (Hidden by default) -->
        <div id="cashSection" class="glass-card" style="display: none; margin-top: 16px; background: rgba(139, 0, 0, 0.03);">
            <h4 style="color: var(--primary-color); margin-bottom: 12px;">
                <iconify-icon icon="solar:banknote-2-linear"></iconify-icon> Cash Payment
            </h4>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="cashAmount">Cash Amount (₦)</label>
                <input type="text" id="cashAmount" class="form-control number-input" placeholder="Enter cash amount" disabled>
            </div>
        </div>
        
        <!-- Transfer Amount Section (Hidden by default) -->
        <div id="transferSection" class="glass-card" style="display: none; margin-top: 16px; background: rgba(139, 0, 0, 0.03);">
            <h4 style="color: var(--primary-color); margin-bottom: 12px;">
                <iconify-icon icon="solar:card-linear"></iconify-icon> Transfer/Card Payment
            </h4>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="transferAmount">Transfer/Card Amount (₦)</label>
                <input type="text" id="transferAmount" class="form-control number-input" placeholder="Enter transfer amount" disabled>
            </div>
        </div>
        
        <!-- Delivery Fee -->
        <div class="form-group" style="margin-top: 20px;">
            <label class="form-label" for="deliveryFee">
                <iconify-icon icon="solar:scooter-linear" style="color: var(--primary-color);"></iconify-icon> Delivery Fee (₦)
            </label>
            <input type="text" id="deliveryFee" class="form-control number-input" placeholder="Enter delivery fee (optional)">
        </div>
        
        <!-- Payment Confirmation (visible when Transfer/Card is selected) -->
        <div id="confirmationSection" class="confirmation-warning" style="display: none;">
            <label class="form-check">
                <input type="checkbox" id="paymentConfirmed">
                <span class="form-check-label">
                    <iconify-icon icon="solar:danger-triangle-linear"></iconify-icon>
                    I confirm that payment has been received and verified on the POS system before submitting this order.
                </span>
            </label>
        </div>
    </div>
    
    <!-- Grand Total -->
    <div class="grand-total-section">
        <span class="grand-total-label">
            <iconify-icon icon="solar:document-text-linear"></iconify-icon> Grand Total
        </span>
        <span id="grandTotal" class="grand-total-value"><span class="naira">₦</span>0</span>
    </div>
    
    <!-- Submit Button -->
    <div style="margin-top: 24px; text-align: center;">
        <button type="button" id="submitOrder" class="btn btn-primary btn-lg">
            <iconify-icon icon="solar:check-circle-linear"></iconify-icon> Submit Order
        </button>
    </div>
</form>

<script>
    jQuery(document).ready(function($) {
        if (typeof TakeOrder !== 'undefined') {
            // Initialize TakeOrder
            TakeOrder.init();
            
            // Calculate totals immediately
            TakeOrder.calculateTotals();
        }
    });
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
