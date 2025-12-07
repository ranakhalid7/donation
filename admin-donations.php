<?php
require_once 'config.php';

$pageTitle = 'إدارة التبرعات';
$pageDescription = 'إدارة ومراقبة جميع التبرعات في النظام';

checkLogin();
checkUserType(['admin']);

$db = Database::getInstance();

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $donationId = (int)$_POST['donation_id'];
    $action = $_POST['action'];
    
    if (in_array($action, ['approve', 'reject', 'delete'])) {
        if ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM donations WHERE id = ?");
            $stmt->execute([$donationId]);
            $message = 'تم حذف التبرع بنجاح';
        } elseif ($action === 'approve') {
            $stmt = $db->prepare("UPDATE donations SET status = 'available' WHERE id = ?");
            $stmt->execute([$donationId]);
            $message = 'تم قبول التبرع';
        } elseif ($action === 'reject') {
            $stmt = $db->prepare("UPDATE donations SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$donationId]);
            $message = 'تم رفض التبرع';
        }
        
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = 'success';
    }
    
    header('Location: admin-donations.php');
    exit();
}

// فلترة التبرعات
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// بناء الاستعلام
$whereConditions = [];
$params = [];

if (!empty($status)) {
    $whereConditions[] = "d.status = ?";
    $params[] = $status;
}

