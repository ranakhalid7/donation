<?php
require_once 'config.php';

$pageTitle = 'ملف الجمعية';
$pageDescription = 'إدارة معلومات وإعدادات الجمعية الخيرية';

checkLogin();
checkUserType(['charity']);

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$success = '';
$errors = [];

// جلب بيانات الجمعية
$charityStmt = $db->prepare("
    SELECT c.*, u.full_name, u.email, u.phone, u.address as user_address 
    FROM charities c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.user_id = ?
");
$charityStmt->execute([$userId]);
$charity = $charityStmt->fetch();

// إذا لم تكن الجمعية موجودة، إنشاؤها
if (!$charity) {
    $createCharityStmt = $db->prepare("
        INSERT INTO charities (user_id, name, description, address, license_number, verified, created_at) 
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    $createCharityStmt->execute([
        $userId, 
        $_SESSION['full_name'], 
        'وصف الجمعية', 
        '', 
        ''
    ]);
    
    // إعادة جلب البيانات
    $charityStmt->execute([$userId]);
    $charity = $charityStmt->fetch();
}

// معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $address = trim($_POST['address']);
    $licenseNumber = trim($_POST['license_number']);
    $website = trim($_POST['website']);
    
    // التحقق من البيانات
    if (empty($name)) $errors[] = 'اسم الجمعية مطلوب';
    if (empty($description)) $errors[] = 'وصف الجمعية مطلوب';
    if (empty($address)) $errors[] = 'عنوان الجمعية مطلوب';
    
    if (strlen($description) < 50) {
        $errors[] = 'وصف الجمعية يجب أن يكون 50 حرف على الأقل';
    }
    
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = 'رابط الموقع غير صحيح';
    }
    
    // تحديث البيانات
    if (empty($errors)) {
        $updateStmt = $db->prepare("
            UPDATE charities 
            SET name = ?, description = ?, address = ?, license_number = ?, website = ?, updated_at = NOW()
            WHERE user_id = ?
        ");
        
        if ($updateStmt->execute([$name, $description, $address, $licenseNumber, $website, $userId])) {
            $success = 'تم تحديث بيانات الجمعية بنجاح';
            
            // إعادة جلب البيانات المحدثة
            $charityStmt->execute([$userId]);
            $charity = $charityStmt->fetch();
        } else {
            $errors[] = 'حدث خطأ أثناء التحديث';
        }
    }
}

// جلب إحصائيات الجمعية
$statsStmt = $db->prepare("
    SELECT 
        COUNT(d.id) as total_donations,
        COUNT(CASE WHEN d.status = 'completed' THEN 1 END) as completed_donations,
        (SELECT COUNT(*) FROM donation_requests dr 
         JOIN donations d2 ON dr.donation_id = d2.id 
         WHERE d2.charity_id = ?) as total_requests
    FROM donations d 
    WHERE d.charity_id = ?
");
$statsStmt->execute([$charity['id'], $charity['id']]);
$stats = $statsStmt->fetch();
?>

<?php require_once 'includes/header.php'; ?>

    <!-- صفحة ملف الجمعية -->
    <section class="charity-profile" style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <!-- عنوان الصفحة -->
            <div class="page-header mb-4">
                <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">ملف الجمعية</h1>
                <p class="text-muted">إدارة معلومات وإعدادات الجمعية الخيرية</p>
            </div>
            
            <div class="row">
                <!-- نموذج تحديث البيانات -->
                <div class="col-8 col-sm-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h3>معلومات الجمعية</h3>
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
                            
                            <form method="POST" data-validate="true">
                                <div class="form-group">
                                    <label for="name" class="form-label">اسم الجمعية</label>
                                    <input type="text" id="name" name="name" class="form-control" 
                                           value="<?php echo escape($charity['name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description" class="form-label">وصف الجمعية ونشاطها</label>
                                    <textarea id="description" name="description" class="form-control" 
                                              rows="5" required minlength="50"><?php echo escape($charity['description']); ?></textarea>
                                    <small class="text-muted">الحد الأدنى 50 حرف</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="address" class="form-label">عنوان الجمعية</label>
                                    <textarea id="address" name="address" class="form-control" 
                                              rows="3" required><?php echo escape($charity['address']); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="license_number" class="form-label">رقم الترخيص</label>
                                            <input type="text" id="license_number" name="license_number" class="form-control" 
                                                   value="<?php echo escape($charity['license_number']); ?>">
                                            <small class="text-muted">رقم ترخيص الجمعية الرسمي</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="website" class="form-label">موقع الجمعية</label>
                                            <input type="url" id="website" name="website" class="form-control" 
                                                   value="<?php echo escape($charity['website']); ?>" 
                                                   placeholder="https://example.com">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary">
                                        تحديث معلومات الجمعية
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- معلومات المسؤول -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h3>معلومات المسؤول</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6 col-sm-12 mb-3">
                                    <strong>الاسم الكامل:</strong><br>
                                    <?php echo escape($charity['full_name']); ?>
                                </div>
                                <div class="col-6 col-sm-12 mb-3">
                                    <strong>البريد الإلكتروني:</strong><br>
                                    <?php echo escape($charity['email']); ?>
                                </div>
                                <div class="col-6 col-sm-12 mb-3">
                                    <strong>رقم الهاتف:</strong><br>
                                    <?php echo escape($charity['phone']); ?>
                                </div>
                                <div class="col-6 col-sm-12 mb-3">
                                    <strong>العنوان الشخصي:</strong><br>
                                    <?php echo escape($charity['user_address']); ?>
                                </div>
                            </div>
                            <p class="text-muted mb-0">
                                <small>لتحديث هذه المعلومات، يرجى زيارة <a href="profile.php">الملف الشخصي</a></small>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- الشريط الجانبي -->
                <div class="col-4 col-sm-12">
                    <!-- حالة الجمعية -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3>حالة الجمعية</h3>
                        </div>
                        <div class="card-body text-center">
                            <div class="charity-logo mb-3" style="width: 100px; height: 100px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; color: white; font-size: 2rem; font-weight: bold;">
                                <?php echo strtoupper(substr($charity['name'], 0, 2)); ?>
                            </div>
                            <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                                <?php echo escape($charity['name']); ?>
                            </h4>
                            
                            <div class="verification-status mb-3">
                                <?php if ($charity['verified']): ?>
                                    <span class="badge badge-success" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                        ✓ جمعية معتمدة
                                    </span>
                                    <p class="text-success mt-2">تم التحقق من صحة بيانات الجمعية</p>
                                <?php else: ?>
                                    <span class="badge badge-warning" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                        ⏳ قيد المراجعة
                                    </span>
                                    <p class="text-muted mt-2">سيتم مراجعة بيانات الجمعية قريباً</p>
                                <?php endif; ?>
                            </div>
                            
                            <small class="text-muted">
                                عضو منذ: <?php echo date('Y-m-d', strtotime($charity['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                    
                    <!-- إحصائيات النشاط -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3>إحصائيات النشاط</h3>
                        </div>
                        <div class="card-body">
                            <div class="stat-item mb-3" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--light-color); border-radius: var(--border-radius);">
                                <span>التبرعات المستلمة</span>
                                <strong style="color: var(--primary-color); font-size: 1.2rem;">
                                    <?php echo $stats['total_donations']; ?>
                                </strong>
                            </div>
                            <div class="stat-item mb-3" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--light-color); border-radius: var(--border-radius);">
                                <span>التبرعات المكتملة</span>
                                <strong style="color: var(--success-color); font-size: 1.2rem;">
                                    <?php echo $stats['completed_donations']; ?>
                                </strong>
                            </div>
                            <div class="stat-item" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--light-color); border-radius: var(--border-radius);">
                                <span>طلبات الاستلام</span>
                                <strong style="color: var(--secondary-color); font-size: 1.2rem;">
                                    <?php echo $stats['total_requests']; ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- روابط سريعة -->
                    <div class="card">
                        <div class="card-header">
                            <h3>روابط سريعة</h3>
                        </div>
                        <div class="card-body">
                            <div class="quick-links">
                                <a href="charity-donations.php" class="btn btn-outline w-100 mb-2">
                                    التبرعات المستلمة
                                </a>
                                <a href="donations.php" class="btn btn-primary w-100 mb-2">
                                    تصفح التبرعات
                                </a>
                                <a href="dashboard.php" class="btn btn-secondary w-100">
                                    لوحة التحكم
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
