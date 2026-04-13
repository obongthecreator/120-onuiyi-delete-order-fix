<?php
/**
 * Header Partial Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = Stand120_Auth::get_current_user_data();
$is_admin = Stand120_Auth::is_admin();
$current_page = get_query_var('stand120_page');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#8B0000">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo $page_title ?? '120 Stand Inventory'; ?></title>
    
    <!-- Prevent browser from caching pages -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Manifest for PWA -->
    <link rel="manifest" href="<?php echo STAND120_PLUGIN_URL; ?>manifest.json">
    
    <!-- Preconnect to fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Iconify (required for <iconify-icon> elements used across all templates) -->
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@2.3.0/dist/iconify-icon.min.js"></script>
    
    <!-- Plugin Styles -->
    <link rel="stylesheet" href="<?php echo STAND120_PLUGIN_URL; ?>assets/css/style.css?v=<?php echo STAND120_VERSION; ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo STAND120_PLUGIN_URL; ?>assets/images/logo.png">
    <link rel="apple-touch-icon" href="<?php echo STAND120_PLUGIN_URL; ?>assets/images/logo.png">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    
    <?php wp_head(); ?>
</head>
<body class="stand120-app">
    <!-- Offline Banner -->
    <div class="offline-banner">
        <i class="fas fa-wifi-slash"></i> You are offline. Changes will sync when you reconnect.
    </div>
    
    <!-- Header -->
    <header class="stand120-header">
        <a href="<?php echo home_url('/120-stand/'); ?>" class="stand120-logo">
            <img src="<?php echo STAND120_PLUGIN_URL; ?>assets/images/logo.png" alt="120 Stand">
            <span class="stand120-logo-text">120 Stand</span>
        </a>
        
        <nav class="stand120-nav">
            <a href="<?php echo home_url('/120-stand/'); ?>" class="stand120-nav-link <?php echo $current_page === 'home' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="<?php echo home_url('/120-stand/take-order/'); ?>" class="stand120-nav-link <?php echo $current_page === 'take-order' ? 'active' : ''; ?>">
                <i class="fas fa-cart-plus"></i> Take Order
            </a>
            <a href="<?php echo home_url('/120-stand/product-summary/'); ?>" class="stand120-nav-link <?php echo $current_page === 'product-summary' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Summary
            </a>
            <?php if ($is_admin): ?>
            <a href="<?php echo home_url('/120-stand/admin-panel/'); ?>" class="stand120-nav-link <?php echo $current_page === 'admin-panel' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Admin
            </a>
            <?php endif; ?>
            <a href="<?php echo wp_logout_url(home_url('/120-stand/login/')); ?>" class="stand120-nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
        
        <div class="hamburger-menu">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </header>
    
    <!-- Mobile Sidebar Overlay -->
    <div class="mobile-sidebar-overlay"></div>
    
    <!-- Mobile Sidebar -->
    <aside class="mobile-sidebar">
        <a href="<?php echo home_url('/120-stand/'); ?>" class="stand120-logo">
            <img src="<?php echo STAND120_PLUGIN_URL; ?>assets/images/logo.png" alt="120 Stand">
            <span class="stand120-logo-text">120 Stand</span>
        </a>
        
        <div class="mobile-nav-links">
            <a href="<?php echo home_url('/120-stand/'); ?>" class="mobile-nav-link <?php echo $current_page === 'home' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="<?php echo home_url('/120-stand/take-order/'); ?>" class="mobile-nav-link <?php echo $current_page === 'take-order' ? 'active' : ''; ?>">
                <i class="fas fa-cart-plus"></i> Take Order
            </a>
            <a href="<?php echo home_url('/120-stand/order-preparation/'); ?>" class="mobile-nav-link <?php echo $current_page === 'order-preparation' ? 'active' : ''; ?>">
                <i class="fas fa-blender"></i> Order Preparation
            </a>
            <a href="<?php echo home_url('/120-stand/stock-inventory/'); ?>" class="mobile-nav-link <?php echo $current_page === 'stock-inventory' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i> Stock Inventory
            </a>
            <a href="<?php echo home_url('/120-stand/chopping-inventory/'); ?>" class="mobile-nav-link <?php echo $current_page === 'chopping-inventory' ? 'active' : ''; ?>">
                <i class="fas fa-cut"></i> Chopping Inventory
            </a>
            <a href="<?php echo home_url('/120-stand/import-record/'); ?>" class="mobile-nav-link <?php echo $current_page === 'import-record' ? 'active' : ''; ?>">
                <i class="fas fa-truck-loading"></i> Import Record
            </a>
            <a href="<?php echo home_url('/120-stand/product-summary/'); ?>" class="mobile-nav-link <?php echo $current_page === 'product-summary' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Product Summary
            </a>
            <a href="<?php echo home_url('/120-stand/financial-summary/'); ?>" class="mobile-nav-link <?php echo $current_page === 'financial-summary' ? 'active' : ''; ?>">
                <i class="fas fa-wallet"></i> Financial Summary
            </a>
            <a href="<?php echo home_url('/120-stand/expense-record/'); ?>" class="mobile-nav-link <?php echo $current_page === 'expense-record' ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i> Market Expense
            </a>
            <a href="<?php echo home_url('/120-stand/profile/'); ?>" class="mobile-nav-link <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> Profile
            </a>
            <?php if ($is_admin): ?>
            <a href="<?php echo home_url('/120-stand/admin-panel/'); ?>" class="mobile-nav-link <?php echo $current_page === 'admin-panel' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Admin Panel
            </a>
            <a href="<?php echo home_url('/120-stand/analytics/'); ?>" class="mobile-nav-link <?php echo $current_page === 'analytics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Analytics
            </a>
            <a href="<?php echo home_url('/120-stand/reconciliation/'); ?>" class="mobile-nav-link <?php echo $current_page === 'reconciliation' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> Reconciliation
            </a>
            <?php endif; ?>
            <a href="<?php echo wp_logout_url(home_url('/120-stand/login/')); ?>" class="mobile-nav-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>
    
    <!-- Main Container -->
    <main class="stand120-container">
        <div class="page-content">