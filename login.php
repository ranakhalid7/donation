<?php
require_once 'config.php';

$pageTitle = 'تسجيل الدخول';
$pageDescription = 'تسجيل الدخول إلى حسابك للوصول إلى لوحة التحكم وإدارة تبرعاتك';

// إعادة توجيه إذا كان المستخدم مسجل دخول بالفعل
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'يرجى ملء جميع الحقول المطلوبة';
    } else {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id, username, email, password, full_name, user_type, status FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password'])) {
            // تسجيل الدخول بنجاح
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_type'] = $user['user_type'];
            
            // تذكر المستخدم
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                
                // حفظ الرمز في قاعدة البيانات (يمكن إضافة جدول للرموز المميزة)
            }
            
            // إعادة التوجيه إلى لوحة التحكم
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'بيانات الدخول غير صحيحة';
        }
    }
}
?>

<?php require_once 'includes/header.php'; ?>

    <style>
        .login-section {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef3 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-section .card {
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border: none;
        }

        .login-section .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            color: white;
            padding: 2rem;
        }

        .login-section .card-header h2 {
            color: white;
            margin-bottom: 0.5rem;
        }

        .login-section .card-header p {
            color: rgba(255,255,255,0.9);
            margin: 0;
        }

        .login-section .card-body {
            padding: 2.5rem;
        }

        .login-section .card-footer {
            background: #f8f9fa;
            padding: 1.5rem;
            border-top: 2px solid var(--border-color);
        }

        .login-section .form-control {
            height: 50px;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            padding: 0 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .login-section .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(85,107,47,0.1);
        }

        .login-section .btn-primary {
            height: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 10px;
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            .login-section {
                padding: 1rem 0 !important;
                min-height: calc(100vh - 60px) !important;
            }

            .login-section .container {
                padding: 0 0.5rem;
            }

            .login-section .col-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .login-section .card {
                margin: 0;
                border-radius: 0;
                min-height: calc(100vh - 60px);
            }

            .login-section .card-header {
                padding: 1.5rem 1rem;
            }

            .login-section .card-header h2 {
                font-size: 1.5rem;
            }

            .login-section .card-body {
                padding: 1.5rem 1rem;
                flex: 1;
            }

            .login-section .card-footer {
                padding: 1rem;
            }
        }
    </style>

    <!-- صفحة تسجيل الدخول -->
    <section class="login-section" style="padding: 2rem 0; min-height: calc(100vh - var(--header-height));">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-6">
                    <div class="card">
                        <div class="card-header text-center">
                            <h2>تسجيل الدخول</h2>
                            <p>أدخل بياناتك للوصول إلى حسابك</p>
                        </div>
                        
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo escape($error); ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" data-validate="true">
                                <div class="form-group">
                                    <label for="email" class="form-label">البريد الإلكتروني</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?php echo escape($_POST['email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password" class="form-label">كلمة المرور</label>
                                    <input type="password" id="password" name="password" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" id="remember" name="remember" class="form-check-input">
                                        <label for="remember" class="form-check-label">تذكرني</label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">تسجيل الدخول</button>
                            </form>
                            
                            <div class="text-center mt-3">
                                <a href="forgot-password.php" class="text-muted">نسيت كلمة المرور؟</a>
                            </div>
                        </div>
                        
                        <div class="card-footer text-center">
                            <p class="mb-0">ليس لديك حساب؟ 
                                <a href="register.php" class="text-primary">سجل الآن</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
