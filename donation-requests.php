<?php
require_once 'config.php';
checkLogin();
checkUserType(['beneficiary']);

$pageTitle = 'طلبات التبرع';
$pageDescription = 'تصفح وإدارة طلبات التبرع الخاصة بك';

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
                $success = "تم إلغاء الطلب بنجاح";
            } catch (PDOException $e) {
                $error = "حدث خطأ أثناء الإلغاء";
            }
        }
    }
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

    <!-- Main Content -->
    <div class="container">
        <div class="page-header">
            <h1>طلبات التبرع الخاصة بي</h1>
            <a href="donations.php" class="btn btn-primary">تصفح التبرعات المتاحة</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo escape($error); ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo escape($success); ?></div>
        <?php endif; ?>

        <!-- إحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-details">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>إجمالي الطلبات</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-details">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>قيد الانتظار</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-details">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>مقبولة</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❌</div>
                <div class="stat-details">
                    <h3><?php echo $stats['rejected']; ?></h3>
                    <p>مرفوضة</p>
                </div>
            </div>
        </div>

        <!-- الفلاتر -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>حالة الطلب:</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>الكل</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>مقبول</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>مرفوض</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>الفئة:</label>
                    <select name="category" onchange="this.form.submit()">
                        <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>الكل</option>
                        <option value="clothing" <?php echo $category === 'clothing' ? 'selected' : ''; ?>>ملابس</option>
                        <option value="furniture" <?php echo $category === 'furniture' ? 'selected' : ''; ?>>أثاث</option>
                        <option value="electronics" <?php echo $category === 'electronics' ? 'selected' : ''; ?>>أجهزة كهربائية</option>
                        <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>أخرى</option>
                    </select>
                </div>

                <?php if ($status !== 'all' || $category !== 'all'): ?>
                    <a href="donation-requests.php" class="btn btn-secondary">إعادة تعيين</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- قائمة الطلبات -->
        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <p>لا توجد طلبات بعد</p>
                <a href="donations.php" class="btn btn-primary">تصفح التبرعات المتاحة</a>
            </div>
        <?php else: ?>
            <div class="requests-container">
                <?php foreach ($requests as $request): ?>
                <div class="request-card">
                    <div class="request-header">
                        <div>
                            <h3><?php echo escape($request['title']); ?></h3>
                            <span class="badge badge-<?php echo $request['category']; ?>">
                                <?php echo escape($request['category']); ?>
                            </span>
                        </div>
                        <span class="badge badge-<?php echo $request['status']; ?>">
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
                    
                    <div class="request-body">
                        <div class="donation-info-section">
                            <h4>معلومات التبرع:</h4>
                            <p><?php echo escape(substr($request['description'], 0, 150)) . (strlen($request['description']) > 150 ? '...' : ''); ?></p>
                            <div class="donation-meta">
                                <span><strong>الحالة:</strong> <?php echo escape($request['condition_item']); ?></span>
                                <span><strong>الكمية:</strong> <?php echo escape($request['quantity']); ?></span>
                                <span><strong>الموقع:</strong> <?php echo escape($request['pickup_location']); ?></span>
                            </div>
                        </div>
                        
                        <div class="request-reason">
                            <h4>سبب الطلب:</h4>
                            <p><?php echo escape($request['message']); ?></p>
                        </div>
                        
                        <?php if ($request['status'] === 'approved'): ?>
                        <div class="donor-contact">
                            <h4>معلومات الاتصال بالمتبرع:</h4>
                            <p><strong>الاسم:</strong> <?php echo escape($request['donor_name']); ?></p>
                            <p><strong>الهاتف:</strong> <?php echo escape($request['donor_phone']); ?></p>
                            <p><strong>البريد:</strong> <?php echo escape($request['donor_email']); ?></p>
                            <div class="alert alert-info">
                                يرجى التواصل مع المتبرع لتنسيق موعد الاستلام
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="request-dates">
                            <span>📅 تاريخ الطلب: <?php echo date('Y-m-d H:i', strtotime($request['created_at'])); ?></span>
                            <?php if ($request['updated_at'] && $request['updated_at'] !== $request['created_at']): ?>
                            <span>🔄 آخر تحديث: <?php echo date('Y-m-d H:i', strtotime($request['updated_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="request-actions">
                        <a href="donation-details.php?id=<?php echo $request['donation_id']; ?>" 
                           class="btn btn-sm btn-primary">
                            عرض التبرع
                        </a>
                        
                        <?php if ($request['status'] === 'pending'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                            <button type="submit" name="cancel_request" 
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('هل أنت متأكد من إلغاء هذا الطلب؟')">
                                إلغاء الطلب
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php require_once 'includes/footer.php'; ?>