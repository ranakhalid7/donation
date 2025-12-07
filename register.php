<?php
require_once 'config.php';

$pageTitle = 'إنشاء حساب جديد';
$pageDescription = 'انضم إلى منصة التبرعات وابدأ رحلتك في العطاء والمساعدة';

// إعادة توجيه إذا كان المستخدم مسجل دخول بالفعل
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$errors = [];
$success = '';
$selectedType = $_GET['type'] ?? 'donor';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $fullName = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $userType = $_POST['user_type'];
    
    // التحقق من البيانات
    if (empty($username)) $errors[] = 'اسم المستخدم مطلوب';
    if (empty($email)) $errors[] = 'البريد الإلكتروني مطلوب';
    if (empty($password)) $errors[] = 'كلمة المرور مطلوبة';
    if (empty($fullName)) $errors[] = 'الاسم الكامل مطلوب';
    if (empty($phone)) $errors[] = 'رقم الهاتف مطلوب';
    if (empty($userType)) $errors[] = 'نوع المستخدم مطلوب';
    
    if ($password !== $confirmPassword) {
        $errors[] = 'كلمات المرور غير متطابقة';
    }
    
    if (strlen($password) < 6) {
        $errors[] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صحيح';
    }
    
    if (!preg_match('/^[0-9+\-\s()]{10,}$/', $phone)) {
        $errors[] = 'رقم الهاتف غير صحيح';
    }
    
    if (!in_array($userType, ['donor', 'beneficiary', 'charity'])) {
        $errors[] = 'نوع المستخدم غير صحيح';
    }
    
    // التحقق من عدم وجود اسم المستخدم أو البريد مسبقاً
    if (empty($errors)) {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'اسم المستخدم أو البريد الإلكتروني موجود مسبقاً';
        }
    }
    
    // إنشاء الحساب
    if (empty($errors)) {
        $hashedPassword = hashPassword($password);
        $status = ($userType === 'charity') ? 'pending' : 'active';
        
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, full_name, phone, address, user_type, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$username, $email, $hashedPassword, $fullName, $phone, $address, $userType, $status])) {
            $userId = $db->lastInsertId();
            
            // إذا كان نوع المستخدم جمعية خيرية، إنشاء سجل في جدول الجمعيات
            if ($userType === 'charity') {
                $charityName = $_POST['charity_name'] ?? $fullName;
                $licenseNumber = $_POST['license_number'] ?? '';
                $description = $_POST['description'] ?? '';
                $website = $_POST['website'] ?? '';
                
                $charityStmt = $db->prepare("
                    INSERT INTO charities (user_id, charity_name, license_number, description, website) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $charityStmt->execute([$userId, $charityName, $licenseNumber, $description, $website]);
                
                $success = 'تم التسجيل بنجاح! حسابك قيد المراجعة وسيتم تفعيله قريباً.';
            } else {
                // تسجيل الدخول التلقائي
                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['full_name'] = $fullName;
                $_SESSION['user_type'] = $userType;
                
                header('Location: dashboard.php');
                exit();
            }
        } else {
            $errors[] = 'حدث خطأ أثناء التسجيل، يرجى المحاولة مرة أخرى';
        }
    }
}
?>

