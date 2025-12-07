<?php
require_once 'config.php';

$pageTitle = 'التقارير والإحصائيات';
$pageDescription = 'تقارير شاملة عن نشاط المنصة والمستخدمين';

checkLogin();
checkUserType(['admin']);

$db = Database::getInstance();

// تحديد الفترة الزمنية للتقرير
$period = $_GET['period'] ?? 'month';
$customStart = $_GET['start_date'] ?? '';
$customEnd = $_GET['end_date'] ?? '';

// تحديد التواريخ حسب الفترة
switch ($period) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('-1 week'));
        $endDate = date('Y-m-d');
        break;
    case 'month':
        $startDate = date('Y-m-d', strtotime('-1 month'));
        $endDate = date('Y-m-d');
        break;
    case 'quarter':
        $startDate = date('Y-m-d', strtotime('-3 months'));
        $endDate = date('Y-m-d');
        break;
    case 'year':
        $startDate = date('Y-m-d', strtotime('-1 year'));
        $endDate = date('Y-m-d');
        break;
    case 'custom':
        $startDate = $customStart ?: date('Y-m-d', strtotime('-1 month'));
        $endDate = $customEnd ?: date('Y-m-d');
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-1 month'));
        $endDate = date('Y-m-d');
}

// إحصائيات عامة للفترة المحددة
$generalStatsStmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?) as new_users,
        (SELECT COUNT(*) FROM donations WHERE created_at BETWEEN ? AND ?) as new_donations,
        (SELECT COUNT(*) FROM donations WHERE status = 'completed' AND updated_at BETWEEN ? AND ?) as completed_donations,
        (SELECT COUNT(*) FROM donation_requests WHERE created_at BETWEEN ? AND ?) as new_requests,
        (SELECT COUNT(*) FROM charities WHERE created_at BETWEEN ? AND ?) as new_charities
");
$generalStatsStmt->execute([$startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate]);
$generalStats = $generalStatsStmt->fetch();

