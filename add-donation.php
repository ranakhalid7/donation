<?php
require_once 'config.php';
checkLogin();
checkUserType(['donor']);

$pageTitle = 'إضافة تبرع جديد';
$pageDescription = 'شارك في الخير وأضف تبرعك ليستفيد منه المحتاجون';

$errors = [];
$success = '';

// جلب الجمعيات المعتمدة
$db = Database::getInstance();
$charitiesStmt = $db->prepare("SELECT id, charity_name, description FROM charities WHERE verified = 1 ORDER BY charity_name ASC");
$charitiesStmt->execute();
$charities = $charitiesStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $condition = $_POST['condition_item'];
    $quantity = (int)$_POST['quantity'];
    $pickupLocation = trim($_POST['pickup_location']);
    $deliveryMethod = $_POST['delivery_method'];
    $charityId = !empty($_POST['charity_id']) ? (int)$_POST['charity_id'] : null;

    // التحقق من البيانات
    if (empty($title)) $errors[] = 'عنوان التبرع مطلوب';
    if (empty($description)) $errors[] = 'وصف التبرع مطلوب';
    if (empty($category)) $errors[] = 'فئة التبرع مطلوبة';
    if (empty($condition)) $errors[] = 'حالة السلعة مطلوبة';
    if ($quantity < 1) $errors[] = 'الكمية يجب أن تكون على الأقل 1';
    if (empty($pickupLocation)) $errors[] = 'موقع الاستلام مطلوب';
    if (empty($deliveryMethod)) $errors[] = 'طريقة التسليم مطلوبة';
    
    // معالجة الصور
    $uploadedImages = [];
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = UPLOAD_PATH . 'donations/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxFiles = 5;
        
        for ($i = 0; $i < count($_FILES['images']['name']) && $i < $maxFiles; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['images']['tmp_name'][$i];
                $fileName = $_FILES['images']['name'][$i];
                $fileSize = $_FILES['images']['size'][$i];
                $fileType = $_FILES['images']['type'][$i];
                
                // التحقق من نوع الملف
                if (!in_array($fileType, $allowedTypes)) {
                    $errors[] = "نوع الملف غير مدعوم: $fileName";
                    continue;
                }
                
                // التحقق من حجم الملف
                if ($fileSize > MAX_FILE_SIZE) {
                    $errors[] = "حجم الملف كبير جداً: $fileName";
                    continue;
                }
                
                // إنشاء اسم ملف فريد
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = uniqid('donation_') . '.' . $fileExtension;
                $targetPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $uploadedImages[] = $uploadDir . $newFileName;
                } else {
                    $errors[] = "فشل في رفع الصورة: $fileName";
                }
            }
        }
    }
    
    // حفظ التبرع في قاعدة البيانات
    if (empty($errors)) {
        $db = Database::getInstance();
        $imagesJson = json_encode($uploadedImages);

        // تحديد الحالة: إذا كان هناك جمعية محددة، الحالة ستكون pending_charity_approval
        // وإلا ستكون available للتوزيع المباشر
        $status = $charityId ? 'pending_charity_approval' : 'available';

        $stmt = $db->prepare("
            INSERT INTO donations (donor_id, charity_id, title, description, category, condition_item, quantity, images, pickup_location, delivery_method, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt->execute([$_SESSION['user_id'], $charityId, $title, $description, $category, $condition, $quantity, $imagesJson, $pickupLocation, $deliveryMethod, $status])) {
            // إرسال إشعار للجمعية إذا تم تحديد جمعية
            if ($charityId) {
                // الحصول على user_id الخاص بالجمعية
                $charityUserStmt = $db->prepare("SELECT user_id, charity_name FROM charities WHERE id = ?");
                $charityUserStmt->execute([$charityId]);
                $charityUser = $charityUserStmt->fetch();

                if ($charityUser) {
                    $notifStmt = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type)
                        VALUES (?, ?, ?, ?)
                    ");
                    $notifStmt->execute([
                        $charityUser['user_id'],
                        'تبرع جديد في انتظار الموافقة',
                        'تبرع جديد "' . $title . '" يحتاج إلى موافقتكم للنشر',
                        'info'
                    ]);
                }
                $success = 'تم إضافة التبرع بنجاح! في انتظار موافقة الجمعية على نشره.';
            } else {
                $success = 'تم إضافة التبرع بنجاح ونشره للمستفيدين!';
            }

            // إعادة تعيين النموذج
            $_POST = [];
        } else {
            $errors[] = 'حدث خطأ أثناء حفظ التبرع';
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>

    <!-- صفحة إضافة التبرع -->
    <section class="add-donation" style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">إضافة تبرع جديد</h2>
                            <p class="text-muted">شارك في الخير وأضف تبرعك ليستفيد منه المحتاجون</p>
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
                            
                            <form method="POST" enctype="multipart/form-data" data-validate="true">
                                <div class="row">
                                    <div class="col-8 col-sm-12">
                                        <div class="form-group">
                                            <label for="title" class="form-label">عنوان التبرع</label>
                                            <input type="text" id="title" name="title" class="form-control" 
                                                   value="<?php echo escape($_POST['title'] ?? ''); ?>" 
                                                   placeholder="مثال: ملابس شتوية للأطفال" required>
                                        </div>
                                    </div>
                                    <div class="col-4 col-sm-12">
                                        <div class="form-group">
                                            <label for="quantity" class="form-label">الكمية</label>
                                            <input type="number" id="quantity" name="quantity" class="form-control" 
                                                   value="<?php echo escape($_POST['quantity'] ?? '1'); ?>" 
                                                   min="1" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description" class="form-label">وصف التبرع</label>
                                    <textarea id="description" name="description" class="form-control" 
                                              rows="4" placeholder="اكتب وصفاً مفصلاً عن التبرع وحالته..." required><?php echo escape($_POST['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="category" class="form-label">فئة التبرع</label>
                                            <select id="category" name="category" class="form-control form-select" required>
                                                <option value="">اختر الفئة</option>
                                                <option value="clothing" <?php echo ($_POST['category'] ?? '') === 'clothing' ? 'selected' : ''; ?>>ملابس</option>
                                                <option value="furniture" <?php echo ($_POST['category'] ?? '') === 'furniture' ? 'selected' : ''; ?>>أثاث</option>
                                                <option value="electronics" <?php echo ($_POST['category'] ?? '') === 'electronics' ? 'selected' : ''; ?>>إلكترونيات</option>
                                                <option value="other" <?php echo ($_POST['category'] ?? '') === 'other' ? 'selected' : ''; ?>>أخرى</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="condition_item" class="form-label">حالة السلعة</label>
                                            <select id="condition_item" name="condition_item" class="form-control form-select" required>
                                                <option value="">اختر الحالة</option>
                                                <option value="new" <?php echo ($_POST['condition_item'] ?? '') === 'new' ? 'selected' : ''; ?>>جديدة</option>
                                                <option value="excellent" <?php echo ($_POST['condition_item'] ?? '') === 'excellent' ? 'selected' : ''; ?>>ممتازة</option>
                                                <option value="good" <?php echo ($_POST['condition_item'] ?? '') === 'good' ? 'selected' : ''; ?>>جيدة</option>
                                                <option value="fair" <?php echo ($_POST['condition_item'] ?? '') === 'fair' ? 'selected' : ''; ?>>مقبولة</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="images" class="form-label">صور التبرع </label>
                                    <input type="file" id="images" name="images[]" class="form-control" 
                                           multiple accept="image/*">
                                    <small class="text-muted">يمكنك رفع حتى 5 صور بحجم أقصى 5 ميجابايت لكل صورة</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="pickup_location" class="form-label">موقع الاستلام</label>
                                    <input type="text" id="pickup_location" name="pickup_location" class="form-control" 
                                           value="<?php echo escape($_POST['pickup_location'] ?? ''); ?>" 
                                           placeholder="المدينة، الحي، أو عنوان مفصل" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="delivery_method" class="form-label">طريقة التسليم</label>
                                    <select id="delivery_method" name="delivery_method" class="form-control form-select" required>
                                        <option value="">اختر طريقة التسليم</option>
                                        <option value="pickup" <?php echo ($_POST['delivery_method'] ?? '') === 'pickup' ? 'selected' : ''; ?>>استلام من المتبرع</option>
                                        <option value="delivery" <?php echo ($_POST['delivery_method'] ?? '') === 'delivery' ? 'selected' : ''; ?>>توصيل للمستفيد</option>
                                        <option value="both" <?php echo ($_POST['delivery_method'] ?? '') === 'both' ? 'selected' : ''; ?>>كلا الطريقتين</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="charity_id" class="form-label">تخصيص لجمعية خيرية (اختياري)</label>
                                    <select id="charity_id" name="charity_id" class="form-control form-select">
                                        <option value="">اختر جمعية (أو اترك فارغاً للتوزيع المباشر)</option>
                                        <?php foreach ($charities as $charity): ?>
                                            <option value="<?php echo $charity['id']; ?>"
                                                    <?php echo ($_POST['charity_id'] ?? '') == $charity['id'] ? 'selected' : ''; ?>>
                                                <?php echo escape($charity['charity_name']); ?>
                                                <?php if ($charity['description']): ?>
                                                    - <?php echo escape(substr($charity['description'], 0, 50)); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">
                                        ✓ إذا اخترت جمعية، ستقوم الجمعية باستلام التبرع منك وتوزيعه على المستفيدين<br>
                                        ✓ إذا تركت فارغاً، يمكن للمستفيدين طلب التبرع مباشرة منك
                                    </small>
                                </div>

                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" id="terms" name="terms" class="form-check-input" required>
                                        <label for="terms" class="form-check-label">
                                            أؤكد أن المعلومات المقدمة صحيحة وأن التبرع في حالة جيدة وصالح للاستخدام
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-success" style="font-size: 1.1rem; padding: 1rem 2rem;">
                                        إضافة التبرع
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                                        إلغاء
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
