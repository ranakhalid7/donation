<?php
require_once 'config.php';

// تعريف متغيرات الصفحة
$pageTitle = 'الرئيسية';
$pageDescription = 'منصة تربط بين المتبرعين والمحتاجين لتسهيل عملية التبرع بالملابس والأثاث';

// جلب الإحصائيات
$db = Database::getInstance();

// إحصائيات التبرعات
$donationsStmt = $db->prepare("SELECT COUNT(*) as total, 
    COUNT(CASE WHEN status = 'available' THEN 1 END) as available,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
    FROM donations");
$donationsStmt->execute();
$donationsStats = $donationsStmt->fetch();

// إحصائيات المستخدمين
$usersStmt = $db->prepare("SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN user_type = 'donor' THEN 1 END) as donors,
    COUNT(CASE WHEN user_type = 'beneficiary' THEN 1 END) as beneficiaries,
    COUNT(CASE WHEN user_type = 'charity' THEN 1 END) as charities
    FROM users WHERE status = 'active'");
$usersStmt->execute();
$usersStats = $usersStmt->fetch();

// أحدث التبرعات
$recentDonationsStmt = $db->prepare("
    SELECT d.*, u.full_name as donor_name, u.user_type
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE d.status = 'available'
    ORDER BY d.created_at DESC
    LIMIT 6
");
$recentDonationsStmt->execute();
$recentDonations = $recentDonationsStmt->fetchAll();

// الجمعيات الخيرية المعتمدة
$charitiesStmt = $db->prepare("
    SELECT c.*, u.full_name
    FROM charities c
    JOIN users u ON c.user_id = u.id
    WHERE c.verified = 1
    ORDER BY c.created_at DESC
    LIMIT 4
");
$charitiesStmt->execute();
$charities = $charitiesStmt->fetchAll();

// تضمين الهيدر
require_once 'includes/header.php';
?>

    <!-- البانر الرئيسي -->
    <section class="hero">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h1>شارك في الخير والعطاء</h1>
                    <p>منصة تربط بين المتبرعين والمحتاجين لتسهيل عملية التبرع بالملابس والأثاث</p>
                    <div class="hero-buttons">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="register.php?type=donor" class="btn btn-success">
                                🤝 تسجيل كمتبرع
                            </a>
                            <a href="register.php?type=beneficiary" class="btn btn-warning">
                           تسجيل كجمعية
                            
                            </a>
                        <?php else: ?>
                            <a href="donations.php" class="btn btn-success">
                                📦 تصفح التبرعات
                            </a>
                            <?php if ($_SESSION['user_type'] == 'donor'): ?>
                                <a href="add-donation.php" class="btn btn-warning">
                                    ➕ إضافة تبرع
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- الإحصائيات -->
    <section class="stats">
        <div class="container">
            <div class="row">
                <div class="col-3 col-sm-6 mb-3">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo number_format($donationsStats['total']); ?></div>
                        <div class="stats-label">إجمالي التبرعات</div>
                    </div>
                </div>
                <div class="col-3 col-sm-6 mb-3">
                    <div class="stats-card green">
                        <div class="stats-number"><?php echo number_format($donationsStats['available']); ?></div>
                        <div class="stats-label">تبرعات متاحة</div>
                    </div>
                </div>
                <div class="col-3 col-sm-6 mb-3">
                    <div class="stats-card orange">
                        <div class="stats-number"><?php echo number_format($usersStats['donors']); ?></div>
                        <div class="stats-label">متبرعين نشطين</div>
                    </div>
                </div>
                <div class="col-3 col-sm-6 mb-3">
                    <div class="stats-card purple">
                        <div class="stats-number"><?php echo number_format($usersStats['charities']); ?></div>
                        <div class="stats-label">جمعيات خيرية</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- أحدث التبرعات -->
    <section class="recent-donations section-padding-bg">
        <div class="container">
            <div class="section-header">
                <h2>أحدث التبرعات المتاحة</h2>
                <p>اكتشف أحدث التبرعات من أعضاء مجتمعنا الكريم واعثر على ما تحتاجه</p>
            </div>

            <?php if (!empty($recentDonations)): ?>
            <div class="grid grid-3">
                <?php foreach ($recentDonations as $donation): ?>
                    <div class="card donation-item">
                        <?php
                        $images = json_decode($donation['images'], true);
                        $firstImage = !empty($images) ? $images[0] : 'images/default-donation.jpg';
                        ?>
                        <img src="<?php echo escape($firstImage); ?>" alt="<?php echo escape($donation['title']); ?>" class="donation-image">

                        <div class="donation-content">
                            <h3 class="donation-title"><?php echo escape($donation['title']); ?></h3>
                            <p class="text-muted mb-2"><?php echo escape(substr($donation['description'], 0, 120)); ?>...</p>

                            <div class="donation-details">
                                <span class="badge badge-primary">
                                    <?php 
                                    $categories = [
                                        'clothing' => '👕 ملابس',
                                        'furniture' => '🪑 أثاث', 
                                        'electronics' => '📱 إلكترونيات',
                                        'other' => '📦 أخرى'
                                    ];
                                    echo $categories[$donation['category']] ?? $donation['category'];
                                    ?>
                                </span>
                                <span class="badge badge-success">
                                    <?php 
                                    $conditions = [
                                        'new' => '🆕 جديد',
                                        'excellent' => '⭐ ممتاز',
                                        'good' => '👍 جيد', 
                                        'fair' => '👌 مقبول'
                                    ];
                                    echo $conditions[$donation['condition_item']] ?? $donation['condition_item'];
                                    ?>
                                </span>
                                <?php if ($donation['quantity'] > 1): ?>
                                <span class="badge badge-warning">الكمية: <?php echo $donation['quantity']; ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="donation-meta">
                                <small class="text-muted">
                                    📍 <?php echo escape($donation['pickup_location']); ?><br>
                                    👤 بواسطة: <?php echo escape($donation['donor_name']); ?>
                                </small>
                                <a href="donation-details.php?id=<?php echo $donation['id']; ?>" class="btn btn-primary btn-sm">
                                    عرض التفاصيل
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center">
                <div style="font-size: 4rem; margin-bottom: 1rem;">📦</div>
                <h3 style="color: var(--primary-color); margin-bottom: 1rem;">لا توجد تبرعات متاحة حالياً</h3>
                <p style="color: #666;">كن أول من يشارك بتبرع وساعد المحتاجين</p>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'donor'): ?>
                <a href="add-donation.php" class="btn btn-success mt-4">إضافة تبرع جديد</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="donations.php" class="btn btn-lg btn-primary">عرض جميع التبرعات</a>
            </div>
        </div>
    </section>

    <!-- الجمعيات الخيرية -->
    <?php if (!empty($charities)): ?>
    <section class="charities section-padding">
        <div class="container">
            <div class="section-header">
                <h2>الجمعيات الخيرية المعتمدة</h2>
                <p>شركاؤنا في العمل الخيري والإنساني المعتمدون والموثوقون</p>
            </div>

            <div class="grid grid-4">
                <?php foreach ($charities as $charity): ?>
                    <div class="card text-center charity-card">
                        <div class="card-body">
                            <?php if ($charity['logo']): ?>
                                <img src="<?php echo escape($charity['logo']); ?>" alt="<?php echo escape($charity['charity_name']); ?>" class="charity-logo">
                            <?php else: ?>
                                <div class="charity-logo" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: bold;">
                                    <?php echo mb_substr($charity['charity_name'], 0, 1); ?>
                                </div>
                            <?php endif; ?>
                            <h4 class="card-title" style="color: var(--primary-color); font-weight: 700; margin-bottom: 1rem;">
                                <?php echo escape($charity['charity_name']); ?>
                            </h4>
                            <p class="text-muted charity-description"><?php echo escape(substr($charity['description'], 0, 100)); ?>...</p>
                            <div style="margin-top: 1rem;">
                                <span class="badge badge-success">✅ معتمدة</span>
                                <?php if ($charity['website']): ?>
                                <a href="<?php echo escape($charity['website']); ?>" target="_blank" class="btn btn-sm btn-outline" style="margin-top: 0.5rem;">
                                    🌐 زيارة الموقع
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-4">
                <a href="charities.php" class="btn btn-lg btn-primary">عرض جميع الجمعيات</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- كيف يعمل الموقع -->
    <section class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>كيف يعمل الموقع؟</h2>
                <p>خطوات بسيطة للمشاركة في الخير والعطاء</p>
            </div>

            <div class="row">
                <div class="col-4 col-sm-12 mb-4">
                    <div class="text-center">
                        <div class="step-icon">1</div>
                        <h3>التسجيل</h3>
                        <p>قم بإنشاء حساب جديد واختر نوع المستخدم (متبرع أو مستفيد أو جمعية خيرية)</p>
                    </div>
                </div>
                <div class="col-4 col-sm-12 mb-4">
                    <div class="text-center">
                        <div class="step-icon">2</div>
                        <h3>الإضافة أو البحث</h3>
                        <p>المتبرعون يضيفون تبرعاتهم، والمستفيدون يبحثون عن ما يحتاجونه</p>
                    </div>
                </div>
                <div class="col-4 col-sm-12 mb-4">
                    <div class="text-center">
                        <div class="step-icon">3</div>
                        <h3>التواصل والاستلام</h3>
                        <p>يتم التواصل بين الطرفين لترتيب عملية الاستلام والتسليم</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php
// تضمين الفوتر
require_once 'includes/footer.php';
?>