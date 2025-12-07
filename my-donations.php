<?php
require_once 'config.php';
checkLogin();
checkUserType(['donor']);

$pageTitle = 'تبرعاتي';
$pageDescription = 'إدارة ومتابعة التبرعات الخاصة بك';

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// معالجة الحذف
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_donation'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $donationId = intval($_POST['donation_id']);
        
        // التحقق من ملكية التبرع
        $checkStmt = $db->prepare("SELECT id, status FROM donations WHERE id = ? AND donor_id = ?");
        $checkStmt->execute([$donationId, $userId]);
        $donation = $checkStmt->fetch();
        
        if ($donation && $donation['status'] === 'available') {
            try {
                $deleteStmt = $db->prepare("UPDATE donations SET status = 'cancelled' WHERE id = ?");
                $deleteStmt->execute([$donationId]);
                $success = "تم إلغاء التبرع بنجاح";
            } catch (PDOException $e) {
                $error = "حدث خطأ أثناء الإلغاء";
            }
        }
    }
}

// الفلترة
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// بناء الاستعلام
$sql = "SELECT d.*, 
        (SELECT COUNT(*) FROM donation_requests WHERE donation_id = d.id) as requests_count
        FROM donations d 
        WHERE d.donor_id = ?";

$params = [$userId];

if ($status !== 'all') {
    $sql .= " AND d.status = ?";
    $params[] = $status;
}

