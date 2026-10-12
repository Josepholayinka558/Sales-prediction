<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';

if (auth()->check()) redirect('/dashboard.php');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $_SESSION['_old'] = $_POST;
    $action = $_POST['action'] ?? 'login';
    if (!Csrf::verifyRequest()) {
        Session::flash('error', 'Invalid request token. Please refresh.');
        redirect('/login.php');
    }
    if ($action === 'forgot') {
        $email = clean_input($_POST['reset_email'] ?? '');
        if (!validate_email($email)) {
            Session::flash('error', 'Please enter a valid email.');
            redirect('/login.php');
        }
        auth()->sendResetLink($email);
        Session::flash('success', 'If the email exists, a reset link would be sent. Use demo account for testing.');
        redirect('/login.php');
    }
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    $errors = [];
    if (!$email) $errors['email'] = 'Email or username required.';
    if (empty($password)) $errors['password'] = 'Password required.';
    if (!empty($errors)) {
        $_SESSION['_errors'] = $errors;
        redirect('/login.php');
    }
    if (auth()->attempt($email, $password, $remember)) {
        Session::flash('success', 'Welcome back! You have signed in successfully.');
        redirect('/dashboard.php');
    }
    Session::flash('error', 'Invalid credentials. Try demo accounts below.');
    redirect('/login.php');
}