if (!empty($category)) {
    $whereConditions[] = "d.category = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $whereConditions[] = "(d.title LIKE ? OR d.description LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// عدد النتائج الإجمالي
$countSql = "SELECT COUNT(*) FROM donations d JOIN users u ON d.donor_id = u.id $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalResults = $countStmt->fetchColumn();
$totalPages = ceil($totalResults / $limit);

// جلب التبرعات
$sql = "
    SELECT d.*, u.full_name as donor_name, u.email as donor_email,
           (SELECT COUNT(*) FROM donation_requests WHERE donation_id = d.id) as requests_count
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    $whereClause
    ORDER BY d.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$donations = $stmt->fetchAll();

// إحصائيات سريعة
$statsStmt = $db->prepare("
    SELECT 
        COUNT(*) as total_donations,
        COUNT(CASE WHEN status = 'available' THEN 1 END) as available,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
    FROM donations
");
$statsStmt->execute();
$stats = $statsStmt->fetch();
?>

<?php require_once 'includes/header.php'; ?>

    <!-- صفحة إدارة التبرعات -->
    <section class="admin-donations" style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <!-- عنوان الصفحة -->
            <div class="page-header mb-4">
                <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">إدارة التبرعات</h1>
                <p class="text-muted">إدارة ومراقبة جميع التبرعات في النظام</p>
            </div>
            
            <!-- إحصائيات سريعة -->
                    <!-- <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['total_donations']; ?></div>
                        <div class="stats-label">إجمالي التبرعات</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--success-color), #27ae60);">
                        <div class="stats-number"><?php echo $stats['available']; ?></div>
                        <div class="stats-label">متاحة</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--secondary-color), #3498db);">
                        <div class="stats-number"><?php echo $stats['completed']; ?></div>
                        <div class="stats-label">مكتملة</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--warning-color), #f39c12);">
                        <div class="stats-number"><?php echo $stats['pending']; ?></div>
                        <div class="stats-label">قيد المراجعة</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--danger-color), #c0392b);">
                        <div class="stats-number"><?php echo $stats['cancelled']; ?></div>
                        <div class="stats-label">ملغية</div>
                    </div> -->
           
            
            <!-- فلاتر البحث -->
            <div class="search-filters mb-4">
                <form method="GET" class="row">
                    <div class="col-3 col-sm-12 mb-3">
                        <input type="text" name="search" class="form-control" 
                               placeholder="البحث بالعنوان أو الوصف..." 
                               value="<?php echo escape($search); ?>">
                    </div>
                    <div class="col-3 col-sm-12 mb-3">
                        <select name="status" class="form-control form-select">
                            <option value="">جميع الحالات</option>
                            <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>متاحة</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>مكتملة</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>قيد المراجعة</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>ملغية</option>
                        </select>
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <select name="category" class="form-control form-select">
                            <option value="">جميع الفئات</option>
                            <option value="clothing" <?php echo $category === 'clothing' ? 'selected' : ''; ?>>ملابس</option>
                            <option value="furniture" <?php echo $category === 'furniture' ? 'selected' : ''; ?>>أثاث</option>
                            <option value="electronics" <?php echo $category === 'electronics' ? 'selected' : ''; ?>>إلكترونيات</option>
                            <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>أخرى</option>
                        </select>
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <button type="submit" class="btn btn-primary w-100">بحث</button>
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <a href="admin-donations.php" class="btn btn-secondary w-100">مسح الفلاتر</a>
                    </div>
                </form>
            </div>
            
            <!-- عرض التبرعات -->
            <?php if (!empty($donations)): ?>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>التبرع</th>
                                        <th>المتبرع</th>
                                        <th>الفئة</th>
                                        <th>الحالة</th>
                                        <th>الطلبات</th>
                                        <th>التاريخ</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($donations as $donation): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo escape($donation['title']); ?></strong><br>
                                                    <small class="text-muted">
                                                        <?php echo escape(substr($donation['description'], 0, 50)); ?>...
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php echo escape($donation['donor_name']); ?><br>
                                                    <small class="text-muted"><?php echo escape($donation['donor_email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-primary">
                                                    <?php 
                                                    $categories = [
                                                        'clothing' => 'ملابس',
                                                        'furniture' => 'أثاث',
                                                        'electronics' => 'إلكترونيات',
                                                        'other' => 'أخرى'
                                                    ];
                                                    echo $categories[$donation['category']] ?? $donation['category'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusColors = [
                                                    'available' => 'success',
                                                    'reserved' => 'warning',
                                                    'with_charity' => 'warning',
                                                    'delivered' => 'info',
                                                    'completed' => 'primary',
                                                    'pending' => 'warning',
                                                    'cancelled' => 'danger'
                                                ];
                                                $statusLabels = [
                                                    'available' => 'متاحة',
                                                    'reserved' => 'محجوزة',
                                                    'with_charity' => 'مع الجمعية',
                                                    'delivered' => 'موزعة',
                                                    'completed' => 'مكتملة',
                                                    'pending' => 'قيد المراجعة',
                                                    'cancelled' => 'ملغية'
                                                ];
                                                ?>
                                                <span class="badge badge-<?php echo $statusColors[$donation['status']]; ?>">
                                                    <?php echo $statusLabels[$donation['status']]; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo $donation['requests_count']; ?> طلب
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('Y-m-d', strtotime($donation['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group-vertical">
                                                    <?php if ($donation['status'] === 'pending'): ?>
                                                        <form method="POST" style="display: inline; margin-bottom: 0.5rem;">
                                                            <input type="hidden" name="donation_id" value="<?php echo $donation['id']; ?>">
                                                            <button type="submit" name="action" value="approve" 
                                                                    class="btn btn-success btn-sm">
                                                                قبول
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline; margin-bottom: 0.5rem;">
                                                            <input type="hidden" name="donation_id" value="<?php echo $donation['id']; ?>">
                                                            <button type="submit" name="action" value="reject" 
                                                                    class="btn btn-warning btn-sm">
                                                                رفض
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="donation_id" value="<?php echo $donation['id']; ?>">
                                                        <button type="submit" name="action" value="delete" 
                                                                class="btn btn-danger btn-sm"
                                                                onclick="return confirm('حذف هذا التبرع نهائياً؟')">
                                                            حذف
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- التنقل بين الصفحات -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-wrapper mt-4">
                        <nav aria-label="تنقل الصفحات">
                            <ul class="pagination" style="display: flex; justify-content: center; list-style: none; gap: 0.5rem;">
                                <?php if ($page > 1): ?>
                                    <li>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                           class="btn btn-outline">السابق</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                           class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-outline'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                           class="btn btn-outline">التالي</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- رسالة عدم وجود تبرعات -->
                <div class="no-donations text-center" style="padding: 4rem 0;">
                    <div style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;">📦</div>
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">لا توجد تبرعات</h3>
                    <p class="text-muted mb-4">لم يتم العثور على تبرعات تطابق معايير البحث</p>
                    <a href="admin-donations.php" class="btn btn-primary">مسح الفلاتر</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>

    <?php if (isset($_SESSION['message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            DonationSystem.showNotification('<?php echo escape($_SESSION['message']); ?>', '<?php echo escape($_SESSION['message_type'] ?? 'info'); ?>');
        });
    </script>
    <?php
        unset($_SESSION['message'], $_SESSION['message_type']);
    endif;
    ?>
