<?php
require_once 'config.php';

$pageTitle = 'إدارة المستخدمين';
$pageDescription = 'إدارة ومراقبة جميع مستخدمي المنصة';

checkLogin();
checkUserType(['admin']);

$db = Database::getInstance();

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($action === 'activate' && $userId) {
        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        if ($stmt->execute([$userId])) {
            $_SESSION['message'] = 'تم تفعيل المستخدم بنجاح';
            $_SESSION['message_type'] = 'success';
        }
    } elseif ($action === 'deactivate' && $userId) {
        $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        if ($stmt->execute([$userId])) {
            $_SESSION['message'] = 'تم إلغاء تفعيل المستخدم';
            $_SESSION['message_type'] = 'warning';
        }
    } elseif ($action === 'delete' && $userId) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND user_type != 'admin'");
        if ($stmt->execute([$userId])) {
            $_SESSION['message'] = 'تم حذف المستخدم بنجاح';
            $_SESSION['message_type'] = 'success';
        }
    }
    
    header('Location: admin-users.php');
    exit();
}

// فلترة وبحث
$search = $_GET['search'] ?? '';
$userType = $_GET['user_type'] ?? '';
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($userType)) {
    $whereConditions[] = "user_type = ?";
    $params[] = $userType;
}

if (!empty($status)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
}

$whereClause = empty($whereConditions) ? '1=1' : implode(' AND ', $whereConditions);

// عدد النتائج
$countSql = "SELECT COUNT(*) FROM users WHERE $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalUsers = $countStmt->fetchColumn();
$totalPages = ceil($totalUsers / $limit);

// جلب المستخدمين
$sql = "SELECT * FROM users WHERE $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// إحصائيات
$statsStmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN user_type = 'donor' THEN 1 END) as donors,
        COUNT(CASE WHEN user_type = 'beneficiary' THEN 1 END) as beneficiaries,
        COUNT(CASE WHEN user_type = 'charity' THEN 1 END) as charities
    FROM users
");
$statsStmt->execute();
$stats = $statsStmt->fetch();
?>

<?php require_once 'includes/header.php'; ?>

    <section class="admin-section" style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <h1 style="color: var(--primary-color); margin-bottom: 2rem;">إدارة المستخدمين</h1>
            
            <!-- الإحصائيات -->
            <div class="stats-grid mb-4">
                <div class="row">
                    <div class="col-2 col-sm-12 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $stats['total']; ?></div>
                            <div class="stats-label">إجمالي المستخدمين</div>
                        </div>
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, var(--success-color), #27ae60);">
                            <div class="stats-number"><?php echo $stats['active']; ?></div>
                            <div class="stats-label">مستخدمين نشطين</div>
                        </div>
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, var(--warning-color), #f39c12);">
                            <div class="stats-number"><?php echo $stats['pending']; ?></div>
                            <div class="stats-label">في انتظار التفعيل</div>
                        </div>
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <div class="stats-number"><?php echo $stats['donors']; ?></div>
                            <div class="stats-label">متبرعين</div>
                        </div>
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <div class="stats-number"><?php echo $stats['beneficiaries']; ?></div>
                            <div class="stats-label">مستفيدين</div>
                        </div>
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                            <div class="stats-number"><?php echo $stats['charities']; ?></div>
                            <div class="stats-label">جمعيات خيرية</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- فلاتر البحث -->
            <div class="search-filters mb-4">
                <form method="GET" class="row">
                    <div class="col-4 col-sm-12 mb-3">
                        <input type="text" name="search" class="form-control" 
                               placeholder="البحث باسم المستخدم أو البريد الإلكتروني..." 
                               value="<?php echo escape($search); ?>">
                    </div>
                    <div class="col-3 col-sm-12 mb-3">
                        <select name="user_type" class="form-control form-select">
                            <option value="">جميع الأنواع</option>
                            <option value="donor" <?php echo $userType === 'donor' ? 'selected' : ''; ?>>متبرع</option>
                            <option value="beneficiary" <?php echo $userType === 'beneficiary' ? 'selected' : ''; ?>>مستفيد</option>
                            <option value="charity" <?php echo $userType === 'charity' ? 'selected' : ''; ?>>جمعية خيرية</option>
                            <option value="admin" <?php echo $userType === 'admin' ? 'selected' : ''; ?>>مدير</option>
                        </select>
                    </div>
                    <div class="col-3 col-sm-12 mb-3">
                        <select name="status" class="form-control form-select">
                            <option value="">جميع الحالات</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>نشط</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>غير نشط</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>في الانتظار</option>
                        </select>
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <button type="submit" class="btn btn-primary w-100">بحث</button>
                    </div>
                </form>
            </div>
            
            <!-- جدول المستخدمين -->
            <div class="card">
                <div class="card-header">
                    <h3>قائمة المستخدمين (<?php echo $totalUsers; ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($users)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>المعرف</th>
                                        <th>الاسم الكامل</th>
                                        <th>اسم المستخدم</th>
                                        <th>البريد الإلكتروني</th>
                                        <th>النوع</th>
                                        <th>الحالة</th>
                                        <th>تاريخ التسجيل</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo escape($user['full_name']); ?></td>
                                            <td><?php echo escape($user['username']); ?></td>
                                            <td><?php echo escape($user['email']); ?></td>
                                            <td>
                                                <?php
                                                $typeLabels = [
                                                    'donor' => 'متبرع',
                                                    'beneficiary' => 'مستفيد',
                                                    'charity' => 'جمعية خيرية',
                                                    'admin' => 'مدير'
                                                ];
                                                $typeColors = [
                                                    'donor' => 'success',
                                                    'beneficiary' => 'primary',
                                                    'charity' => 'warning',
                                                    'admin' => 'danger'
                                                ];
                                                ?>
                                                <span class="badge badge-<?php echo $typeColors[$user['user_type']]; ?>">
                                                    <?php echo $typeLabels[$user['user_type']]; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusLabels = [
                                                    'active' => 'نشط',
                                                    'inactive' => 'غير نشط',
                                                    'pending' => 'في الانتظار'
                                                ];
                                                $statusColors = [
                                                    'active' => 'success',
                                                    'inactive' => 'secondary',
                                                    'pending' => 'warning'
                                                ];
                                                ?>
                                                <span class="badge badge-<?php echo $statusColors[$user['status']]; ?>">
                                                    <?php echo $statusLabels[$user['status']]; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <?php if ($user['user_type'] !== 'admin'): ?>
                                                    <div class="btn-group" style="display: flex; gap: 0.25rem;">
                                                        <?php if ($user['status'] !== 'active'): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="activate">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" class="btn btn-success btn-sm">تفعيل</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="deactivate">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" class="btn btn-warning btn-sm">إلغاء تفعيل</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <form method="POST" style="display: inline;" 
                                                              onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">مدير</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
                        <div class="text-center p-4">
                            <p class="text-muted">لا توجد مستخدمين</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