$email = old('email');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body {
            min-height: 100vh; margin: 0;
            background: #f8fafc;
            display: flex; align-items: center; justify-content: center; padding: 1rem;
        }
        .auth-container {
            width: 100%; max-width: 1200px;
            display: grid; grid-template-columns: 1fr 1fr;
            background: #fff; border-radius: 24px; overflow: hidden;
            box-shadow: 0 25px 70px rgba(15,23,42,0.15);
            min-height: 680px;
        }
        .auth-brand {
            position: relative;
            overflow: hidden;
            background: #0f172a;
        }
        .auth-brand .bg-image {
            position: absolute; inset: 0;
            background-image: url("https://coresg-normal.trae.ai/api/ide/v1/text_to_image?prompt=modern%20corporate%20office%20interior%20corridor%20with%20glass%20meeting%20rooms%20dark%20elegant%20pendant%20lights%20wooden%20ceiling%20professional%20business%20atmosphere&image_size=portrait_4_3");
            background-size: cover;
            background-position: center;
            filter: saturate(1.1);
        }
        .auth-brand .overlay {
            position: absolute; inset: 0;
            background: linear-gradient(180deg, rgba(15,23,42,0.25) 0%, rgba(15,23,42,0.7) 100%);
        }
        .auth-brand-content {
            position: relative; z-index: 2;
            height: 100%;
            padding: 3rem 3rem;
            display: flex; flex-direction: column; justify-content: space-between;
            color: #fff;
        }
        .brand-logo { display: flex; align-items: center; gap: 0.875rem; }
        .brand-logo-box {
            width: 48px; height: 48px; border-radius: 12px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
        }
        .brand-logo h1 { font-size: 1.5rem; font-weight: 800; margin: 0; letter-spacing: -0.02em; }
        .brand-tagline { margin-top: auto; padding-bottom: 2rem; }
        .trust-badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.375rem 0.875rem;
            border-radius: 100px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.18);
            font-size: 0.75rem; font-weight: 500;
            margin-bottom: 1.25rem;
            letter-spacing: 0.02em;
        }
        .trust-badge .pulse {
            width: 7px; height: 7px; border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 4px rgba(34,197,94,0.25);
        }
        .brand-tagline h2 {
            font-size: 2.5rem; font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
        }
        .brand-tagline h2 em {
            font-style: normal;
            background: linear-gradient(135deg, #fbbf24 0%, #f97316 100%);
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .brand-tagline p {
            color: rgba(255,255,255,0.78);
            line-height: 1.7; font-size: 0.95rem;
            max-width: 420px;
            font-weight: 400;
        }
        .features-list { list-style: none; padding: 0; margin: 1.75rem 0 0; display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem 1rem; }
        .features-list li {
            display: flex; align-items: center; gap: 0.65rem;
            color: rgba(255,255,255,0.9); font-size: 0.8rem; font-weight: 500;
        }
        .features-list li i {
            width: 22px; height: 22px; border-radius: 6px;
            background: rgba(251,191,36,0.18); color: #fbbf24;
            display: flex; align-items: center; justify-content: center; font-size: 0.65rem;
            flex-shrink: 0;
        }
        .brand-footer {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
            font-weight: 500;
        }
        .auth-form-wrapper {
            padding: 3rem 3.5rem;
            display: flex; flex-direction: column; justify-content: center;
            background: #fff;
        }
        .welcome-chip {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.4rem 0.85rem;
            border-radius: 100px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.78rem; font-weight: 600;
            margin-bottom: 1.5rem;
            width: fit-content;
        }
        .welcome-chip i { font-size: 0.75rem; }
        .auth-header { margin-bottom: 2rem; }
        .auth-header h3 {
            font-weight: 800;
            font-size: 2rem;
            margin: 0 0 0.5rem;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .auth-header p { color: #64748b; margin: 0; font-size: 0.92rem; }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #0f172a; margin-bottom: 0.45rem; display: block; }
        .form-control {
            border-radius: 12px;
            padding: 0.85rem 1rem 0.85rem 2.8rem;
            font-size: 0.92rem;
            border: 1.5px solid #e2e8f0;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .form-control:hover { background: #fff; border-color: #cbd5e1; }
        .form-control:focus {
            background: #fff;
            border-color: #f97316;
            box-shadow: 0 0 0 4px rgba(249,115,22,0.1);
            outline: none;
        }
        .input-group-icon { position: relative; }
        .input-group-icon > i {
            position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: 1rem; z-index: 5;
            width: 18px; text-align: center;
            transition: color 0.2s;
        }
        .form-control:focus ~ i, .input-group-icon:focus-within > i { color: #f97316; }
        .password-toggle {
            position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #94a3b8; cursor: pointer; z-index: 5; padding: 0.25rem;
            transition: color 0.2s;
        }
        .password-toggle:hover { color: #f97316; }
        .btn-primary {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            border: none; padding: 0.95rem 1.5rem; border-radius: 12px; font-weight: 700;
            font-size: 0.95rem; transition: all 0.3s; letter-spacing: 0.01em;
            box-shadow: 0 4px 14px rgba(249,115,22,0.3);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(249,115,22,0.4);
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
        }
        .form-check-input {
            border-color: #cbd5e1;
            border-radius: 6px;
            cursor: pointer;
        }
        .form-check-input:checked {
            background-color: #f97316; border-color: #f97316;
            box-shadow: 0 0 0 4px rgba(249,115,22,0.1);
        }
        .form-check-label {
            font-size: 0.85rem; color: #475569; cursor: pointer;
        }
        .alert-sm {
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            border: none;
            display: flex; align-items: center; gap: 0.6rem;
        }
        .divider {
            display: flex; align-items: center; gap: 1rem; margin: 1.75rem 0 1.25rem;
            color: #94a3b8; font-size: 0.72rem;
            text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .demo-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.6rem; }
        .demo-card {
            padding: 0.9rem 0.6rem; border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            cursor: pointer;
            background: #fff; transition: all 0.2s; text-align: center;
        }
        .demo-card:hover {
            border-color: #f97316;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(249,115,22,0.12);
            background: #fff7ed;
        }
        .demo-card i { color: #f97316; margin-bottom: 0.35rem; font-size: 1.05rem; }
        .demo-card .role { font-size: 0.78rem; font-weight: 700; color: #0f172a; }
        .demo-card .hint { font-size: 0.68rem; color: #64748b; margin-top: 0.15rem; }
        .text-link {
            color: #f97316;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .text-link:hover { text-decoration: underline; color: #ea580c; }
        @media (max-width: 900px) {
            body { padding: 0; }
            .auth-container { grid-template-columns: 1fr; min-height: 100vh; border-radius: 0; }
            .auth-brand { min-height: 240px; }
            .auth-brand-content { padding: 2rem; }
            .auth-form-wrapper { padding: 2rem 1.75rem; }
            .brand-tagline h2 { font-size: 1.75rem; }
            .features-list { grid-template-columns: 1fr; gap: 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-brand">
            <div class="bg-image"></div>
            <div class="overlay"></div>
            <div class="auth-brand-content">
                <div>
                    <div class="brand-logo">
                        <div class="brand-logo-box">
                            <i class="fas fa-chart-simple"></i>
                        </div>
                        <h1>SalesForecast BI</h1>
                    </div>
                </div>
                <div class="brand-tagline">
                    <div class="trust-badge">
                        <span class="pulse"></span>
                        <span>Trusted by sales teams worldwide</span>
                    </div>
                    <h2>Accelerate growth with <em>data-driven</em> decisions.</h2>
                    <p>Combine historical analytics, predictive forecasting, and automated reporting in one unified platform built for modern teams.</p>
                    <ul class="features-list">
                        <li><i class="fas fa-check"></i> Sales Analytics</li>
                        <li><i class="fas fa-check"></i> Forecasting AI</li>
                        <li><i class="fas fa-check"></i> Custom Reports</li>
                        <li><i class="fas fa-check"></i> Live KPIs</li>
                    </ul>
                </div>
                <div class="brand-footer">
                    &copy; <?= date('Y') ?> SalesForecast BI. Built for business intelligence.
                </div>
            </div>
        </div>
        <div class="auth-form-wrapper">
            <div class="welcome-chip">
                <i class="fas fa-bolt"></i>
                Sign in to continue
            </div>
            <div class="auth-header">
                <h3>Welcome Back.</h3>
                <p>Enter your credentials to access your workspace.</p>
            </div>

            <?php if (Session::hasFlash('error')): ?>
                <div class="alert alert-danger alert-sm mb-4"><i class="fas fa-circle-exclamation"></i><?= e(Session::flash('error')) ?></div>
            <?php endif; ?>
            <?php if (Session::hasFlash('success')): ?>
                <div class="alert alert-success alert-sm mb-4"><i class="fas fa-circle-check"></i><?= e(Session::flash('success')) ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <?= Csrf::field() ?>
                <div class="mb-4">
                    <label class="form-label">Email or Username</label>
                    <div class="input-group-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="email" class="form-control" placeholder="admin@salesforecast.local" required value="<?= e($email) ?>">
                    </div>
                    <?php if ($e = errors('email')): ?><div class="text-danger small mt-1"><?= e($e) ?></div><?php endif; ?>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="pwd" class="form-control" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePwd()" aria-label="Toggle password visibility"><i class="fas fa-eye" id="pIc"></i></button>
                    </div>
                    <?php if ($e = errors('password')): ?><div class="text-danger small mt-1"><?= e($e) ?></div><?php endif; ?>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="rmb">
                        <label class="form-check-label" for="rmb">Remember me</label>
                    </div>
                    <a href="#" class="text-link" data-bs-toggle="modal" data-bs-target="#forgotModal">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-4"><i class="fas fa-right-to-bracket me-2"></i>Sign In</button>
            </form>

            <div class="divider">Quick sign in with demo accounts</div>
            <div class="demo-cards">
                <div class="demo-card" onclick="fillCreds('admin')">
                    <i class="fas fa-crown d-block"></i>
                    <div class="role">Admin</div>
                    <div class="hint">password</div>
                </div>
                <div class="demo-card" onclick="fillCreds('analyst')">
                    <i class="fas fa-chart-pie d-block"></i>
                    <div class="role">Analyst</div>
                    <div class="hint">password</div>
                </div>
                <div class="demo-card" onclick="fillCreds('manager')">
                    <i class="fas fa-user-tie d-block"></i>
                    <div class="role">Manager</div>
                    <div class="hint">password</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="forgotModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-key text-primary me-2"></i>Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Enter your email and we'll send you a reset link.</p>
                    <form method="POST" action="" id="forgotForm">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="forgot">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="reset_email" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePwd() {
            const p = document.getElementById('pwd'), i = document.getElementById('pIc');
            if (p.type === 'password') { p.type = 'text'; i.className = 'fas fa-eye-slash'; } else { p.type = 'password'; i.className = 'fas fa-eye'; }
        }
        function fillCreds(r) {
            const em = r + '@salesforecast.local';
            document.querySelector('input[name="email"]').value = em;
            document.getElementById('pwd').value = 'password';
        }
    </script>
</body>
</html>