// إحصائيات المستخدمين حسب النوع
$userStatsStmt = $db->prepare("
    SELECT 
        user_type,
        COUNT(*) as count
    FROM users 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY user_type
");
$userStatsStmt->execute([$startDate, $endDate]);
$userStats = $userStatsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// إحصائيات التبرعات حسب الفئة
$donationCategoryStatsStmt = $db->prepare("
    SELECT 
        category,
        COUNT(*) as count
    FROM donations 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY category
    ORDER BY count DESC
");
$donationCategoryStatsStmt->execute([$startDate, $endDate]);
$donationCategoryStats = $donationCategoryStatsStmt->fetchAll();

// إحصائيات حالة التبرعات
$donationStatusStatsStmt = $db->prepare("
    SELECT 
        status,
        COUNT(*) as count
    FROM donations 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY status
");
$donationStatusStatsStmt->execute([$startDate, $endDate]);
$donationStatusStats = $donationStatusStatsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// أكثر المتبرعين نشاطاً
$topDonorsStmt = $db->prepare("
    SELECT 
        u.full_name,
        u.email,
        COUNT(d.id) as donations_count,
        COUNT(CASE WHEN d.status = 'completed' THEN 1 END) as completed_count
    FROM users u
    JOIN donations d ON u.id = d.donor_id
    WHERE d.created_at BETWEEN ? AND ?
    GROUP BY u.id, u.full_name, u.email
    ORDER BY donations_count DESC
    LIMIT 10
");
$topDonorsStmt->execute([$startDate, $endDate]);
$topDonors = $topDonorsStmt->fetchAll();

// النشاط اليومي للفترة المحددة
$dailyActivityStmt = $db->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as donations_count,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) = DATE(d.created_at) AND created_at BETWEEN ? AND ?) as users_count
    FROM donations d
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 30
");
$dailyActivityStmt->execute([$startDate, $endDate, $startDate, $endDate]);
$dailyActivity = $dailyActivityStmt->fetchAll();

// إجمالي الإحصائيات (كل الأوقات)
$totalStatsStmt = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM donations) as total_donations,
        (SELECT COUNT(*) FROM charities) as total_charities,
        (SELECT COUNT(*) FROM donation_requests) as total_requests
");
$totalStatsStmt->execute();
$totalStats = $totalStatsStmt->fetch();
?>

<?php require_once 'includes/header.php'; ?>

    <!-- صفحة التقارير -->
    <section class="admin-reports" style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <!-- عنوان الصفحة -->
            <div class="page-header mb-4">
                <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">التقارير والإحصائيات</h1>
                <p class="text-muted">تقارير شاملة عن نشاط المنصة والمستخدمين</p>
            </div>
            
            <!-- فلاتر الفترة الزمنية -->
            <div class="period-filters mb-4">
                <form method="GET" class="row">
                    <div class="col-3 col-sm-12 mb-3">
                        <select name="period" class="form-control form-select" onchange="toggleCustomDates()">
                            <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>آخر أسبوع</option>
                            <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>آخر شهر</option>
                            <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>آخر 3 أشهر</option>
                            <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>آخر سنة</option>
                            <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>فترة مخصصة</option>
                        </select>
                    </div>
                    
                    <div id="custom-dates" style="display: <?php echo $period === 'custom' ? 'flex' : 'none'; ?>; gap: 1rem;">
                        <div class="col-3 col-sm-12 mb-3">
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?php echo escape($customStart); ?>" placeholder="تاريخ البداية">
                        </div>
                        <div class="col-3 col-sm-12 mb-3">
                            <input type="date" name="end_date" class="form-control" 
                                   value="<?php echo escape($customEnd); ?>" placeholder="تاريخ النهاية">
                        </div>
                    </div>
                    
                    <div class="col-3 col-sm-12 mb-3">
                        <button type="submit" class="btn btn-primary">إنشاء التقرير</button>
                    </div>
                </form>
            </div>
            
            <!-- معلومات الفترة -->
            <div class="alert alert-info mb-4">
                <strong>فترة التقرير:</strong> من <?php echo $startDate; ?> إلى <?php echo $endDate; ?>
            </div>
            
            <!-- الإحصائيات العامة -->
            <div class="general-stats mb-5">
                <h2 style="color: var(--primary-color); margin-bottom: 2rem;">الإحصائيات العامة للفترة</h2>
                <div class="grid grid-5">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $generalStats['new_users']; ?></div>
                        <div class="stats-label">مستخدمين جدد</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--success-color), #27ae60);">
                        <div class="stats-number"><?php echo $generalStats['new_donations']; ?></div>
                        <div class="stats-label">تبرعات جديدة</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--secondary-color), #3498db);">
                        <div class="stats-number"><?php echo $generalStats['completed_donations']; ?></div>
                        <div class="stats-label">تبرعات مكتملة</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--warning-color), #f39c12);">
                        <div class="stats-number"><?php echo $generalStats['new_requests']; ?></div>
                        <div class="stats-label">طلبات جديدة</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                        <div class="stats-number"><?php echo $generalStats['new_charities']; ?></div>
                        <div class="stats-label">جمعيات جديدة</div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- إحصائيات المستخدمين الجدد -->
                <div class="col-6 col-sm-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h3>المستخدمين الجدد حسب النوع</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($userStats)): ?>
                                <?php 
                                $userTypeLabels = [
                                    'donor' => 'متبرعين',
                                    'beneficiary' => 'مستفيدين',
                                    'charity' => 'جمعيات',
                                    'admin' => 'مدراء'
                                ];
                                $userTypeColors = [
                                    'donor' => 'success',
                                    'beneficiary' => 'primary',
                                    'charity' => 'warning',
                                    'admin' => 'danger'
                                ];
                                ?>
                                <?php foreach ($userStats as $type => $count): ?>
                                    <div class="stat-item mb-3" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid #dee2e6; border-radius: var(--border-radius);">
                                        <span><?php echo $userTypeLabels[$type] ?? $type; ?></span>
                                        <span class="badge badge-<?php echo $userTypeColors[$type] ?? 'primary'; ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                            <?php echo $count; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">لا توجد مستخدمين جدد في هذه الفترة</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- إحصائيات التبرعات حسب الفئة -->
                <div class="col-6 col-sm-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h3>التبرعات حسب الفئة</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($donationCategoryStats)): ?>
                                <?php 
                                $categoryLabels = [
                                    'clothing' => 'ملابس',
                                    'furniture' => 'أثاث',
                                    'electronics' => 'إلكترونيات',
                                    'other' => 'أخرى'
                                ];
                                ?>
                                <?php foreach ($donationCategoryStats as $stat): ?>
                                    <div class="stat-item mb-3" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid #dee2e6; border-radius: var(--border-radius);">
                                        <span><?php echo $categoryLabels[$stat['category']] ?? $stat['category']; ?></span>
                                        <span class="badge badge-primary" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                            <?php echo $stat['count']; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">لا توجد تبرعات في هذه الفترة</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- أكثر المتبرعين نشاطاً -->
            <?php if (!empty($topDonors)): ?>
                <div class="top-donors mb-5">
                    <h2 style="color: var(--primary-color); margin-bottom: 2rem;">أكثر المتبرعين نشاطاً</h2>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>الترتيب</th>
                                            <th>الاسم</th>
                                            <th>البريد الإلكتروني</th>
                                            <th>عدد التبرعات</th>
                                            <th>التبرعات المكتملة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topDonors as $index => $donor): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge badge-<?php echo $index < 3 ? 'warning' : 'secondary'; ?>" style="font-size: 1rem;">
                                                        #<?php echo $index + 1; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo escape($donor['full_name']); ?></td>
                                                <td><?php echo escape($donor['email']); ?></td>
                                                <td>
                                                    <span class="badge badge-primary"><?php echo $donor['donations_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-success"><?php echo $donor['completed_count']; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- النشاط اليومي -->
            <?php if (!empty($dailyActivity)): ?>
                <div class="daily-activity mb-5">
                    <h2 style="color: var(--primary-color); margin-bottom: 2rem;">النشاط اليومي</h2>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>تبرعات جديدة</th>
                                            <th>مستخدمين جدد</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dailyActivity as $activity): ?>
                                            <tr>
                                                <td><?php echo $activity['date']; ?></td>
                                                <td>
                                                    <span class="badge badge-primary"><?php echo $activity['donations_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-success"><?php echo $activity['users_count']; ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- الإحصائيات الإجمالية -->
            <div class="total-stats">
                <h2 style="color: var(--primary-color); margin-bottom: 2rem;">الإحصائيات الإجمالية (جميع الأوقات)</h2>
                <div class="grid grid-4">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo number_format($totalStats['total_users']); ?></div>
                        <div class="stats-label">إجمالي المستخدمين</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--success-color), #27ae60);">
                        <div class="stats-number"><?php echo number_format($totalStats['total_donations']); ?></div>
                        <div class="stats-label">إجمالي التبرعات</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--warning-color), #f39c12);">
                        <div class="stats-number"><?php echo number_format($totalStats['total_charities']); ?></div>
                        <div class="stats-label">إجمالي الجمعيات</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--secondary-color), #3498db);">
                        <div class="stats-number"><?php echo number_format($totalStats['total_requests']); ?></div>
                        <div class="stats-label">إجمالي الطلبات</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>

    <script>
        function toggleCustomDates() {
            const periodSelect = document.querySelector('select[name="period"]');
            const customDatesDiv = document.getElementById('custom-dates');
            
            if (periodSelect.value === 'custom') {
                customDatesDiv.style.display = 'flex';
            } else {
                customDatesDiv.style.display = 'none';
            }
        }
        
        // تأكد من إظهار/إخفاء الحقول عند التحميل
        document.addEventListener('DOMContentLoaded', function() {
            toggleCustomDates();
        });
    </script>
</body>
</html>
