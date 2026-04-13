<?php
/**
 * Login Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Redirect if already logged in
if (Stand120_Auth::is_logged_in()) {
    wp_redirect(home_url('/120-stand/'));
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#8B0000">
    <title>Login - 120 Stand Inventory</title>
    
    <!-- Prevent browser from caching pages -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Preconnect to fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Plugin Styles -->
    <link rel="stylesheet" href="<?php echo STAND120_PLUGIN_URL; ?>assets/css/style.css?v=<?php echo STAND120_VERSION; ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo STAND120_PLUGIN_URL; ?>assets/images/logo.png">
    
    <?php wp_head(); ?>
</head>
<body class="stand120-app stand120-login-page">
    <div class="login-container">
        <div class="login-card glass-card">
            <img src="<?php echo STAND120_PLUGIN_URL; ?>assets/images/logo.png" alt="120 Stand" class="login-logo">
            <h1 class="login-title">120 Stand</h1>
            <p class="login-subtitle">Inventory Management System</p>
            
            <form id="loginForm" class="login-form">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                </div>
                
                <button type="submit" id="loginBtn" class="btn btn-primary login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <p class="loading-text">Loading...</p>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    
    <!-- Localized Script Data -->
    <script>
        var stand120_ajax = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('stand120_nonce'); ?>',
            plugin_url: '<?php echo STAND120_PLUGIN_URL; ?>',
            home_url: '<?php echo home_url('/120-stand/'); ?>',
            is_logged_in: false,
            is_admin: false,
            current_user: null
        };
    </script>
    
    <!-- Plugin Scripts -->
    <script src="<?php echo STAND120_PLUGIN_URL; ?>assets/js/main.js?v=<?php echo STAND120_VERSION; ?>"></script>
    
    <script>
    (function($) {
        $(document).ready(function() {
            if (typeof Login !== 'undefined') {
                Login.init();
            }
        });
    })(jQuery);
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>