<?php
/**
 * Profile Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Profile - 120 Stand Inventory';
$current_user = Stand120_Auth::get_current_user_data();
$is_admin = Stand120_Auth::is_admin();

// Get staff statistics
global $wpdb;
$orders_table = $wpdb->prefix . 'stand120_orders';
$staff_id = $current_user['staff_id'];
$today = date('Y-m-d');
$month_start = date('Y-m-01');

$today_orders = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $orders_table WHERE staff_id = %d AND order_date = %s",
    $staff_id, $today
));

$today_sales = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(grand_total) FROM $orders_table WHERE staff_id = %d AND order_date = %s",
    $staff_id, $today
)) ?: 0;

$month_sales = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(grand_total) FROM $orders_table WHERE staff_id = %d AND order_date >= %s",
    $staff_id, $month_start
)) ?: 0;

include STAND120_PLUGIN_DIR . 'templates/partials/header.php';
?>

<h1 class="page-title">
    <iconify-icon icon="solar:user-linear"></iconify-icon>
    My Profile
</h1>

<div class="glass-card">
    <div class="profile-header">
        <div class="profile-avatar">
            <?php echo strtoupper(substr($current_user['display_name'], 0, 1)); ?>
        </div>
        <h2 class="profile-name"><?php echo esc_html($current_user['display_name']); ?></h2>
        <p class="profile-role">
            <iconify-icon icon="solar:<?php echo $is_admin ? 'crown-linear' : 'user-linear'; ?>"></iconify-icon>
            <?php echo ucfirst($current_user['role']); ?>
        </p>
        
        <div class="profile-stats">
            <div class="profile-stat">
                <span class="profile-stat-value"><?php echo intval($today_orders); ?></span>
                <span class="profile-stat-label">Orders Today</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><span class="naira">₦</span><?php echo number_format($today_sales, 0); ?></span>
                <span class="profile-stat-label">Sales Today</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><span class="naira">₦</span><?php echo number_format($month_sales, 0); ?></span>
                <span class="profile-stat-label">This Month</span>
            </div>
        </div>
    </div>
</div>

<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:info-circle-linear"></iconify-icon> Account Information
    </h3>
    
    <div style="display: grid; gap: 16px;">
        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border-glass);">
            <span style="color: var(--text-muted);">Username</span>
            <span><?php echo esc_html($current_user['username']); ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border-glass);">
            <span style="color: var(--text-muted);">Email</span>
            <span><?php echo esc_html($current_user['email']); ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border-glass);">
            <span style="color: var(--text-muted);">Role</span>
            <span><?php echo ucfirst($current_user['role']); ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 12px 0;">
            <span style="color: var(--text-muted);">Status</span>
            <span class="status-badge status-synced">
                <iconify-icon icon="solar:check-read-linear"></iconify-icon> Active
            </span>
        </div>
    </div>
</div>

<div class="glass-card">
    <h3 style="margin-bottom: 20px; color: var(--primary-color);">
        <iconify-icon icon="solar:link-linear"></iconify-icon> Quick Links
    </h3>
    
    <div style="display: grid; gap: 12px;">
        <a href="<?php echo home_url('/120-stand/take-order-history/'); ?>" class="btn btn-secondary" style="justify-content: flex-start;">
            <iconify-icon icon="solar:history-linear"></iconify-icon> My Order History
        </a>
        <?php if ($is_admin): ?>
        <a href="<?php echo home_url('/120-stand/admin-panel/'); ?>" class="btn btn-secondary" style="justify-content: flex-start;">
            <iconify-icon icon="solar:settings-linear"></iconify-icon> Admin Panel
        </a>
        <a href="<?php echo home_url('/120-stand/analytics/'); ?>" class="btn btn-secondary" style="justify-content: flex-start;">
            <iconify-icon icon="solar:graph-up-linear"></iconify-icon> Analytics Dashboard
        </a>
        <?php endif; ?>
        <a href="<?php echo wp_logout_url(home_url('/120-stand/login/')); ?>" class="btn btn-danger" style="justify-content: flex-start;">
            <iconify-icon icon="solar:logout-2-linear"></iconify-icon> Logout
        </a>
    </div>
</div>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>
