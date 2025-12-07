<?php
require_once 'config.php';
checkLogin();

// تعريف عنوان الصفحة
$pageTitle = 'الجمعيات الخيرية';
$pageDescription = 'تصفح الجمعيات الخيرية المسجلة في المنصة';

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];

// البحث والفلترة
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$city = isset($_GET['city']) ? $_GET['city'] : 'all';
$verified = isset($_GET['verified']) ? $_GET['verified'] : 'all';

// بناء الاستعلام
$sql = "SELECT c.*, u.email, u.phone, u.address,
        (SELECT COUNT(*) FROM donations WHERE charity_id = c.id) as donations_count,
        (SELECT COUNT(*) FROM donations WHERE charity_id = c.id AND status = 'completed') as completed_count
        FROM charities c
        JOIN users u ON c.user_id = u.id
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (c.charity_name LIKE ? OR c.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($city !== 'all') {
    $sql .= " AND u.address LIKE ?";
    $params[] = "%$city%";
}

if ($verified !== 'all') {
    $verifiedValue = $verified === 'yes' ? 1 : 0;
    $sql .= " AND c.verified = ?";
    $params[] = $verifiedValue;
}

$sql .= " ORDER BY c.verified DESC, c.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$charities = $stmt->fetchAll();

// جلب قائمة المناطق من عناوين المستخدمين
$citiesStmt = $db->prepare("
    SELECT DISTINCT u.address 
    FROM users u 
    JOIN charities c ON u.id = c.user_id 
    WHERE u.address IS NOT NULL AND u.address != ''
    ORDER BY u.address
");
$citiesStmt->execute();
$cities = $citiesStmt->fetchAll(PDO::FETCH_COLUMN);

// إحصائيات
$statsStmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN verified = 1 THEN 1 END) as verified_count,
        COUNT(CASE WHEN verified = 0 THEN 1 END) as unverified_count
    FROM charities
");
$statsStmt->execute();
$stats = $statsStmt->fetch();

require_once 'includes/header.php';
?>
    <style>
        /* تصميم محسّن لصفحة الجمعيات */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef3 100%);
            min-height: 100vh;
        }

        /* الصفحة الرئيسية */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* رأس الصفحة */
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2.5rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }

        /* بطاقات الإحصائيات */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(52, 152, 219, 0.2);
        }

        .stat-icon {
            font-size: 3rem;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .stat-details h3 {
            font-size: 2.5rem;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .stat-details p {
            color: #6c757d;
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }

        /* الفلاتر */
        .filters-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 3rem;
        }

        .filters-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .search-group {
            flex: 2;
            min-width: 300px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
        }

        /* شبكة الجمعيات */
        .charities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .charity-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
        }

        .charity-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 40px rgba(52, 152, 219, 0.2);
            border-color: #3498db;
        }

        .charity-header {
            padding: 2rem;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .charity-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .verified-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: #2ecc71;
            border-radius: 50%;
            font-size: 1rem;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(46, 204, 113, 0.4);
        }

        .charity-reg {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
            margin: 0;
        }

        .charity-body {
            padding: 2rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .charity-description {
            color: #6c757d;
            line-height: 1.7;
            margin-bottom: 1.5rem;
            min-height: 60px;
        }

        .charity-details {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0e6ed;
        }

        .detail-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .detail-item strong {
            color: #2c3e50;
            font-weight: 700;
            min-width: 100px;
        }

        .detail-item span,
        .detail-item a {
            color: #495057;
            word-break: break-word;
        }

        .detail-item a {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }

        .detail-item a:hover {
            text-decoration: underline;
        }

        .charity-stats {
            display: flex;
            gap: 2rem;
            justify-content: center;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            display: block;
            font-size: 2rem;
            font-weight: 800;
            color: #3498db;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            display: block;
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 600;
        }

        .charity-actions {
            padding: 0 2rem 2rem;
            display: flex;
            gap: 1rem;
            margin-top: auto;
        }

        .charity-actions .btn {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-decoration: none;
            display: inline-block;
        }

        /* رسالة فارغة */
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .empty-state p {
            font-size: 1.3rem;
            color: #6c757d;
            margin-bottom: 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 1.5rem;
                text-align: center;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }

            .charities-grid {
                grid-template-columns: 1fr;
            }

            .filters-form {
                flex-direction: column;
            }

            .filter-group,
            .search-group {
                width: 100%;
                min-width: 100%;
            }

            .charity-actions {
                flex-direction: column;
            }

            .stat-card {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>

    <!-- المحتوى الرئيسي -->
    <div class="main-container">
        <div class="page-header">
            <h1>الجمعيات الخيرية المسجلة</h1>
            <?php if ($userType === 'admin'): ?>
                <a href="admin-charities.php" class="btn btn-primary">إدارة الجمعيات</a>
            <?php endif; ?>
        </div>

        <!-- إحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-details">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>إجمالي الجمعيات</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-details">
                    <h3><?php echo $stats['verified_count']; ?></h3>
                    <p>جمعيات موثقة</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-details">
                    <h3><?php echo $stats['unverified_count']; ?></h3>
                    <p>قيد التوثيق</p>
                </div>
            </div>
        </div>

        <!-- الفلاتر والبحث -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group search-group">
                    <input type="text" name="search" placeholder="البحث عن جمعية..." 
                           value="<?php echo escape($search); ?>">
                </div>

                <div class="filter-group">
                    <label>المنطقة:</label>
                    <select name="city">
                        <option value="all" <?php echo $city === 'all' ? 'selected' : ''; ?>>جميع المناطق</option>
                        <?php foreach ($cities as $cityOption): ?>
                        <option value="<?php echo escape($cityOption); ?>" 
                                <?php echo $city === $cityOption ? 'selected' : ''; ?>>
                            <?php echo escape($cityOption); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>التوثيق:</label>
                    <select name="verified">
                        <option value="all" <?php echo $verified === 'all' ? 'selected' : ''; ?>>الكل</option>
                        <option value="yes" <?php echo $verified === 'yes' ? 'selected' : ''; ?>>موثقة</option>
                        <option value="no" <?php echo $verified === 'no' ? 'selected' : ''; ?>>غير موثقة</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">بحث</button>
                
                <?php if (!empty($search) || $city !== 'all' || $verified !== 'all'): ?>
                    <a href="charities.php" class="btn btn-secondary">إعادة تعيين</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- قائمة الجمعيات -->
        <?php if (empty($charities)): ?>
            <div class="empty-state">
                <p>لا توجد جمعيات مطابقة للبحث</p>
            </div>
        <?php else: ?>
            <div class="charities-grid">
                <?php foreach ($charities as $charity): ?>
                <div class="charity-card">
                    <div class="charity-header">
                        <div class="charity-info">
                            <h3>
                                <?php echo escape($charity['charity_name']); ?>
                                <?php if ($charity['verified']): ?>
                                    <span class="verified-badge" title="جمعية موثقة">✓</span>
                                <?php endif; ?>
                            </h3>
                            <p class="charity-reg">
                                رقم الترخيص: <?php echo escape($charity['license_number']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="charity-body">
                        <p class="charity-description">
                            <?php echo escape(substr($charity['description'], 0, 150)) . (strlen($charity['description']) > 150 ? '...' : ''); ?>
                        </p>
                        
                        <div class="charity-details">
                            <div class="detail-item">
                                <strong>📍 العنوان:</strong>
                                <span><?php echo escape($charity['address']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <strong>📧 البريد:</strong>
                                <span><?php echo escape($charity['email']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <strong>📞 الهاتف:</strong>
                                <span><?php echo escape($charity['phone']); ?></span>
                            </div>
                            
                            <?php if (!empty($charity['website'])): ?>
                            <div class="detail-item">
                                <strong>🌐 الموقع:</strong>
                                <a href="<?php echo escape($charity['website']); ?>" target="_blank">
                                    زيارة الموقع
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="charity-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $charity['donations_count']; ?></span>
                                <span class="stat-label">تبرع</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $charity['completed_count']; ?></span>
                                <span class="stat-label">مكتمل</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="charity-actions">
                        <a href="charity-profile.php?id=<?php echo $charity['id']; ?>" 
                           class="btn btn-primary">
                            عرض الملف
                        </a>
                        
                        <?php if ($userType === 'donor'): ?>
                        <a href="add-donation.php?charity_id=<?php echo $charity['id']; ?>" 
                           class="btn btn-success">
                            تبرع للجمعية
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php require_once 'includes/footer.php'; ?>