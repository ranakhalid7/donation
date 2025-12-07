<?php
require_once 'config.php';
checkLogin();
checkUserType(['beneficiary']);

$pageTitle = 'طلباتي';
$pageDescription = 'تابع حالة طلباتك على التبرعات المتاحة';

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// معالجة إلغاء الطلب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_request'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $requestId = intval($_POST['request_id']);
        
        // التحقق من ملكية الطلب
        $checkStmt = $db->prepare("SELECT id, status FROM donation_requests WHERE id = ? AND requester_id = ?");
        $checkStmt->execute([$requestId, $userId]);
        $request = $checkStmt->fetch();
        
        if ($request && $request['status'] === 'pending') {
            try {
                $deleteStmt = $db->prepare("DELETE FROM donation_requests WHERE id = ?");
                $deleteStmt->execute([$requestId]);
                $_SESSION['message'] = 'تم إلغاء الطلب بنجاح';
                $_SESSION['message_type'] = 'success';
            } catch (PDOException $e) {
                $_SESSION['message'] = 'حدث خطأ أثناء الإلغاء';
                $_SESSION['message_type'] = 'error';
            }
        }
    }
    header('Location: my-requests.php');
    exit();
}

// الفلترة
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';

// بناء الاستعلام
$sql = "SELECT dr.*, d.title, d.description, d.category, d.condition_item, 
        d.quantity, d.pickup_location, d.status as donation_status,
        u.full_name as donor_name, u.phone as donor_phone, u.email as donor_email
        FROM donation_requests dr
        JOIN donations d ON dr.donation_id = d.id
        JOIN users u ON d.donor_id = u.id
        WHERE dr.requester_id = ?";

$params = [$userId];

if ($status !== 'all') {
    $sql .= " AND dr.status = ?";
    $params[] = $status;
}