if ($category !== 'all') {
    $sql .= " AND d.category = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $sql .= " AND (d.title LIKE ? OR d.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY d.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$donations = $stmt->fetchAll();

// إحصائيات
$statsStmt = $db->prepare("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'pending_charity_approval' THEN 1 END) as pending_approval,
        COUNT(CASE WHEN status = 'available' THEN 1 END) as available,
        COUNT(CASE WHEN status = 'reserved' THEN 1 END) as reserved,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled
    FROM donations
    WHERE donor_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();
 ?>
<?php require_once 'includes/header.php'; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
        }

        .stat-icon {
            font-size: 2.5rem;
        }

        .stat-details h3 {
            font-size: 2rem;
            color: var(--primary-color);
            margin: 0;
        }

        .stat-details p {
            margin: 0.25rem 0 0 0;
            color: #666;
            font-size: 0.9rem;
        }

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }

        .filters-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.65rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.95rem;
        }

        .search-group {
            flex: 2;
            min-width: 300px;
            flex-direction: row;
            align-items: stretch;
        }

        .search-group input {
            flex: 1;
        }

        .donations-grid {
            display: grid;
            gap: 1.5rem;
        }

        .donation-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
        }

        .donation-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-4px);
        }

        .donation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-bottom: 2px solid var(--border-color);
        }

        .donation-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.3rem;
        }

        .donation-body {
            padding: 1.5rem;
        }

        .donation-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .donation-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .donation-meta span {
            font-size: 0.9rem;
            color: #666;
        }

        .donation-info {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            font-size: 0.9rem;
            color: #666;
        }

        .requests-badge {
            background: var(--warning-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }

        .donation-actions {
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
        }

        .empty-state p {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                font-size: 2rem;
            }

            .stat-details h3 {
                font-size: 1.5rem;
            }

            .filters-form {
                flex-direction: column;
            }

            .filter-group,
            .search-group {
                width: 100%;
                min-width: auto;
            }

            .search-group {
                flex-direction: column;
                gap: 0.5rem;
            }

            .donation-header {
                flex-direction: column;
                gap: 0.75rem;
                align-items: stretch;
            }

            .donation-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .donation-info {
                flex-direction: column;
                gap: 0.75rem;
            }

            .donation-actions {
                flex-direction: column;
            }

            .donation-actions .btn,
            .donation-actions form {
                width: 100%;
            }

            .donation-actions button {
                width: 100%;
            }
        }
    </style>

    <!-- Main Content -->
    <section style="padding: 2rem 0; min-height: 70vh;">
    <div class="container">
        <div class="page-header mb-4 d-flex justify-content-between align-items-center" style="flex-wrap: wrap; gap: 1rem;">
            <h1 style="color: var(--primary-color); margin: 0;">تبرعاتي</h1>
            <a href="add-donation.php" class="btn btn-primary">إضافة تبرع جديد</a>
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
                <div class="stat-icon">📦</div>
                <div class="stat-details">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>إجمالي التبرعات</p>
                </div>
            </div>
            <?php if ($stats['pending_approval'] > 0): ?>
            <div class="stat-card" style="border-right: 4px solid #ffc107;">
                <div class="stat-icon">⏳</div>
                <div class="stat-details">
                    <h3><?php echo $stats['pending_approval']; ?></h3>
                    <p>في انتظار الموافقة</p>
                </div>
            </div>
            <?php endif; ?>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-details">
                    <h3><?php echo $stats['available']; ?></h3>
                    <p>متاح</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔒</div>
                <div class="stat-details">
                    <h3><?php echo $stats['reserved']; ?></h3>
                    <p>محجوز</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✔️</div>
                <div class="stat-details">
                    <h3><?php echo $stats['completed']; ?></h3>
                    <p>مكتمل</p>
                </div>
            </div>
        </div>

        <!-- الفلاتر والبحث -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>الحالة:</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>الكل</option>
                        <option value="pending_charity_approval" <?php echo $status === 'pending_charity_approval' ? 'selected' : ''; ?>>في انتظار الموافقة</option>
                        <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>متاح</option>
                        <option value="reserved" <?php echo $status === 'reserved' ? 'selected' : ''; ?>>محجوز</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>مكتمل</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>ملغي</option>
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

                <div class="filter-group search-group">
                    <input type="text" name="search" placeholder="البحث..." 
                           value="<?php echo escape($search); ?>">
                    <button type="submit" class="btn btn-primary">بحث</button>
                    <?php if ($status !== 'all' || $category !== 'all' || !empty($search)): ?>
                        <a href="my-donations.php" class="btn btn-secondary">إعادة تعيين</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- قائمة التبرعات -->
        <?php if (empty($donations)): ?>
            <div class="empty-state">
                <p>لا توجد تبرعات بعد</p>
                <a href="add-donation.php" class="btn btn-primary">إضافة تبرع جديد</a>
            </div>
        <?php else: ?>
            <div class="donations-grid">
                <?php foreach ($donations as $donation): ?>
                <div class="donation-card">
                    <div class="donation-header">
                        <h3><?php echo escape($donation['title']); ?></h3>
                        <span class="badge badge-<?php echo $donation['status']; ?>">
                            <?php
                            $statusText = [
                                'pending_charity_approval' => 'في انتظار الموافقة',
                                'available' => 'متاح',
                                'reserved' => 'محجوز',
                                'with_charity' => 'مع الجمعية',
                                'delivered' => 'موزع',
                                'completed' => 'مكتمل',
                                'cancelled' => 'ملغي'
                            ];
                            echo isset($statusText[$donation['status']]) ? $statusText[$donation['status']] : $donation['status'];
                            ?>
                        </span>
                    </div>
                    
                    <div class="donation-body">
                        <p class="donation-description">
                            <?php echo escape(substr($donation['description'], 0, 100)) . (strlen($donation['description']) > 100 ? '...' : ''); ?>
                        </p>
                        
                        <div class="donation-meta">
                            <span><strong>الفئة:</strong> <?php echo escape($donation['category']); ?></span>
                            <span><strong>الحالة:</strong> <?php echo escape($donation['condition_item']); ?></span>
                            <span><strong>الكمية:</strong> <?php echo escape($donation['quantity']); ?></span>
                        </div>
                        
                        <div class="donation-info">
                            <span>📍 <?php echo escape($donation['pickup_location']); ?></span>
                            <span>📅 <?php echo date('Y-m-d', strtotime($donation['created_at'])); ?></span>
                            <?php if ($donation['requests_count'] > 0): ?>
                            <span class="requests-badge">📨 <?php echo $donation['requests_count']; ?> طلب</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="donation-actions">
                        <a href="donation-details.php?id=<?php echo $donation['id']; ?>" 
                           class="btn btn-sm btn-primary">
                            عرض التفاصيل
                        </a>
                        
                        <?php if ($donation['status'] === 'available'): ?>
                        <a href="edit-donation.php?id=<?php echo $donation['id']; ?>" 
                           class="btn btn-sm btn-secondary">
                            تعديل
                        </a>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="donation_id" value="<?php echo $donation['id']; ?>">
                            <button type="submit" name="delete_donation" 
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('هل أنت متأكد من إلغاء هذا التبرع؟')">
                                إلغاء
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    </section>

<?php require_once 'includes/footer.php'; ?>