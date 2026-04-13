<?php
/**
 * Import Record Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Import Record - 120 Stand Inventory';
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

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
    <h1 class="page-title" style="margin-bottom: 0;">
        <iconify-icon icon="solar:delivery-linear"></iconify-icon>
        Import Record
    </h1>
    <a href="<?php echo home_url('/120-stand/import-record-history/'); ?>" class="history-btn">
        <iconify-icon icon="solar:history-linear"></iconify-icon> View History
    </a>
</div>

<div class="glass-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color: var(--primary-color);">
            <iconify-icon icon="solar:delivery-linear"></iconify-icon> Daily Product Imports
        </h3>
        <input type="date" id="importDate" class="form-control" value="<?php echo $today; ?>" style="max-width: 200px;">
    </div>
    
    <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 0.9rem;">
        <iconify-icon icon="solar:info-circle-linear"></iconify-icon> 
        Values are auto-saved and synced in real-time. Fruit imports update Chopping Inventory.
        Non-fruit imports update Stock Inventory added packs.
    </p>
    
    <div class="table-responsive">
        <table class="table" id="importTable">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity Imported</th>
                    <th>Sync Status</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data loaded via JavaScript -->
            </tbody>
        </table>
    </div>
</div>

<style>
    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
        margin-left: 8px;
        text-transform: uppercase;
    }
    .badge-fruit {
        background: rgba(40, 167, 69, 0.2);
        color: #28a745;
    }
    .badge-non_fruit {
        background: rgba(23, 162, 184, 0.2);
        color: #17a2b8;
    }
    .icon-spin {
        animation: iconSpin 1s infinite linear;
    }
    @keyframes iconSpin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<script>
(function($) {
    $(document).ready(function() {
        if (typeof ImportRecord !== 'undefined') {
            ImportRecord.init();
            
            // Reload data when date changes
            $('#importDate').on('change', function() {
                ImportRecord.loadData();
            });
        }
    });
})(jQuery);
</script>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
