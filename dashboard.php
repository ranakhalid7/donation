<?php
require_once 'config.php';

$pageTitle = 'لوحة التحكم';
$pageDescription = 'إدارة حسابك ومتابعة نشاطك على المنصة';

checkLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];

// إحصائيات حسب نوع المستخدم
$stats = [];

if ($userType === 'donor') {
    // إحصائيات المتبرع
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_donations,
            COUNT(CASE WHEN status = 'available' THEN 1 END) as available_donations,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_donations,
            COUNT(CASE WHEN status = 'reserved' THEN 1 END) as reserved_donations
        FROM donations WHERE donor_id = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    
    // أحدث التبرعات
    $recentStmt = $db->prepare("
        SELECT * FROM donations 
        WHERE donor_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recentStmt->execute([$userId]);
    $recentDonations = $recentStmt->fetchAll();
    
} elseif ($userType === 'beneficiary') {
    // إحصائيات المستفيد
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_requests
        FROM donation_requests 
        WHERE requester_id = ?
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    
    // أحدث الطلبات
    $recentStmt = $db->prepare("
        SELECT dr.*, d.title, d.description, u.full_name as donor_name
        FROM donation_requests dr
        JOIN donations d ON dr.donation_id = d.id
        JOIN users u ON d.donor_id = u.id
        WHERE dr.requester_id = ?
        ORDER BY dr.created_at DESC
        LIMIT 5
    ");
    $recentStmt->execute([$userId]);
    $recentRequests = $recentStmt->fetchAll();
    
} elseif ($userType === 'charity') {
    // إحصائيات الجمعية
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_donations
        FROM donations 
        WHERE charity_id = (SELECT id FROM charities WHERE user_id = ?)
    ");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch();
    
    // معلومات الجمعية
    $charityStmt = $db->prepare("SELECT * FROM charities WHERE user_id = ?");
    $charityStmt->execute([$userId]);
    $charity = $charityStmt->fetch();
    
} elseif ($userType === 'admin') {
    // إحصائيات المدير
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users,
            (SELECT COUNT(*) FROM donations) as total_donations,
            (SELECT COUNT(*) FROM charities WHERE verified = 1) as verified_charities,
            (SELECT COUNT(*) FROM users WHERE status = 'pending') as pending_users
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
}

// الإشعارات
$notificationsStmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 5
");
$notificationsStmt->execute([$userId]);
$notifications = $notificationsStmt->fetchAll();
?>

<?php require_once 'includes/header.php'; ?>

    <!-- لوحة التحكم -->
    <section class="dashboard" style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <div class="row">
                <!-- الشريط الجانبي -->
                <div class="col-3 col-sm-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h3>القائمة الجانبية</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="sidebar-menu">
                                <a href="dashboard.php" class="sidebar-link active">
                                    <span>الرئيسية</span>
                                </a>
                                
                                <?php if ($userType === 'donor'): ?>
                                    <a href="my-donations.php" class="sidebar-link">
                                        <span>تبرعاتي</span>
                                    </a>
                                    <a href="add-donation.php" class="sidebar-link">
                                        <span>إضافة تبرع جديد</span>
                                    </a>
                                    <a href="donation-requests.php" class="sidebar-link">
                                        <span>طلبات التبرع</span>
                                    </a>
                                    
                                <?php elseif ($userType === 'beneficiary'): ?>
                                    <a href="donations.php" class="sidebar-link">
                                        <span>البحث عن تبرعات</span>
                                    </a>
                                    <a href="my-requests.php" class="sidebar-link">
                                        <span>طلباتي</span>
                                    </a>
                                    
                                <?php elseif ($userType === 'charity'): ?>
                                    <a href="charity-donations.php" class="sidebar-link">
                                        <span>التبرعات المستلمة</span>
                                    </a>
                                    <a href="charity-profile.php" class="sidebar-link">
                                        <span>ملف الجمعية</span>
                                    </a>
                                    
                                <?php elseif ($userType === 'admin'): ?>
                                    <a href="admin-users.php" class="sidebar-link">
                                        <span>إدارة المستخدمين</span>
                                    </a>
                                    <a href="admin-donations.php" class="sidebar-link">
                                        <span>إدارة التبرعات</span>
                                    </a>
                                    <a href="admin-charities.php" class="sidebar-link">
                                        <span>إدارة الجمعيات</span>
                                    </a>
                                    <a href="admin-reports.php" class="sidebar-link">
                                        <span>التقارير</span>
                                    </a>
                                <?php endif; ?>
                                
                                <a href="notifications.php" class="sidebar-link">
                                    <span>الإشعارات</span>
                                    <?php if (count($notifications) > 0): ?>
                                        <span class="badge badge-danger"><?php echo count($notifications); ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="profile.php" class="sidebar-link">
                                    <span>الملف الشخصي</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- المحتوى الرئيسي -->
                <div class="col-9 col-sm-12">
                    <!-- ترحيب -->
                    <div class="welcome-card mb-4" style="background: linear-gradient(135deg, var(--secondary-color), #3498db); color: white; padding: 2rem; border-radius: var(--border-radius);">
                        <!-- <h2>مرحباً بك، <?php echo escape($_SESSION['full_name']); ?></h2> -->
                        <p style="opacity: 0.9; margin-bottom: 0;">
                            <?php 
                            switch ($userType) {
                                case 'donor':
                                    echo 'شكراً لك على كرمك وعطائك. يمكنك إدارة تبرعاتك ومتابعة طلبات المستفيدين من هنا.';
                                    break;
                                case 'beneficiary':
                                    echo 'نتمنى أن تجد ما تحتاجه. يمكنك البحث عن التبرعات وإرسال الطلبات من هنا.';
                                    break;
                                case 'charity':
                                    echo 'نقدر عملكم الخيري النبيل. يمكنكم إدارة التبرعات والمستفيدين من هنا.';
                                    break;
                                case 'admin':
                                    echo 'مرحباً بك في لوحة تحكم المدير. يمكنك إدارة الموقع بالكامل من هنا.';
                                    break;
                            }
                            ?>
                        </p>
                    </div>
                    
                    <!-- الإحصائيات -->
                    <div class="stats-grid mb-4">
                        <div class="row">
                            <?php if ($userType === 'donor'): ?>
                                <div class="col-3 col-sm-12 mb-3">
                                    <div class="stats-card">
                                        <div class="stats-number"><?php echo $stats['total_donations']; ?></div>
                                        <div class="stats-label">إجمالي التبرعات</div>
                                    </div>
                                </div>
                                <div class="col-3 col-sm-12 mb-3">
                                    <div class="stats-card" style="background: linear-gradient(135deg, var(--success-color), #27ae60);">
                                        <div class="stats-number"><?php echo $stats['available_donations']; ?></div>
                                        <div class="stats-label">تبرعات متاحة</div>
                                    </div>
                                </div>
                                <div class="col-3 col-sm-12 mb-3">
                                    <div class="stats-card" style="background: linear-gradient(135deg, var(--warning-color), #f39c12);">
                                        <div class="stats-number"><?php echo $stats['reserved_donations']; ?></div>
                                        <div class="stats-label">تبرعات محجوزة</div>
                                    </div>
                                </div>
                                <div class="col-3 col-sm-12 mb-3">
                                    <div class="stats-card" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                                        <div class="stats-number"><?php echo $stats['completed_donations']; ?></div>
                                        <div class="stats-label">تبرعات مكتملة</div>
                                    </div>
                                </div>
                                
                            <?php elseif ($userType === 'beneficiary'): ?>
                                <div class="col-4 col-sm-12 mb-3">
                                    <div class="stats-card">
                                        <div class="stats-number"><?php echo $stats['total_requests']; ?></div>
                                        <div class="stats-label">إجمالي الطلبات</div>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-12 mb-3">
                                    <div class="stats-card" style="background: linear-gradient(135deg, var(--success-color), #27ae60);">
                                        <div class="stats-number">
                                            <?php 
                                            $approvedStmt = $db->prepare("SELECT COUNT(*) FROM donation_requests WHERE requester_id = ? AND status = 'approved'");
                                            $approvedStmt->execute([$userId]);
                                            echo $approvedStmt->fetchColumn();
                                            ?>
                                        </div>
                                        <div class="stats-label">طلبات مقبولة</div>
                                    </div>
                                </div>
                                <div class="col-4 col-sm-12 mb-3">
                                    <div class="stats-card" style="background: linear-gradient(135deg, var(--warning-color), #f39c12);">
                                        <div class="stats-number">
                                            <?php 
                                            $pendingStmt = $db->prepare("SELECT COUNT(*) FROM donation_requests WHERE requester_id = ? AND status = 'pending'");
                                            $pendingStmt->execute([$userId]);
                                            echo $pendingStmt->fetchColumn();
                                            ?>
                                        </div>
                                        <div class="stats-label">طلبات معلقة</div>
                                    </div>
                                </div>
                                
                            <?php elseif ($userType === 'admin'): ?>
                                <div class="col-3 col-sm-12 mb-3">
                                    <div class="stats-card">
                                        <div class="stats-number"><?php echo $stats['total_users']; ?></div>
                                        <div class="stats-label">المستخدمين النشطين</div>
                                    </div>
                                </div>
                                <div class="col-3 col-sm-12 mb-3">
                                    <div class="stats-card" style="background: linear-gradient(135deg, var(--success-color), #27ae60);">
                                        <div class="stats-number"><?php echo $stats['total_donations']; ?></div>
                                        <div class="stats-label">إجمالي التبرعات</div>
                                    </div>
                                </div>
                                <div class="col-3 col-sm-12 mb-3">
                                    <div class="stats-card" style="background: linear-gradient(135deg, var(--warning-color), #f39c12);">
                                        <div class="stats-number"><?php echo $stats['verified_charities']; ?></div>
                                        <div class="stats-label">جمعيات معتمدة</div>
                                    </div>
                                </div>
                                <div class="col-3 col-sm-12 mb-3">
                                    <div class="stats-card" style="background: linear-gradient(135deg, var(--danger-color), #c0392b);">
                                        <div class="stats-number"><?php echo $stats['pending_users']; ?></div>
                                        <div class="stats-label">حسابات معلقة</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- المحتوى الأساسي -->
                        <div class="col-8 col-sm-12 mb-4">
                            <?php if ($userType === 'donor' && !empty($recentDonations)): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h3>أحدث تبرعاتك</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>العنوان</th>
                                                        <th>الفئة</th>
                                                        <th>الحالة</th>
                                                        <th>التاريخ</th>
                                                        <th>الإجراءات</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentDonations as $donation): ?>
                                                        <tr>
                                                            <td><?php echo escape($donation['title']); ?></td>
                                                            <td>
                                                                <span class="badge badge-primary"><?php echo escape($donation['category']); ?></span>
                                                            </td>
                                                            <td>
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
                                                                <span class="badge badge-<?php echo $statusColors[$donation['status']]; ?>">
                                                                    <?php echo $statusLabels[$donation['status']]; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('Y-m-d', strtotime($donation['created_at'])); ?></td>
                                                            <td>
                                                                <a href="donation-details.php?id=<?php echo $donation['id']; ?>" 
                                                                   class="btn btn-sm btn-primary">عرض</a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="text-center mt-3">
                                            <a href="my-donations.php" class="btn btn-primary">عرض جميع التبرعات</a>
                                        </div>
                                    </div>
                                </div>
                                
                            <?php elseif ($userType === 'beneficiary' && !empty($recentRequests)): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h3>أحدث طلباتك</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>التبرع</th>
                                                        <th>المتبرع</th>
                                                        <th>الحالة</th>
                                                        <th>التاريخ</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentRequests as $request): ?>
                                                        <tr>
                                                            <td><?php echo escape($request['title']); ?></td>
                                                            <td><?php echo escape($request['donor_name']); ?></td>
                                                            <td>
                                                                <?php
                                                                $statusColors = [
                                                                    'pending' => 'warning',
                                                                    'approved' => 'success',
                                                                    'rejected' => 'danger'
                                                                ];
                                                                $statusLabels = [
                                                                    'pending' => 'معلق',
                                                                    'approved' => 'مقبول',
                                                                    'rejected' => 'مرفوض'
                                                                ];
                                                                ?>
                                                                <span class="badge badge-<?php echo $statusColors[$request['status']]; ?>">
                                                                    <?php echo $statusLabels[$request['status']]; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('Y-m-d', strtotime($request['created_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="text-center mt-3">
                                            <a href="my-requests.php" class="btn btn-primary">عرض جميع الطلبات</a>
                                        </div>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h3>ابدأ رحلتك في العطاء</h3>
                                        <p class="text-muted mb-4">
                                            <?php 
                                            if ($userType === 'donor') {
                                                echo 'لم تقم بإضافة أي تبرعات بعد. ابدأ بإضافة تبرعك الأول وشارك في الخير.';
                                            } elseif ($userType === 'beneficiary') {
                                                echo 'لم تقم بإرسال أي طلبات بعد. تصفح التبرعات المتاحة وأرسل طلبك.';
                                            }
                                            ?>
                                        </p>
                                        <?php if ($userType === 'donor'): ?>
                                            <a href="add-donation.php" class="btn btn-primary">إضافة تبرع جديد</a>
                                        <?php elseif ($userType === 'beneficiary'): ?>
                                            <a href="donations.php" class="btn btn-primary">تصفح التبرعات</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- الشريط الجانبي للإشعارات -->
                        <div class="col-4 col-sm-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3>الإشعارات الأخيرة</h3>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($notifications)): ?>
                                        <?php foreach ($notifications as $notification): ?>
                                            <div class="notification-item mb-3 p-3" style="border: 1px solid #dee2e6; border-radius: var(--border-radius); background: #f8f9fa;">
                                                <h5 class="mb-1"><?php echo escape($notification['title']); ?></h5>
                                                <p class="mb-2 text-muted" style="font-size: 0.9rem;">
                                                    <?php echo escape($notification['message']); ?>
                                                </p>
                                                <small class="text-muted">
                                                    <?php echo date('Y-m-d H:i', strtotime($notification['created_at'])); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="text-center">
                                            <a href="notifications.php" class="btn btn-sm btn-outline">عرض جميع الإشعارات</a>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center">لا توجد إشعارات جديدة</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>

    <style>
        .sidebar-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            color: var(--dark-color);
            text-decoration: none;
            border-bottom: 1px solid #dee2e6;
            transition: background-color 0.3s;
        }
        
        .sidebar-link:hover,
        .sidebar-link.active {
            background-color: var(--light-color);
            color: var(--secondary-color);
        }
        
        .sidebar-link:last-child {
            border-bottom: none;
        }
        
        @media (max-width: 768px) {
            .sidebar-menu {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .sidebar-link {
                flex: 1 1 auto;
                border: 1px solid #dee2e6;
                border-radius: var(--border-radius);
                text-align: center;
                padding: 0.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
</body>
</html>