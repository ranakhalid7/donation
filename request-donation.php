<?php
require_once 'config.php';
checkLogin();
checkUserType(['beneficiary']);

$pageTitle = 'طلب تبرع';
$pageDescription = 'قدم طلبك للحصول على تبرع';

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

$error = '';
$success = '';

// الحصول على معرف التبرع
$donationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($donationId == 0) {
    header('Location: donations.php');
    exit();
}

// جلب تفاصيل التبرع
$stmt = $db->prepare("
    SELECT d.*, u.full_name as donor_name, u.phone as donor_phone, 
           u.email as donor_email, c.charity_name
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    LEFT JOIN charities c ON d.charity_id = c.id
    WHERE d.id = ? AND d.status = 'available'
");
$stmt->execute([$donationId]);
$donation = $stmt->fetch();

if (!$donation) {
    $_SESSION['message'] = 'التبرع غير متاح أو غير موجود';
    $_SESSION['message_type'] = 'error';
    header('Location: donations.php');
    exit();
}

// التحقق من عدم وجود طلب سابق
$checkStmt = $db->prepare("
    SELECT id, status FROM donation_requests 
    WHERE donation_id = ? AND requester_id = ?
");
$checkStmt->execute([$donationId, $userId]);
$existingRequest = $checkStmt->fetch();

if ($existingRequest) {
    $_SESSION['message'] = 'لقد قمت بطلب هذا التبرع مسبقاً';
    $_SESSION['message_type'] = 'warning';
    header('Location: donation-details.php?id=' . $donationId);
    exit();
}

// معالجة طلب التبرع
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'رمز الأمان غير صحيح';
    } else {
        $message = trim($_POST['message']);
        
        // التحقق من البيانات
        if (empty($message)) {
            $error = 'يرجى توضيح سبب حاجتك لهذا التبرع';
        } elseif (strlen($message) < 20) {
            $error = 'يرجى كتابة سبب مفصل (20 حرف على الأقل)';
        } else {
            try {
                // إضافة الطلب
                $insertStmt = $db->prepare("
                    INSERT INTO donation_requests (donation_id, requester_id, message, status)
                    VALUES (?, ?, ?, 'pending')
                ");
                
                if ($insertStmt->execute([$donationId, $userId, $message])) {
                    // إضافة إشعار للمتبرع
                    $notifStmt = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type)
                        VALUES (?, 'طلب تبرع جديد', ?, 'info')
                    ");
                    $notifMessage = 'تم استلام طلب جديد على تبرعك: ' . $donation['title'];
                    $notifStmt->execute([$donation['donor_id'], $notifMessage]);
                    
                    $_SESSION['message'] = 'تم إرسال طلبك بنجاح! سيتم التواصل معك قريباً';
                    $_SESSION['message_type'] = 'success';
                    header('Location: my-requests.php');
                    exit();
                } else {
                    $error = 'حدث خطأ أثناء إرسال الطلب';
                }
            } catch (PDOException $e) {
                $error = 'حدث خطأ أثناء إرسال الطلب';
            }
        }
    }
}

