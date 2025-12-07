<?php
require_once 'config.php';

// تعريف عنوان الصفحة
$pageTitle = 'التبرعات المتاحة';
$pageDescription = 'تصفح جميع التبرعات المتاحة من ملابس وأثاث';

$db = Database::getInstance();

// معاملات البحث والفلترة
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$condition = $_GET['condition'] ?? '';
$location = $_GET['location'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// بناء الاستعلام - عرض التبرعات المتاحة والتي مع الجمعية (لكي يتمكن المستفيدون من إرسال طلبات)
$whereConditions = ["(d.status = 'available' OR d.status = 'with_charity')"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(d.title LIKE ? OR d.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $whereConditions[] = "d.category = ?";
    $params[] = $category;
}

if (!empty($condition)) {
    $whereConditions[] = "d.condition_item = ?";
    $params[] = $condition;
}

if (!empty($location)) {
    $whereConditions[] = "d.pickup_location LIKE ?";
    $params[] = "%$location%";
}

$whereClause = implode(' AND ', $whereConditions);

// عدد النتائج الإجمالي
$countSql = "SELECT COUNT(*) FROM donations d JOIN users u ON d.donor_id = u.id WHERE $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalResults = $countStmt->fetchColumn();
$totalPages = ceil($totalResults / $limit);

// جلب التبرعات
$sql = "
    SELECT d.*, u.full_name as donor_name, u.user_type
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE $whereClause
    ORDER BY d.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$donations = $stmt->fetchAll();

require_once 'includes/header.php';
?>
    <style>
        /* تصميم محسّن لصفحة التبرعات */
        .donations-page {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef3 100%);
            padding: 3rem 0 !important;
            min-height: calc(100vh - var(--header-height) - 100px) !important;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .page-header p {
            font-size: 1.1rem;
            color: #6c757d;
        }

        /* فلاتر البحث */
        .search-filters {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .search-filters .form-control,
        .search-filters .form-select {
            height: 50px;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            padding: 0 1.25rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .search-filters .form-control:focus,
        .search-filters .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
            outline: none;
        }

        .search-filters .btn-primary {
            height: 50px;
            border-radius: 10px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        /* نتائج البحث */
        .search-results {
            background: white;
            padding: 1.25rem 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .search-results p {
            font-size: 1rem;
            font-weight: 600;
            color: #2c3e50;
        }

        /* شبكة التبرعات */
        .donations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .donation-item {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .donation-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 40px rgba(52, 152, 219, 0.2);
            border-color: #3498db;
        }

        /* صورة التبرع */
        .donation-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-bottom: 3px solid #f0f3f7;
            transition: transform 0.4s ease;
        }

        .donation-item:hover .donation-image {
            transform: scale(1.08);
        }

        /* محتوى البطاقة */
        .donation-content {
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .donation-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
            line-height: 1.4;
            min-height: 60px;
        }

        .donation-content > p {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.7;
            margin-bottom: 1.25rem;
            min-height: 50px;
        }

        /* تفاصيل التبرع */
        .donation-details {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #f0f3f7;
        }

        .donation-details .badge {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 20px;
            margin-left: 0.5rem;
        }

        .donation-details .text-muted {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-right: 4px solid #3498db;
        }

        .donation-details .text-muted small {
            display: block;
            line-height: 1.8;
            color: #495057;
        }

        .donation-details .text-muted strong {
            color: #2c3e50;
            font-weight: 700;
        }

        /* معلومات التبرع السفلية */
        .donation-meta {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 1rem;
        }

        .donation-meta small {
            color: #6c757d;
            font-size: 0.85rem;
            line-height: 1.6;
        }

        .donation-meta > div {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .donation-meta .btn {
            padding: 0.6rem 1.25rem;
            font-size: 0.9rem;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            white-space: nowrap;
        }

        .donation-meta .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .donation-meta .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }

        /* رسالة عدم وجود نتائج */
        .no-results {
            background: white;
            padding: 5rem 2rem !important;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .no-results h3 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* التنقل بين الصفحات */
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 3rem;
        }

        .pagination {
            display: flex;
            gap: 0.75rem;
            padding: 0;
            list-style: none;
            align-items: center;
        }

        .pagination .btn {
            min-width: 45px;
            height: 45px;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .pagination .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .pagination .btn-outline {
            background: white;
            color: #3498db;
            border: 2px solid #3498db;
        }

        .pagination .btn-outline:hover {
            background: #3498db;
            color: white;
        }

        /* تحسينات responsive */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.8rem;
            }

            .donations-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .donation-image {
                height: 180px;
            }

            .donation-title {
                font-size: 0.9rem;
                min-height: auto;
                margin-bottom: 0.5rem;
            }

            .donation-content {
                padding: 0.75rem;
            }

            .donation-content > p {
                display: none;
            }

            .donation-details {
                margin-bottom: 0.75rem;
                padding-bottom: 0.75rem;
            }

            .donation-details .badge {
                font-size: 0.7rem;
                padding: 0.3rem 0.6rem;
            }

            .donation-details .text-muted {
                display: none;
            }

            .donation-meta {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }

            .donation-meta small {
                display: none;
            }

            .donation-meta > div {
                width: 100%;
            }

            .donation-meta .btn {
                width: 100%;
                justify-content: center;
                padding: 0.65rem;
                font-size: 0.85rem;
            }

            .search-filters .row {
                gap: 1rem;
            }

            .col-2, .col-3 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .donations-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1400px) {
            .donations-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        /* أنيميشن للبطاقات عند التحميل */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .donation-item {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        .donation-item:nth-child(1) { animation-delay: 0.1s; }
        .donation-item:nth-child(2) { animation-delay: 0.2s; }
        .donation-item:nth-child(3) { animation-delay: 0.3s; }
        .donation-item:nth-child(4) { animation-delay: 0.4s; }
        .donation-item:nth-child(5) { animation-delay: 0.5s; }
        .donation-item:nth-child(6) { animation-delay: 0.6s; }
    </style>

    <!-- صفحة التبرعات -->
    <section class="donations-page" style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <!-- عنوان الصفحة -->
            <div class="page-header mb-4">
                <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">التبرعات المتاحة</h1>
                <p class="text-muted">اكتشف التبرعات المتاحة واطلب ما تحتاجه</p>
            </div>
            
            <!-- فلاتر البحث -->
            <div class="search-filters mb-4">
                <form method="GET" class="row">
                    <div class="col-3 col-sm-12 mb-3">
                        <input type="text" name="search" class="form-control" 
                               placeholder="البحث في التبرعات..." 
                               value="<?php echo escape($search); ?>">
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
                        <select name="condition" class="form-control form-select">
                            <option value="">جميع الحالات</option>
                            <option value="new" <?php echo $condition === 'new' ? 'selected' : ''; ?>>جديدة</option>
                            <option value="excellent" <?php echo $condition === 'excellent' ? 'selected' : ''; ?>>ممتازة</option>
                            <option value="good" <?php echo $condition === 'good' ? 'selected' : ''; ?>>جيدة</option>
                            <option value="fair" <?php echo $condition === 'fair' ? 'selected' : ''; ?>>مقبولة</option>
                        </select>
                    </div>
                    <div class="col-3 col-sm-12 mb-3">
                        <input type="text" name="location" class="form-control" 
                               placeholder="الموقع..." 
                               value="<?php echo escape($location); ?>">
                    </div>
                    <div class="col-2 col-sm-12 mb-3">
                        <button type="submit" class="btn btn-primary w-100">بحث</button>
                    </div>
                </form>
            </div>
            
            <!-- نتائج البحث -->
            <!-- <div class="search-results mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <p class="text-muted mb-0">
                        <?php if ($totalResults > 0): ?>
                            عرض <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $totalResults); ?> 
                            من <?php echo $totalResults; ?> تبرع
                        <?php else: ?>
                            لم يتم العثور على تبرعات
                        <?php endif; ?>
                    </p>
                    
                    <?php if (!empty($search) || !empty($category) || !empty($condition) || !empty($location)): ?>
                        <a href="donations.php" class="btn btn-secondary btn-sm">مسح الفلاتر</a>
                    <?php endif; ?>
                </div>
            </div> -->
            
            <!-- عرض التبرعات -->
            <?php if (!empty($donations)): ?>
                <div class="donations-grid">
                    <?php foreach ($donations as $donation): ?>
                            <div class="card donation-item">
                                <?php 
                                $images = json_decode($donation['images'], true);
                                $firstImage = !empty($images) ? $images[0] : 'images/default-donation.jpg';
                                ?>
                                <img src="<?php echo escape($firstImage); ?>" 
                                     alt="<?php echo escape($donation['title']); ?>" 
                                     class="donation-image">
                                
                                <div class="donation-content">
                                    <h3 class="donation-title"><?php echo escape($donation['title']); ?></h3>
                                    <p class="text-muted mb-2">
                                        <?php echo escape(substr($donation['description'], 0, 100)); ?>...
                                    </p>
                                    
                                    <div class="donation-details mb-3">
                                        <div class="mb-2">
                                            <span class="badge badge-primary">
                                                <?php 
                                                $categoryLabels = [
                                                    'clothing' => 'ملابس',
                                                    'furniture' => 'أثاث',
                                                    'electronics' => 'إلكترونيات',
                                                    'other' => 'أخرى'
                                                ];
                                                echo $categoryLabels[$donation['category']] ?? $donation['category'];
                                                ?>
                                            </span>
                                            <span class="badge badge-success">
                                                <?php 
                                                $conditionLabels = [
                                                    'new' => 'جديدة',
                                                    'excellent' => 'ممتازة',
                                                    'good' => 'جيدة',
                                                    'fair' => 'مقبولة'
                                                ];
                                                echo $conditionLabels[$donation['condition_item']] ?? $donation['condition_item'];
                                                ?>
                                            </span>
                                        </div>
                                        <div class="text-muted">
                                            <small>
                                                <strong>الكمية:</strong> <?php echo $donation['quantity']; ?><br>
                                                <strong>الموقع:</strong> <?php echo escape($donation['pickup_location']); ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="donation-meta">
                                        <small class="text-muted">
                                            بواسطة: <?php echo escape($donation['donor_name']); ?><br>
                                            <?php echo date('Y-m-d', strtotime($donation['created_at'])); ?>
                                        </small>
                                        <div>
                                            <a href="donation-details.php?id=<?php echo $donation['id']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                عرض التفاصيل
                                            </a>
                                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'beneficiary'): ?>
                                                <a href="request-donation.php?id=<?php echo $donation['id']; ?>" 
                                                   class="btn btn-success btn-sm">
                                                    طلب التبرع
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                </div>
                
                <!-- التنقل بين الصفحات -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-wrapper mt-5">
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
                <!-- رسالة عدم وجود نتائج -->
                <div class="no-results text-center" style="padding: 4rem 0;">
                    <div style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;">📦</div>
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem;">لم يتم العثور على تبرعات</h3>
                    <p class="text-muted mb-4">
                        <?php if (!empty($search) || !empty($category) || !empty($condition) || !empty($location)): ?>
                            جرب تعديل معايير البحث أو مسح الفلاتر للعثور على المزيد من التبرعات
                        <?php else: ?>
                            لا توجد تبرعات متاحة حالياً. تحقق مرة أخرى لاحقاً
                        <?php endif; ?>
                    </p>
                    
                    <?php if (!empty($search) || !empty($category) || !empty($condition) || !empty($location)): ?>
                        <a href="donations.php" class="btn btn-primary">مسح الفلاتر</a>
                    <?php endif; ?>
                    
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="mt-4">
                            <p class="text-muted">هل تريد المساهمة في المجتمع؟</p>
                            <a href="register.php?type=donor" class="btn btn-success">سجل كمتبرع</a>
                        </div>
                    <?php elseif ($_SESSION['user_type'] === 'donor'): ?>
                        <div class="mt-4">
                            <a href="add-donation.php" class="btn btn-success">أضف تبرع جديد</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>