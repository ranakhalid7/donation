<?php
require_once 'config.php';

$pageTitle = 'التبرعات المستلمة';
$pageDescription = 'إدارة ومتابعة التبرعات المخصصة للجمعية';

checkLogin();
checkUserType(['charity']);

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// جلب معلومات الجمعية
$charityStmt = $db->prepare("SELECT * FROM charities WHERE user_id = ?");
$charityStmt->execute([$userId]);
$charity = $charityStmt->fetch();

if (!$charity) {
    header('Location: dashboard.php');
    exit();
}

// فلترة التبرعات
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// بناء الاستعلام
$whereConditions = ["charity_id = ?"];
$params = [$charity['id']];

if (!empty($status)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
}

if (!empty($category)) {
    $whereConditions[] = "category = ?";
    $params[] = $category;
}

$whereClause = implode(' AND ', $whereConditions);

// عدد النتائج الإجمالي
$countSql = "SELECT COUNT(*) FROM donations WHERE $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalResults = $countStmt->fetchColumn();
$totalPages = ceil($totalResults / $limit);

// جلب التبرعات
$sql = "
    SELECT d.*, u.full_name as donor_name, u.phone as donor_phone, u.email as donor_email,
           (SELECT COUNT(*) FROM donation_requests WHERE donation_id = d.id) as requests_count
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE $whereClause
    ORDER BY d.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$donations = $stmt->fetchAll();

// إحصائيات سريعة
$statsStmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'available' THEN 1 END) as available,
        COUNT(CASE WHEN status = 'reserved' THEN 1 END) as reserved,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
    FROM donations WHERE charity_id = ?
");
$statsStmt->execute([$charity['id']]);
$stats = $statsStmt->fetch();
?>

