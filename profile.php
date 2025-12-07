<?php
require_once 'config.php';
checkLogin();

$pageTitle = 'الملف الشخصي';
$pageDescription = 'إدارة معلوماتك الشخصية وإعدادات حسابك';

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$errors = [];
$success = '';

// جلب معلومات المستخدم الحالية
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit();
}

// معالجة تحديث البيانات الشخصية
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'رمز الأمان غير صحيح';
    } else {
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        // التحقق من البيانات
        if (empty($fullName)) $errors[] = 'الاسم الكامل مطلوب';
        if (empty($email)) $errors[] = 'البريد الإلكتروني مطلوب';
        if (empty($phone)) $errors[] = 'رقم الهاتف مطلوب';
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'البريد الإلكتروني غير صحيح';
        }
        
        // التحقق من عدم وجود البريد مسبقاً (إذا تم تغييره)
        if ($email !== $user['email']) {
            $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkStmt->execute([$email, $userId]);
            if ($checkStmt->fetch()) {
                $errors[] = 'البريد الإلكتروني موجود مسبقاً';
            }
        }
        
        // تحديث البيانات
        if (empty($errors)) {
            try {
                $updateStmt = $db->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?, address = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                
                if ($updateStmt->execute([$fullName, $email, $phone, $address, $userId])) {
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['email'] = $email;
                    $success = 'تم تحديث البيانات الشخصية بنجاح';
                    
                    // إعادة جلب البيانات المحدثة
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                } else {
                    $errors[] = 'حدث خطأ أثناء تحديث البيانات';
                }
            } catch (PDOException $e) {
                $errors[] = 'حدث خطأ أثناء تحديث البيانات';
            }
        }
    }
}

// معالجة تغيير كلمة المرور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'رمز الأمان غير صحيح';
    } else {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // التحقق من البيانات
        if (empty($currentPassword)) $errors[] = 'كلمة المرور الحالية مطلوبة';
        if (empty($newPassword)) $errors[] = 'كلمة المرور الجديدة مطلوبة';
        if (empty($confirmPassword)) $errors[] = 'تأكيد كلمة المرور مطلوب';
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'كلمات المرور غير متطابقة';
        }
        
        if (strlen($newPassword) < 6) {
            $errors[] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
        }
        
        // التحقق من كلمة المرور الحالية
        if (empty($errors)) {
            if (!verifyPassword($currentPassword, $user['password'])) {
                $errors[] = 'كلمة المرور الحالية غير صحيحة';
            } else {
                try {
                    $hashedPassword = hashPassword($newPassword);
                    $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    
                    if ($updateStmt->execute([$hashedPassword, $userId])) {
                        $success = 'تم تغيير كلمة المرور بنجاح';
                    } else {
                        $errors[] = 'حدث خطأ أثناء تغيير كلمة المرور';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'حدث خطأ أثناء تغيير كلمة المرور';
                }
            }
        }
    }
}

// إحصائيات المستخدم
$statsStmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM donations WHERE donor_id = ?) as donations_count,
        (SELECT COUNT(*) FROM donation_requests WHERE requester_id = ?) as requests_count,
        (SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0) as unread_notifications