// جلب معلومات المستخدم
$userStmt = $db->prepare("SELECT full_name, phone, email, address FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch();
?>
<?php require_once 'includes/header.php'; ?>

    <!-- المحتوى الرئيسي -->
    <section class="request-donation-section" style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <div class="page-header mb-4">
                <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">طلب تبرع</h1>
                <p class="text-muted">قدم طلبك للحصول على هذا التبرع</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger mb-4"><?php echo escape($error); ?></div>
            <?php endif; ?>

            <div class="row">
                <!-- معلومات التبرع -->
                <div class="col-5 col-sm-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h3>تفاصيل التبرع</h3>
                        </div>
                        <div class="card-body">
                            <?php 
                            $images = json_decode($donation['images'], true);
                            if (!empty($images)): 
                            ?>
                            <div class="mb-3">
                                <img src="<?php echo escape($images[0]); ?>" 
                                     alt="<?php echo escape($donation['title']); ?>" 
                                     style="width: 100%; height: 250px; object-fit: cover; border-radius: var(--radius);">
                            </div>
                            <?php endif; ?>

                            <h3 style="color: var(--primary-color); margin-bottom: 1rem;">
                                <?php echo escape($donation['title']); ?>
                            </h3>

                            <div class="mb-3">
                                <span class="badge badge-primary">
                                    <?php 
                                    $categoryLabels = [
                                        'clothing' => 'ملابس',
                                        'furniture' => 'أثاث',
                                        'electronics' => 'إلكترونيات',
                                        'other' => 'أخرى'
                                    ];
                                    echo $categoryLabels[$donation['category']] ?? $donation['category'];
                                    ?>
                                </span>
                                <span class="badge badge-success">
                                    <?php 
                                    $conditionLabels = [
                                        'new' => 'جديدة',
                                        'excellent' => 'ممتازة',
                                        'good' => 'جيدة',
                                        'fair' => 'مقبولة'
                                    ];
                                    echo $conditionLabels[$donation['condition_item']] ?? $donation['condition_item'];
                                    ?>
                                </span>
                            </div>

                            <div class="mb-3">
                                <strong>الوصف:</strong>
                                <p class="text-muted"><?php echo nl2br(escape($donation['description'])); ?></p>
                            </div>

                            <hr>

                            <div class="text-muted" style="font-size: 0.95rem;">
                                <div class="mb-2">
                                    <strong>الكمية:</strong> <?php echo $donation['quantity']; ?>
                                </div>
                                <div class="mb-2">
                                    <strong>موقع الاستلام:</strong> <?php echo escape($donation['pickup_location']); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>طريقة التسليم:</strong>
                                    <?php 
                                    $deliveryLabels = [
                                        'pickup' => 'استلام من المتبرع',
                                        'delivery' => 'توصيل للمستفيد',
                                        'both' => 'كلا الطريقتين'
                                    ];
                                    echo $deliveryLabels[$donation['delivery_method']] ?? $donation['delivery_method'];
                                    ?>
                                </div>
                                <div>
                                    <strong>المتبرع:</strong> <?php echo escape($donation['donor_name']); ?>
                                </div>
                            </div>

                            <hr>

                            <div class="text-center">
                                <a href="donation-details.php?id=<?php echo $donationId; ?>" 
                                   class="btn btn-outline w-100">
                                    عرض التفاصيل الكاملة
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- نموذج الطلب -->
                <div class="col-7 col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h3>تقديم الطلب</h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-4">
                                <strong>📋 معلومات هامة:</strong>
                                <ul style="margin: 0.5rem 0 0 0; padding-right: 1.5rem;">
                                    <li>يرجى توضيح سبب حاجتك لهذا التبرع بشكل مفصل</li>
                                    <li>سيتم مراجعة طلبك من قبل المتبرع</li>
                                    <li>في حالة الموافقة، سيتم إرسال معلومات الاتصال إليك</li>
                                    <li>يمكنك متابعة حالة طلبك من صفحة "طلباتي"</li>
                                </ul>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                                <!-- معلومات المستخدم (للعرض فقط) -->
                                <div class="mb-4 p-3" style="background: #f8f9fa; border-radius: var(--radius); border-right: 4px solid var(--secondary-color);">
                                    <h4 style="color: var(--primary-color); margin-bottom: 1rem;">معلوماتك الشخصية</h4>
                                    <div class="row">
                                        <div class="col-6 col-sm-12 mb-2">
                                            <small class="text-muted">الاسم:</small>
                                            <div><strong><?php echo escape($userInfo['full_name']); ?></strong></div>
                                        </div>
                                        <div class="col-6 col-sm-12 mb-2">
                                            <small class="text-muted">رقم الهاتف:</small>
                                            <div><strong><?php echo escape($userInfo['phone']); ?></strong></div>
                                        </div>
                                        <div class="col-6 col-sm-12 mb-2">
                                            <small class="text-muted">البريد الإلكتروني:</small>
                                            <div><strong><?php echo escape($userInfo['email']); ?></strong></div>
                                        </div>
                                        <div class="col-6 col-sm-12 mb-2">
                                            <small class="text-muted">العنوان:</small>
                                            <div><strong><?php echo escape($userInfo['address'] ?: 'غير محدد'); ?></strong></div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">💡 ستُرسل هذه المعلومات للمتبرع في حالة الموافقة على طلبك</small>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="message" class="form-label">
                                        سبب الحاجة للتبرع <span style="color: var(--danger-color);">*</span>
                                    </label>
                                    <textarea id="message" name="message" class="form-control" 
                                              rows="8" required minlength="20"
                                              placeholder="يرجى توضيح سبب حاجتك لهذا التبرع بشكل مفصل (على الأقل 20 حرف)...

مثال:
- وصف حالتك الاجتماعية أو المادية
- سبب احتياجك لهذا الصنف بالتحديد
- كيف سيساعدك هذا التبرع
- أي معلومات إضافية قد تدعم طلبك"><?php echo escape($_POST['message'] ?? ''); ?></textarea>
                                    <small class="text-muted">
                                        الرجاء كتابة سبب حاجتك بشكل واضح ومفصل لزيادة فرص قبول طلبك
                                    </small>
                                </div>

                                <div class="form-group">
                                    <div class="form-check">
                                        <input type="checkbox" id="confirm" name="confirm" class="form-check-input" required>
                                        <label for="confirm" class="form-check-label">
                                            أؤكد أن المعلومات المقدمة صحيحة وأنني بحاجة فعلية لهذا التبرع
                                        </label>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-success" style="font-size: 1.1rem; padding: 1rem 3rem;">
                                        إرسال الطلب
                                    </button>
                                    <a href="donation-details.php?id=<?php echo $donationId; ?>" 
                                       class="btn btn-secondary" style="font-size: 1.1rem; padding: 1rem 3rem;">
                                        إلغاء
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- نصائح -->
                    <div class="card mt-4" style="background: linear-gradient(135deg, #fff3e0, #ffe0b2); border: none;">
                        <div class="card-body">
                            <h4 style="color: var(--warning-color); margin-bottom: 1rem;">💡 نصائح لزيادة فرص قبول طلبك</h4>
                            <ul style="margin: 0; padding-right: 1.5rem; color: #5d4037;">
                                <li>كن صادقاً وواضحاً في توضيح سبب حاجتك</li>
                                <li>اذكر التفاصيل التي تجعل طلبك مميزاً</li>
                                <li>تأكد من صحة معلومات الاتصال الخاصة بك</li>
                                <li>كن مهذباً ومحترماً في صياغة طلبك</li>
                                <li>لا تقدم طلبات على تبرعات لا تحتاجها فعلياً</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>