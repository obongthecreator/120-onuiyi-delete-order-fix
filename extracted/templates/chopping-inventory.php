<?php
/**
 * Chopping Inventory Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Chopping Inventory - 120 Stand Inventory';
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
        <iconify-icon icon="solar:scissors-linear"></iconify-icon>
        Chopping Inventory
    </h1>
    <a href="<?php echo home_url('/120-stand/chopping-inventory-history/'); ?>" class="history-btn">
        <iconify-icon icon="solar:history-linear"></iconify-icon> View History
    </a>
</div>

<div class="glass-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color: var(--primary-color);">
            <iconify-icon icon="solar:leaf-linear"></iconify-icon> Whole Fruit Processing
        </h3>
        <input type="date" id="chopDate" class="form-control" value="<?php echo $today; ?>" style="max-width: 200px;">
    </div>
    
    <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 0.9rem;">
        <iconify-icon icon="solar:info-circle-linear"></iconify-icon> 
        Values are auto-saved. Closing = Opening + Import - Prepared. 
        Import values come from Import Records. Packs gotten will update Stock Inventory.
        <?php if (!$is_admin): ?>
        Opening values can only be edited by admin.
        <?php endif; ?>
    </p>
    
    <div class="table-responsive">
        <table class="table" id="chopTable">
            <thead>
                <tr>
                    <th>Fruit</th>
                    <th>Opening (Whole)</th>
                    <th>Import (Whole)</th>
                    <th>Prepared (Whole)</th>
                    <th>Closing (Whole)</th>
                    <th>Pack(s) Gotten</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data loaded via JavaScript -->
            </tbody>
        </table>
    </div>
</div>

<script>
(function($) {
    $(document).ready(function() {
        if (typeof ChoppingInventory !== 'undefined') {
            ChoppingInventory.init();
            
            // Reload data when date changes
            $('#chopDate').on('change', function() {
                ChoppingInventory.loadData();
            });
        }
    });
})(jQuery);
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