<?php require_once 'includes/header.php'; ?>

    <style>
        .register-section {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef3 100%);
            padding: 2rem 0;
            min-height: calc(100vh - var(--header-height));
        }

        .register-section .card {
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            border: none;
        }

        .register-section .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            color: white;
            padding: 2rem;
        }

        .register-section .card-header h2 {
            color: white;
            margin-bottom: 0.5rem;
        }

        .register-section .card-header p {
            color: rgba(255,255,255,0.9);
            margin: 0;
        }

        .register-section .card-body {
            padding: 2.5rem;
        }

        .register-section .card-footer {
            background: #f8f9fa;
            padding: 1.5rem;
            border-top: 2px solid var(--border-color);
        }

        .register-section .form-control {
            height: 50px;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            padding: 0 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .register-section textarea.form-control {
            height: auto;
            min-height: 100px;
        }

        .register-section .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(85,107,47,0.1);
        }

        .register-section .btn-primary {
            height: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 10px;
            margin-top: 1rem;
        }

        .user-type-selector .btn {
            height: 50px;
            font-weight: 600;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .register-section {
                padding: 1rem 0 !important;
                min-height: auto !important;
            }

            .register-section .container {
                padding: 0 0.5rem;
            }

            .register-section .col-8 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .register-section .card {
                margin: 0;
                border-radius: 12px;
            }

            .register-section .card-header {
                padding: 1.5rem 1rem;
            }

            .register-section .card-header h2 {
                font-size: 1.5rem;
            }

            .register-section .card-body {
                padding: 1.5rem 1rem;
            }

            .register-section .card-footer {
                padding: 1rem;
            }

            .register-section .form-group {
                margin-bottom: 1rem;
            }

            .user-type-selector .col-4 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
    </style>

    <!-- صفحة التسجيل -->
    <section class="register-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-8">
                    <div class="card">
                        <div class="card-header text-center">
                            <h2>إنشاء حساب جديد</h2>
                            <p>انضم إلى مجتمع الخير والعطاء</p>
                        </div>
                        
                        <div class="card-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul style="margin: 0; padding-right: 1rem;">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo escape($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?php echo escape($success); ?></div>
                            <?php endif; ?>
                            
                            <!-- أزرار اختيار نوع المستخدم -->
                            <div  class="user-type-selector mb-4">
                                <div class="row">
                                    <!-- <div class="col-4 col-sm-12 mb-2">
                                        <a style="background-color:cornflowerblue !important;" href="register.php?type=donor" 
                                           class="btn w-100 <?php echo $selectedType === 'donor' ? 'btn-primary' : 'btn-outline'; ?>">
                                            متبرع
                                        </a>
                                    </div> -->
                                    <div  class="col-4 col-sm-12 mb-2">
                                        <a style="background-color:cornflowerblue !important;" active href="register.php?type=beneficiary" 
                                           class="btn w-100 <?php echo $selectedType === 'beneficiary' ? 'btn-primary' : 'btn-outline'; ?>">
                                            مستفيد
                                        </a>
                                    </div>
                                    <div class="col-4 col-sm-12 mb-2">
                                        <a style="background-color:cornflowerblue !important;" href="register.php?type=charity" 
                                           class="btn w-100 <?php echo $selectedType === 'charity' ? 'btn-primary' : 'btn-outline'; ?>">
                                            جمعية خيرية
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" data-validate="true">
                                <input type="hidden" name="user_type" value="<?php echo escape($selectedType); ?>">
                                
                                <div class="row">
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="username" class="form-label">اسم المستخدم</label>
                                            <input type="text" id="username" name="username" class="form-control" 
                                                   value="<?php echo escape($_POST['username'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="email" class="form-label">البريد الإلكتروني</label>
                                            <input type="email" id="email" name="email" class="form-control" 
                                                   value="<?php echo escape($_POST['email'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="password" class="form-label">كلمة المرور</label>
                                            <input type="password" id="password" name="password" class="form-control" 
                                                   minlength="6" required>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                                            <input type="password" id="confirm_password" name="confirm_password" 
                                                   class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="full_name" class="form-label">
                                        <?php echo $selectedType === 'charity' ? 'اسم الجمعية' : 'الاسم الكامل'; ?>
                                    </label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" 
                                           value="<?php echo escape($_POST['full_name'] ?? ''); ?>" required>
                                </div>
                                
                                <?php if ($selectedType === 'charity'): ?>
                                    <div class="form-group">
                                        <label for="charity_name" class="form-label">اسم الجمعية الخيرية</label>
                                        <input type="text" id="charity_name" name="charity_name" class="form-control" 
                                               value="<?php echo escape($_POST['charity_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="license_number" class="form-label">رقم الترخيص</label>
                                        <input type="text" id="license_number" name="license_number" class="form-control" 
                                               value="<?php echo escape($_POST['license_number'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="website" class="form-label">الموقع الإلكتروني (اختياري)</label>
                                        <input type="url" id="website" name="website" class="form-control" 
                                               value="<?php echo escape($_POST['website'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="description" class="form-label">وصف الجمعية</label>
                                        <textarea id="description" name="description" class="form-control" 
                                                  rows="3"><?php echo escape($_POST['description'] ?? ''); ?></textarea>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="phone" class="form-label">رقم الهاتف</label>
                                            <input type="tel" id="phone" name="phone" class="form-control" 
                                                   value="<?php echo escape($_POST['phone'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="address" class="form-label">العنوان</label>
                                            <input type="text" id="address" name="address" class="form-control" 
                                                   value="<?php echo escape($_POST['address'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" id="terms" name="terms" class="form-check-input" required>
                                        <label for="terms" class="form-check-label">
                                            أوافق على <a href="terms.php" target="_blank">شروط الاستخدام</a> 
                                            و <a href="privacy.php" target="_blank">سياسة الخصوصية</a>
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">إنشاء الحساب</button>
                            </form>
                        </div>
                        
                        <div class="card-footer text-center">
                            <p class="mb-0">لديك حساب بالفعل؟ 
                                <a href="login.php" class="text-primary">سجل الدخول</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
