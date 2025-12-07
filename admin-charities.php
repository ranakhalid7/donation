<?php
require_once 'config.php';

$pageTitle = 'إدارة الجمعيات';
$pageDescription = 'إدارة ومراقبة جميع الجمعيات الخيرية المسجلة';

checkLogin();
checkUserType(['admin']);

$db = Database::getInstance();

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $charityId = (int)$_POST['charity_id'];
    $action = $_POST['action'];
    
    if (in_array($action, ['verify', 'unverify', 'delete'])) {
        if ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM charities WHERE id = ?");
            $stmt->execute([$charityId]);
            $message = 'تم حذف الجمعية بنجاح';
        } elseif ($action === 'verify') {
            $stmt = $db->prepare("UPDATE charities SET verified = 1 WHERE id = ?");
            $stmt->execute([$charityId]);
            $message = 'تم اعتماد الجمعية';
        } elseif ($action === 'unverify') {
            $stmt = $db->prepare("UPDATE charities SET verified = 0 WHERE id = ?");
            $stmt->execute([$charityId]);
            $message = 'تم إلغاء اعتماد الجمعية';
        }
        
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = 'success';
    }
    
    header('Location: admin-charities.php');
    exit();
}

// فلترة الجمعيات
$verified = $_GET['verified'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// بناء الاستعلام
$whereConditions = [];
$params = [];

if ($verified !== '') {
    $whereConditions[] = "c.verified = ?";
    $params[] = (int)$verified;
}

if (!empty($search)) {
    $whereConditions[] = "(c.name LIKE ? OR c.description LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// عدد النتائج الإجمالي
$countSql = "SELECT COUNT(*) FROM charities c JOIN users u ON c.user_id = u.id $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalResults = $countStmt->fetchColumn();
$totalPages = ceil($totalResults / $limit);

// جلب الجمعيات
$sql = "
    SELECT c.*, u.full_name, u.email, u.phone, u.created_at as user_created_at,
           (SELECT COUNT(*) FROM donations WHERE charity_id = c.id) as donations_count
    FROM charities c
    JOIN users u ON c.user_id = u.id
    $whereClause
    ORDER BY c.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$charities = $stmt->fetchAll();

// إحصائيات سريعة
$statsStmt = $db->prepare("
    SELECT 
        COUNT(*) as total_charities,
        COUNT(CASE WHEN verified = 1 THEN 1 END) as verified_charities,
        COUNT(CASE WHEN verified = 0 THEN 1 END) as unverified_charities
    FROM charities
");
$statsStmt->execute();
$stats = $statsStmt->fetch();
?>

<?php require_once 'includes/header.php'; ?>

    <!-- صفحة إدارة الجمعيات -->
    <section class="admin-charities" style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <!-- عنوان الصفحة -->
            <div class="page-header mb-4">
                <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">إدارة الجمعيات الخيرية</h1>
                <p class="text-muted">إدارة ومراقبة جميع الجمعيات الخيرية المسجلة</p>
            </div>
            
            <!-- إحصائيات سريعة -->
            <div class="stats-grid mb-4">
                <div class="row">
                    <div class="col-4 col-sm-12 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $stats['total_charities']; ?></div>
                            <div class="stats-label">إجمالي الجمعيات</div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-12 mb-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, var(--success-color), #27ae60);">
                            <div class="stats-number"><?php echo $stats['verified_charities']; ?></div>
                            <div class="stats-label">معتمدة</div>
                        </div>
                    </div>
                    <div class="col-4 col-sm-12 mb-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, var(--warning-color), #f39c12);">
                            <div class="stats-number"><?php echo $stats['unverified_charities']; ?></div>
                            <div class="stats-label">غير معتمدة</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- فلاتر البحث -->
            <div class="search-filters mb-4">
                <form method="GET" class="row">
                    <div class="col-4 col-sm-12 mb-3">
                        <input type="text" name="search" class="form-control" 
                               placeholder="البحث بالاسم أو الوصف..." 
                               value="<?php echo escape($search); ?>">
                    </div>
                    <div class="col-3 col-sm-12 mb-3">
                        <select name="verified" class="form-control form-select">
                            <option value="">جميع الحالات</option>
                            <option value="1" <?php echo $verified === '1' ? 'selected' : ''; ?>>معتمدة</option>
                            <option value="0" <?php echo $verified === '0' ? 'selected' : ''; ?>>غير معتمدة</option>
                        </select>
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <button type="submit" class="btn btn-primary w-100">بحث</button>
                    </div>
                    <div class="col-3 col-sm-12 mb-3">
                        <a href="admin-charities.php" class="btn btn-secondary w-100">مسح الفلاتر</a>
                    </div>
                </form>
            </div>
            
            <!-- عرض الجمعيات -->
            <?php if (!empty($charities)): ?>
                <div class="charities-list">
                    <?php foreach ($charities as $charity): ?>
                        <div class="card charity-card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h4 class="mb-0"><?php echo escape($charity['charity_name']); ?></h4>
                                <?php if ($charity['verified']): ?>
                                    <span class="badge badge-success" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                        ✓ معتمدة
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                        ⏳ غير معتمدة
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-8 col-sm-12 mb-3">
                                        <p class="mb-2"><strong>الوصف:</strong></p>
                                        <p class="text-muted mb-3"><?php echo escape($charity['description']); ?></p>
                                        
                                        <div class="charity-info mb-3">
                                            <div class="row">
                                                <div class="col-6 col-sm-12 mb-2">
                                                    <strong>مسؤول الجمعية:</strong><br>
                                                    <?php echo escape($charity['full_name']); ?>
                                                </div>
                                                <div class="col-6 col-sm-12 mb-2">
                                                    <strong>البريد الإلكتروني:</strong><br>
                                                    <?php echo escape($charity['email']); ?>
                                                </div>
                                                <div class="col-6 col-sm-12 mb-2">
                                                    <strong>الهاتف:</strong><br>
                                                    <?php echo escape($charity['phone']); ?>
                                                </div>
                                                <!-- <div class="col-6 col-sm-12 mb-2">
                                                    <strong>العنوان:</strong><br>
                                                    <?php echo escape($charity['address']); ?>
                                                </div> -->
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($charity['license_number'])): ?>
                                            <p><strong>رقم الترخيص:</strong> <?php echo escape($charity['license_number']); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="charity-stats">
                                            <span class="badge badge-info">
                                                <?php echo $charity['donations_count']; ?> تبرع مستلم
                                            </span>
                                        </div>
                                        
                                        <small class="text-muted">
                                            تاريخ التسجيل: <?php echo date('Y-m-d', strtotime($charity['user_created_at'])); ?>
                                        </small>
                                    </div>
                                    
                                    <div class="col-4 col-sm-12 text-center">
                                        <div class="charity-actions">
                                            <?php if ($charity['verified']): ?>
                                                <form method="POST" style="display: inline; margin-bottom: 1rem;">
                                                    <input type="hidden" name="charity_id" value="<?php echo $charity['id']; ?>">
                                                    <button type="submit" name="action" value="unverify" 
                                                            class="btn btn-warning btn-sm w-100"
                                                            onclick="return confirm('إلغاء اعتماد هذه الجمعية؟')">
                                                        إلغاء الاعتماد
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline; margin-bottom: 1rem;">
                                                    <input type="hidden" name="charity_id" value="<?php echo $charity['id']; ?>">
                                                    <button type="submit" name="action" value="verify" 
                                                            class="btn btn-success btn-sm w-100"
                                                            onclick="return confirm('اعتماد هذه الجمعية؟')">
                                                        اعتماد الجمعية
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="charity_id" value="<?php echo $charity['id']; ?>">
                                                <button type="submit" name="action" value="delete" 
                                                        class="btn btn-danger btn-sm w-100"
                                                        onclick="return confirm('حذف هذه الجمعية نهائياً؟ هذا الإجراء لا يمكن التراجع عنه.')">
                                                    حذف الجمعية
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
                <!-- رسالة عدم وجود جمعيات -->
                <div class="no-charities text-center" style="padding: 4rem 0;">
                    <div style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;">🏢</div>
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">لا توجد جمعيات</h3>
                    <p class="text-muted mb-4">لم يتم العثور على جمعيات تطابق معايير البحث</p>
                    <a href="admin-charities.php" class="btn btn-primary">مسح الفلاتر</a>
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
