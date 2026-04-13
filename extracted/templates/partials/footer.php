<?php
/**
 * Footer Partial Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_page = get_query_var('stand120_page');
$current_user = Stand120_Auth::get_current_user_data();
$is_admin = Stand120_Auth::is_admin();
?>
        </div><!-- .page-content -->
    </main><!-- .stand120-container -->
    
    <!-- Scroll to Top Button -->
    <button class="scroll-to-top" aria-label="Scroll to top">
        <svg class="scroll-progress-ring" viewBox="0 0 56 56">
            <circle class="bg" cx="28" cy="28" r="25"></circle>
            <circle class="progress" cx="28" cy="28" r="25"></circle>
        </svg>
        <iconify-icon icon="solar:arrow-up-linear"></iconify-icon>
    </button>
    
    <!-- Bottom Navigation (Mobile) -->
    <nav class="bottom-nav">
        <a href="<?php echo home_url('/120-stand/'); ?>" class="bottom-nav-item <?php echo $current_page === 'home' ? 'active' : ''; ?>">
            <iconify-icon icon="solar:home-2-linear"></iconify-icon>
            <span>Home</span>
        </a>
        <a href="<?php echo home_url('/120-stand/take-order/'); ?>" class="bottom-nav-item <?php echo $current_page === 'take-order' ? 'active' : ''; ?>">
            <iconify-icon icon="solar:cart-plus-linear"></iconify-icon>
            <span>Order</span>
        </a>
        <a href="<?php echo home_url('/120-stand/financial-summary/'); ?>" class="bottom-nav-item <?php echo $current_page === 'financial-summary' ? 'active' : ''; ?>">
            <iconify-icon icon="solar:wallet-linear"></iconify-icon>
            <span>Finance</span>
        </a>
        <a href="<?php echo home_url('/120-stand/product-summary/'); ?>" class="bottom-nav-item <?php echo $current_page === 'product-summary' ? 'active' : ''; ?>">
            <iconify-icon icon="solar:chart-2-linear"></iconify-icon>
            <span>Summary</span>
        </a>
        <a href="<?php echo home_url('/120-stand/profile/'); ?>" class="bottom-nav-item <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
            <iconify-icon icon="solar:user-linear"></iconify-icon>
            <span>Profile</span>
        </a>
    </nav>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <p class="loading-text">Loading...</p>
        </div>
    </div>
    
    <!-- Localized Script Data -->
    <script>
        var stand120_ajax = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('stand120_nonce'); ?>',
            plugin_url: '<?php echo STAND120_PLUGIN_URL; ?>',
            home_url: '<?php echo home_url('/120-stand/'); ?>',
            is_logged_in: <?php echo Stand120_Auth::is_logged_in() ? 'true' : 'false'; ?>,
            is_admin: <?php echo $is_admin ? 'true' : 'false'; ?>,
            is_super_admin: <?php echo Stand120_Auth::is_super_admin() ? 'true' : 'false'; ?>,
            current_user: <?php echo json_encode($current_user); ?>
        };
    </script>
    
    <!-- Plugin Scripts -->
    <script src="<?php echo STAND120_PLUGIN_URL; ?>assets/js/main.js?v=<?php echo STAND120_VERSION; ?>"></script>
    <script src="<?php echo STAND120_PLUGIN_URL; ?>assets/js/sw-register.js?v=<?php echo STAND120_VERSION; ?>"></script>
    
    <?php wp_footer(); ?>
</body>
</html>