if ($category !== 'all') {
    $sql .= " AND d.category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY dr.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// إحصائيات
$statsStmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected
    FROM donation_requests 
    WHERE requester_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();
?>
<?php require_once 'includes/header.php'; ?>

    <!-- المحتوى الرئيسي -->
    <section class="my-requests-section" style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <div class="page-header mb-4">
                <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">طلبات التبرع الخاصة بي</h1>
                <p class="text-muted">تابع حالة طلباتك على التبرعات المتاحة</p>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> mb-4">
                    <?php echo escape($_SESSION['message']); ?>
                </div>
                <?php 
                unset($_SESSION['message'], $_SESSION['message_type']);
                endif; 
            ?>

            <!-- الإحصائيات -->
            <div class="stats-grid mb-4">
                <div class="row">
                    <div class="col-3 col-sm-12 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $stats['total']; ?></div>
                            <div class="stats-label">إجمالي الطلبات</div>
                        </div>
                    </div>
                    <div class="col-3 col-sm-12 mb-3">
                        <div class="stats-card orange">
                            <div class="stats-number"><?php echo $stats['pending']; ?></div>
                            <div class="stats-label">قيد الانتظار</div>
                        </div>
                    </div>
                    <div class="col-3 col-sm-12 mb-3">
                        <div class="stats-card green">
                            <div class="stats-number"><?php echo $stats['approved']; ?></div>
                            <div class="stats-label">مقبولة</div>
                        </div>
                    </div>
                    <div class="col-3 col-sm-12 mb-3">
                        <div class="stats-card red">
                            <div class="stats-number"><?php echo $stats['rejected']; ?></div>
                            <div class="stats-label">مرفوضة</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- الفلاتر -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-5 col-sm-12 mb-3">
                            <label for="status" class="form-label">حالة الطلب:</label>
                            <select name="status" id="status" class="form-control form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>الكل</option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                                <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>مقبول</option>
                                <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>مرفوض</option>
                            </select>
                        </div>

                        <div class="col-5 col-sm-12 mb-3">
                            <label for="category" class="form-label">الفئة:</label>
                            <select name="category" id="category" class="form-control form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>الكل</option>
                                <option value="clothing" <?php echo $category === 'clothing' ? 'selected' : ''; ?>>ملابس</option>
                                <option value="furniture" <?php echo $category === 'furniture' ? 'selected' : ''; ?>>أثاث</option>
                                <option value="electronics" <?php echo $category === 'electronics' ? 'selected' : ''; ?>>إلكترونيات</option>
                                <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>أخرى</option>
                            </select>
                        </div>

                        <div class="col-2 col-sm-12 mb-3">
                            <?php if ($status !== 'all' || $category !== 'all'): ?>
                                <label class="form-label" style="opacity: 0;">إعادة</label>
                                <a href="my-requests.php" class="btn btn-secondary w-100">إعادة تعيين</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- قائمة الطلبات -->
            <?php if (empty($requests)): ?>
                <div class="card">
                    <div class="card-body text-center" style="padding: 4rem 2rem;">
                        <div style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;">📋</div>
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">لا توجد طلبات بعد</h3>
                        <p class="text-muted mb-4">لم تقم بتقديم أي طلبات على التبرعات حتى الآن</p>
                        <a href="donations.php" class="btn btn-primary">تصفح التبرعات المتاحة</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="requests-container">
                    <?php foreach ($requests as $request): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-8 col-sm-12">
                                    <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                                        <?php echo escape($request['title']); ?>
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
                                            echo $categoryLabels[$request['category']] ?? $request['category'];
                                            ?>
                                        </span>
                                        <span class="badge badge-<?php 
                                            echo $request['status'] === 'pending' ? 'warning' : 
                                                 ($request['status'] === 'approved' ? 'success' : 'danger');
                                        ?>">
                                            <?php 
                                            $statusText = [
                                                'pending' => 'قيد الانتظار',
                                                'approved' => 'مقبول',
                                                'rejected' => 'مرفوض'
                                            ];
                                            echo $statusText[$request['status']];
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <strong>وصف التبرع:</strong>
                                        <p class="text-muted mb-2">
                                            <?php echo escape(substr($request['description'], 0, 200)) . (strlen($request['description']) > 200 ? '...' : ''); ?>
                                        </p>
                                    </div>

                                    <div class="mb-3">
                                        <strong>سبب الطلب:</strong>
                                        <p class="text-muted mb-0"><?php echo escape($request['message']); ?></p>
                                    </div>

                                    <div class="text-muted" style="font-size: 0.9rem;">
                                        <div><strong>الحالة:</strong> <?php 
                                            $conditionLabels = [
                                                'new' => 'جديدة',
                                                'excellent' => 'ممتازة',
                                                'good' => 'جيدة',
                                                'fair' => 'مقبولة'
                                            ];
                                            echo $conditionLabels[$request['condition_item']] ?? $request['condition_item'];
                                        ?></div>
                                        <div><strong>الكمية:</strong> <?php echo $request['quantity']; ?></div>
                                        <div><strong>موقع الاستلام:</strong> <?php echo escape($request['pickup_location']); ?></div>
                                        <div><strong>تاريخ الطلب:</strong> <?php echo date('Y-m-d H:i', strtotime($request['created_at'])); ?></div>
                                    </div>
                                    
                                    <?php if ($request['status'] === 'approved'): ?>
                                    <div class="mt-3 p-3" style="background: #d4edda; border-radius: var(--radius); border-right: 4px solid var(--success-color);">
                                        <h4 style="color: var(--success-color); margin-bottom: 0.5rem;">معلومات الاتصال بالمتبرع:</h4>
                                        <div><strong>الاسم:</strong> <?php echo escape($request['donor_name']); ?></div>
                                        <div><strong>الهاتف:</strong> <?php echo escape($request['donor_phone']); ?></div>
                                        <div><strong>البريد:</strong> <?php echo escape($request['donor_email']); ?></div>
                                        <div class="mt-2">
                                            <small class="text-muted">يرجى التواصل مع المتبرع لتنسيق موعد الاستلام</small>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-4 col-sm-12 text-center">
                                    <div class="mb-3">
                                        <a href="donation-details.php?id=<?php echo $request['donation_id']; ?>" 
                                           class="btn btn-primary w-100 mb-2">
                                            عرض التبرع
                                        </a>
                                        
                                        <?php if ($request['status'] === 'pending'): ?>
                                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من إلغاء هذا الطلب؟')">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" name="cancel_request" class="btn btn-danger w-100">
                                                إلغاء الطلب
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>