");
$statsStmt->execute([$userId, $userId, $userId]);
$stats = $statsStmt->fetch();
?>
<?php require_once 'includes/header.php'; ?>

    <!-- المحتوى الرئيسي -->
    <section class="profile-section" style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <div class="page-header mb-4">
                <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">الملف الشخصي</h1>
                <p class="text-muted">إدارة معلوماتك الشخصية وإعدادات حسابك</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-4">
                    <ul style="margin: 0; padding-right: 1rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo escape($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success mb-4"><?php echo escape($success); ?></div>
            <?php endif; ?>

            <div class="row">
                <!-- معلومات الحساب -->
                <div class="col-4 col-sm-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h3>معلومات الحساب</h3>
                        </div>
                        <div class="card-body text-center">
                            <div style="width: 100px; height: 100px; background: linear-gradient(135deg, var(--secondary-color), #2980b9); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 3rem; font-weight: bold;">
                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                            </div>
                            <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                                <?php echo escape($user['full_name']); ?>
                            </h3>
                            <p class="text-muted mb-2">@<?php echo escape($user['username']); ?></p>
                            <span class="badge badge-<?php 
                                echo $user['user_type'] === 'donor' ? 'success' : 
                                     ($user['user_type'] === 'beneficiary' ? 'primary' : 
                                     ($user['user_type'] === 'charity' ? 'warning' : 'danger'));
                            ?>">
                                <?php 
                                $typeLabels = [
                                    'donor' => 'متبرع',
                                    'beneficiary' => 'مستفيد',
                                    'charity' => 'جمعية خيرية',
                                    'admin' => 'مدير'
                                ];
                                echo $typeLabels[$user['user_type']];
                                ?>
                            </span>
                            
                            <hr style="margin: 1.5rem 0;">
                            
                            <div class="text-right" style="font-size: 0.9rem;">
                                <div class="mb-2">
                                    <strong>البريد الإلكتروني:</strong><br>
                                    <span class="text-muted"><?php echo escape($user['email']); ?></span>
                                </div>
                                <div class="mb-2">
                                    <strong>رقم الهاتف:</strong><br>
                                    <span class="text-muted"><?php echo escape($user['phone']); ?></span>
                                </div>
                                <div class="mb-2">
                                    <strong>تاريخ التسجيل:</strong><br>
                                    <span class="text-muted"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></span>
                                </div>
                                <div>
                                    <strong>الحالة:</strong><br>
                                    <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
                                        <?php 
                                        $statusLabels = [
                                            'active' => 'نشط',
                                            'inactive' => 'غير نشط',
                                            'pending' => 'قيد المراجعة'
                                        ];
                                        echo $statusLabels[$user['status']];
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- الإحصائيات -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3>إحصائياتي</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($user['user_type'] === 'donor'): ?>
                            <div class="mb-3 p-3" style="background: #e8f5e9; border-radius: var(--radius);">
                                <div style="font-size: 2rem; font-weight: bold; color: var(--success-color);">
                                    <?php echo $stats['donations_count']; ?>
                                </div>
                                <div class="text-muted">تبرعات منشورة</div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($user['user_type'] === 'beneficiary'): ?>
                            <div class="mb-3 p-3" style="background: #e3f2fd; border-radius: var(--radius);">
                                <div style="font-size: 2rem; font-weight: bold; color: var(--info-color);">
                                    <?php echo $stats['requests_count']; ?>
                                </div>
                                <div class="text-muted">طلبات مقدمة</div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="p-3" style="background: #fff3e0; border-radius: var(--radius);">
                                <div style="font-size: 2rem; font-weight: bold; color: var(--warning-color);">
                                    <?php echo $stats['unread_notifications']; ?>
                                </div>
                                <div class="text-muted">إشعارات غير مقروءة</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- تحديث البيانات -->
                <div class="col-8 col-sm-12">
                    <!-- البيانات الشخصية -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3>تحديث البيانات الشخصية</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <div class="form-group">
                                    <label for="full_name" class="form-label">الاسم الكامل</label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" 
                                           value="<?php echo escape($user['full_name']); ?>" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="email" class="form-label">البريد الإلكتروني</label>
                                            <input type="email" id="email" name="email" class="form-control" 
                                                   value="<?php echo escape($user['email']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="phone" class="form-label">رقم الهاتف</label>
                                            <input type="tel" id="phone" name="phone" class="form-control" 
                                                   value="<?php echo escape($user['phone']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="address" class="form-label">العنوان</label>
                                    <textarea id="address" name="address" class="form-control" rows="2"><?php echo escape($user['address']); ?></textarea>
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    حفظ التغييرات
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- تغيير كلمة المرور -->
                    <div class="card">
                        <div class="card-header">
                            <h3>تغيير كلمة المرور</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <div class="form-group">
                                    <label for="current_password" class="form-label">كلمة المرور الحالية</label>
                                    <input type="password" id="current_password" name="current_password" 
                                           class="form-control" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="new_password" class="form-label">كلمة المرور الجديدة</label>
                                            <input type="password" id="new_password" name="new_password" 
                                                   class="form-control" minlength="6" required>
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
                                
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    تغيير كلمة المرور
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
