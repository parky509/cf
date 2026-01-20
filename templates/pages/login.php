<?php
/**
 * Login Page Template - Simple Form POST Version
 * Uses WordPress admin-post.php for reliable form handling
 * 
 * @package Chinemerem_Foods_Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

// Redirect if already logged in
if (is_user_logged_in()) {
    wp_redirect(home_url('/home/'));
    exit;
}

// Get settings
$business_name = get_option('cfi_business_name', 'Chinemerem Foods');
$login_logo = get_option('cfi_login_logo_image', '');
$login_bg = get_option('cfi_login_background_image', '');

// If no custom login logo, try to get WordPress site logo
if (empty($login_logo)) {
    $custom_logo_id = get_theme_mod('custom_logo');
    if ($custom_logo_id) {
        $login_logo = wp_get_attachment_image_url($custom_logo_id, 'medium');
    }
}

// If still no logo, try site icon
if (empty($login_logo)) {
    $site_icon_id = get_option('site_icon');
    if ($site_icon_id) {
        $login_logo = wp_get_attachment_image_url($site_icon_id, 'medium');
    }
}

// Get error/success messages from URL
$error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
$logout = isset($_GET['logout']) ? true : false;

$error_messages = array(
    'empty' => 'Please enter username and password.',
    'invalid' => 'Invalid username or password.',
    'access' => 'You do not have access to this system.'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo esc_html($business_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            <?php if ($login_bg): ?>
            background: url('<?php echo esc_url($login_bg); ?>') center/cover no-repeat;
            <?php else: ?>
            background: linear-gradient(135deg, #001943 0%, #002960 100%);
            <?php endif; ?>
        }
        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.3);
            z-index: 1;
        }
        .login-box {
            position: relative;
            z-index: 2;
            background: #fff;
            padding: 40px;
            border-radius: 16px;
            width: 100%;
            max-width: 400px;
            margin: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo-area {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-area img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 12px;
        }
        .logo-area .icon-logo {
            width: 80px;
            height: 80px;
            background: #f0f4f8;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        .logo-area .icon-logo i {
            font-size: 36px;
            color: #001943;
        }
        .logo-area h1 {
            font-size: 22px;
            color: #001943;
            margin-top: 16px;
        }
        .logo-area p {
            color: #64748b;
            font-size: 14px;
            margin-top: 4px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .input-wrap {
            display: flex;
            align-items: center;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            transition: all 0.2s;
        }
        .input-wrap:focus-within {
            border-color: #001943;
            background: #fff;
        }
        .input-wrap .icon {
            padding: 0 16px;
            color: #94a3b8;
        }
        .input-wrap:focus-within .icon {
            color: #001943;
        }
        .input-wrap input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 14px 16px 14px 0;
            font-size: 15px;
            outline: none;
            color: #1e293b;
        }
        .input-wrap input::placeholder {
            color: #94a3b8;
        }
        .input-wrap .toggle-pwd {
            padding: 0 16px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
        }
        .input-wrap .toggle-pwd:hover {
            color: #001943;
        }
        .remember-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
        }
        .remember-row input {
            width: 18px;
            height: 18px;
            accent-color: #001943;
        }
        .remember-row label {
            color: #64748b;
            font-size: 14px;
            cursor: pointer;
        }
        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #001943, #002960);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,25,67,0.3);
        }
        .footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            font-size: 12px;
            color: #94a3b8;
            margin: 4px 0;
        }
        .footer a {
            color: #001943;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="overlay"></div>
    <div class="login-box">
        <div class="logo-area">
            <?php if ($login_logo): ?>
                <img src="<?php echo esc_url($login_logo); ?>" alt="Logo">
            <?php else: ?>
                <div class="icon-logo"><i class="fas fa-wheat-awn"></i></div>
            <?php endif; ?>
            <h1><?php echo esc_html($business_name); ?></h1>
            <p>Inventory Management System</p>
        </div>
        
        <?php if ($error && isset($error_messages[$error])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo esc_html($error_messages[$error]); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($logout): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                You have been logged out successfully.
            </div>
        <?php endif; ?>
        
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST">
            <input type="hidden" name="action" value="cfi_do_login">
            
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrap">
                    <span class="icon"><i class="fas fa-user"></i></span>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required autofocus>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <span class="icon"><i class="fas fa-lock"></i></span>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    <button type="button" class="toggle-pwd" onclick="togglePassword()">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            
            <div class="remember-row">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember">Remember me</label>
            </div>
            
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i>
                <span>Login</span>
            </button>
        </form>
        
        <div class="footer">
            <p>&copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html($business_name); ?></p>
            <p>Designed by <a href="https://bendlestech.com" target="_blank">BendlessTech</a></p>
        </div>
    </div>
    
    <script>
    function togglePassword() {
        var pwd = document.getElementById('password');
        var icon = document.getElementById('eyeIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            pwd.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    </script>
</body>
</html>
<?php exit; ?>
