<?php
/**
 * Home Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$page_title = 'Home - 120 Stand Inventory';
$current_user = Stand120_Auth::get_current_user_data();
$is_admin = Stand120_Auth::is_admin();

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

<h1 class="page-title">
    <i class="fas fa-th-large"></i>
    Dashboard
</h1>

<!-- Home Cards Grid -->
<div class="home-cards-grid">
    <!-- Take Order -->
    <a href="<?php echo home_url('/120-stand/take-order/'); ?>" class="home-card">
        <div class="home-card-icon">
            <i class="fas fa-cart-plus"></i>
        </div>
        <h3>Take Order</h3>
        <p>Create new customer orders, select items, set quantities, and process payments quickly.</p>
        <span class="home-card-btn">
            <i class="fas fa-arrow-right"></i> Open
        </span>
    </a>
    
    <!-- Order Preparation -->
    <a href="<?php echo home_url('/120-stand/order-preparation/'); ?>" class="home-card">
        <div class="home-card-icon">
            <i class="fas fa-blender"></i>
        </div>
        <h3>Order Preparation</h3>
        <p>Track daily fruit preparation including opening stock, additions, sales, and closing quantities.</p>
        <span class="home-card-btn">
            <i class="fas fa-arrow-right"></i> Open
        </span>
    </a>
    
    <!-- Stock Inventory -->
    <a href="<?php echo home_url('/120-stand/stock-inventory/'); ?>" class="home-card">
        <div class="home-card-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <h3>Stock Inventory</h3>
        <p>Monitor pack levels for fruits and non-fruit items with real-time opening, added, and closing stock.</p>
        <span class="home-card-btn">
            <i class="fas fa-arrow-right"></i> Open
        </span>
    </a>
    
    <!-- Chopping Inventory -->
    <a href="<?php echo home_url('/120-stand/chopping-inventory/'); ?>" class="home-card">
        <div class="home-card-icon">
            <i class="fas fa-cut"></i>
        </div>
        <h3>Chopping Inventory</h3>
        <p>Record whole fruit processing - track opening, imports, prepared quantities, and packs obtained.</p>
        <span class="home-card-btn">
            <i class="fas fa-arrow-right"></i> Open
        </span>
    </a>
    
    <!-- Import Record -->
    <a href="<?php echo home_url('/120-stand/import-record/'); ?>" class="home-card">
        <div class="home-card-icon">
            <i class="fas fa-truck-loading"></i>
        </div>
        <h3>Import Record</h3>
        <p>Log daily product imports which automatically update stock and chopping inventory forms.</p>
        <span class="home-card-btn">
            <i class="fas fa-arrow-right"></i> Open
        </span>
    </a>
    
    <!-- Product Summary -->
    <a href="<?php echo home_url('/120-stand/product-summary/'); ?>" class="home-card">
        <div class="home-card-icon">
            <i class="fas fa-chart-bar"></i>
        </div>
        <h3>Product Summary</h3>
        <p>View total products sold, revenue generated, active staff, and detailed sales breakdown.</p>
        <span class="home-card-btn">
            <i class="fas fa-arrow-right"></i> Open
        </span>
    </a>
    
    <!-- Financial Summary -->
    <a href="<?php echo home_url('/120-stand/financial-summary/'); ?>" class="home-card">
        <div class="home-card-icon">
            <i class="fas fa-wallet"></i>
        </div>
        <h3>Financial Summary</h3>
        <p>Track daily finances including sales, cash/transfer breakdown, expenses, and cash reconciliation.</p>
        <span class="home-card-btn">
            <i class="fas fa-arrow-right"></i> Open
        </span>
    </a>
    
    <!-- Card Expense -->
    <a href="<?php echo home_url('/120-stand/expense-record/'); ?>" class="home-card">
        <div class="home-card-icon">
            <i class="fas fa-receipt"></i>
        </div>
        <h3>Card Expense</h3>
        <p>Record daily card expenses with description and amount. Stores in Card Expense history.</p>
        <span class="home-card-btn">
            <i class="fas fa-arrow-right"></i> Open
        </span>
    </a>
    
    <?php if ($is_admin): ?>
    <!-- Admin Panel -->
    <a href="<?php echo home_url('/120-stand/admin-panel/'); ?>" class="home-card">
        <div class="home-card-icon">
            <i class="fas fa-cog"></i>
        </div>
        <h3>Admin Panel</h3>
        <p>Manage products, prices, staff accounts, opening values, and system settings.</p>
        <span class="home-card-btn">
            <i class="fas fa-arrow-right"></i> Open
        </span>
    </a>
    
    <!-- Analytics -->
    <a href="<?php echo home_url('/120-stand/analytics/'); ?>" class="home-card">
        <div class="home-card-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <h3>Analytics</h3>
        <p>View performance analytics, sales trends, staff performance, and business insights.</p>
        <span class="home-card-btn">
            <i class="fas fa-arrow-right"></i> Open
        </span>
    </a>
    
    <!-- Reconciliation -->
    <a href="<?php echo home_url('/120-stand/reconciliation/'); ?>" class="home-card">
        <div class="home-card-icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <h3>Reconciliation</h3>
        <p>Daily record reconciliation calendar. Two admins must verify each date's records.</p>
        <span class="home-card-btn">
            <i class="fas fa-arrow-right"></i> Open
        </span>
    </a>
    
    <?php endif; ?>
</div>

<?php include STAND120_PLUGIN_DIR . 'templates/partials/footer.php'; ?>