<?php require_once 'includes/header.php'; ?>

    <!-- صفحة التبرعات المستلمة -->
    <section class="charity-donations" style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <!-- عنوان الصفحة -->
            <div class="page-header mb-4">
                <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">التبرعات المستلمة</h1>
                <p class="text-muted">إدارة ومتابعة التبرعات المخصصة لجمعية <?php echo escape($charity['charity_name']); ?></p>
            </div>
            
            <!-- إحصائيات سريعة -->
            <div class="stats-grid mb-4">
                <div class="grid grid-4">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $stats['total']; ?></div>
                        <div class="stats-label">إجمالي التبرعات</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--success-color), #27ae60);">
                        <div class="stats-number"><?php echo $stats['available']; ?></div>
                        <div class="stats-label">متاحة</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--warning-color), #f39c12);">
                        <div class="stats-number"><?php echo $stats['reserved']; ?></div>
                        <div class="stats-label">محجوزة</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                        <div class="stats-number"><?php echo $stats['completed']; ?></div>
                        <div class="stats-label">مكتملة</div>
                    </div>
                </div>
            </div>
            
            <!-- معلومات الجمعية -->
            <div class="charity-info mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3><?php echo escape($charity['charity_name']); ?></h3>
                        <?php if ($charity['verified']): ?>
                            <span class="badge badge-success" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                ✓ جمعية معتمدة
                            </span>
                        <?php else: ?>
                            <span class="badge badge-warning" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                ⏳ قيد المراجعة
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p><?php echo escape($charity['description']); ?></p>
                        <div class="row">
                            <!-- <div class="col-6 col-sm-12">
                                <strong>العنوان:</strong> <?php echo escape($charity['address']); ?>
                            </div> -->
                            <div class="col-6 col-sm-12">
                                <strong>رقم الترخيص:</strong> <?php echo escape($charity['license_number']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- فلاتر البحث -->
            <div class="search-filters mb-4">
                <form method="GET" class="row">
                    <div class="col-4 col-sm-12 mb-3">
                        <select name="status" class="form-control form-select">
                            <option value="">جميع الحالات</option>
                            <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>متاحة</option>
                            <option value="reserved" <?php echo $status === 'reserved' ? 'selected' : ''; ?>>محجوزة</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>مكتملة</option>
                        </select>
                    </div>
                    <div class="col-4 col-sm-12 mb-3">
                        <select name="category" class="form-control form-select">
                            <option value="">جميع الفئات</option>
                            <option value="clothing" <?php echo $category === 'clothing' ? 'selected' : ''; ?>>ملابس</option>
                            <option value="furniture" <?php echo $category === 'furniture' ? 'selected' : ''; ?>>أثاث</option>
                            <option value="electronics" <?php echo $category === 'electronics' ? 'selected' : ''; ?>>إلكترونيات</option>
                            <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>أخرى</option>
                        </select>
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <button type="submit" class="btn btn-primary w-100">فلترة</button>
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <a href="charity-donations.php" class="btn btn-secondary w-100">مسح الفلاتر</a>
                    </div>
                </form>
            </div>
            
            <!-- عرض التبرعات -->
            <?php if (!empty($donations)): ?>
                <div class="donations-list">
                    <?php foreach ($donations as $donation): ?>
                        <div class="card donation-card mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-2 col-sm-12 mb-3">
                                        <?php 
                                        $images = json_decode($donation['images'], true);
                                        $firstImage = !empty($images) ? $images[0] : 'images/default-donation.jpg';
                                        ?>
                                        <img src="<?php echo escape($firstImage); ?>" 
                                             alt="<?php echo escape($donation['title']); ?>" 
                                             class="img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                                    </div>
                                    <div class="col-6 col-sm-12 mb-3">
                                        <h4 class="mb-2"><?php echo escape($donation['title']); ?></h4>
                                        <p class="text-muted mb-2"><?php echo escape(substr($donation['description'], 0, 150)); ?>...</p>
                                        <div class="donation-meta">
                                            <span class="badge badge-primary"><?php echo escape($donation['category']); ?></span>
                                            <span class="badge badge-success"><?php echo escape($donation['condition_item']); ?></span>
                                            <small class="text-muted">الكمية: <?php echo $donation['quantity']; ?></small>
                                        </div>
                                        <div class="donor-info mt-2">
                                            <strong>المتبرع:</strong> <?php echo escape($donation['donor_name']); ?><br>
                                            <strong>الهاتف:</strong> <?php echo escape($donation['donor_phone']); ?><br>
                                            <strong>البريد:</strong> <?php echo escape($donation['donor_email']); ?>
                                        </div>
                                    </div>
                                    <div class="col-2 col-sm-12 mb-3 text-center">
                                        <?php
                                        $statusColors = [
                                            'available' => 'success',
                                            'reserved' => 'warning',
                                            'with_charity' => 'warning',
                                            'delivered' => 'info',
                                            'completed' => 'primary',
                                            'cancelled' => 'danger'
                                        ];
                                        $statusLabels = [
                                            'available' => 'متاحة',
                                            'reserved' => 'محجوزة',
                                            'with_charity' => 'مع الجمعية',
                                            'delivered' => 'موزعة',
                                            'completed' => 'مكتملة',
                                            'cancelled' => 'ملغية'
                                        ];
                                        ?>
                                        <span class="badge badge-<?php echo $statusColors[$donation['status']]; ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                            <?php echo $statusLabels[$donation['status']]; ?>
                                        </span>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <?php echo date('Y-m-d', strtotime($donation['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-2 col-sm-12 text-center">
                                        <div class="btn-group-vertical w-100">
                                            <div class="mb-2">
                                                <strong>موقع الاستلام:</strong><br>
                                                <small><?php echo escape($donation['pickup_location']); ?></small>
                                            </div>
                                            
                                            <?php if ($donation['requests_count'] > 0): ?>
                                                <div class="alert alert-info p-2 mb-2" style="font-size: 0.8rem;">
                                                    <?php echo $donation['requests_count']; ?> طلب استلام
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="contact-donor">
                                                <a href="tel:<?php echo escape($donation['donor_phone']); ?>" 
                                                   class="btn btn-success btn-sm mb-1 w-100">
                                                    اتصال
                                                </a>
                                                <a href="mailto:<?php echo escape($donation['donor_email']); ?>" 
                                                   class="btn btn-primary btn-sm w-100">
                                                    مراسلة
                                                </a>
                                            </div>
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
                <!-- رسالة عدم وجود تبرعات -->
                <div class="no-donations text-center" style="padding: 4rem 0;">
                    <div style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;">📦</div>
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">لا توجد تبرعات</h3>
                    <p class="text-muted mb-4">
                        <?php if (!empty($status) || !empty($category)): ?>
                            لا توجد تبرعات تطابق المعايير المحددة.
                        <?php else: ?>
                            لم يتم تخصيص أي تبرعات لجمعيتكم بعد.
                        <?php endif; ?>
                    </p>
                    
                    <?php if (!empty($status) || !empty($category)): ?>
                        <a href="charity-donations.php" class="btn btn-secondary">مسح الفلاتر</a>
                    <?php else: ?>
                        <a href="donations.php" class="btn btn-primary">تصفح التبرعات المتاحة</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
