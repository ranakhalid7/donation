<?php
// تحديد القوائم حسب نوع المستخدم
$userType = $_SESSION['user_type'] ?? 'guest';

// القوائم الأساسية لجميع المستخدمين
$mainMenu = [
    'guest' => [
        ['url' => 'index.php', 'label' => 'الرئيسية', 'icon' => '🏠'],
        ['url' => 'donations.php', 'label' => 'التبرعات', 'icon' => '📦'],
        ['url' => 'charities.php', 'label' => 'الجمعيات', 'icon' => '🏢'],
        ['url' => 'about.php', 'label' => 'من نحن', 'icon' => 'ℹ️'],
        ['url' => 'contact.php', 'label' => 'اتصل بنا', 'icon' => '📞']
    ],
    'donor' => [
        ['url' => 'index.php', 'label' => 'الرئيسية', 'icon' => '🏠'],
        ['url' => 'donations.php', 'label' => 'جميع التبرعات', 'icon' => '📦'],
        ['url' => 'add-donation.php', 'label' => 'إضافة تبرع', 'icon' => '➕'],
        ['url' => 'my-donations.php', 'label' => 'تبرعاتي', 'icon' => '📋'],
        ['url' => 'charities.php', 'label' => 'الجمعيات', 'icon' => '🏢']
    ],
    'beneficiary' => [
        ['url' => 'index.php', 'label' => 'الرئيسية', 'icon' => '🏠'],
        ['url' => 'donations.php', 'label' => 'تصفح التبرعات', 'icon' => '🔍'],
        ['url' => 'my-requests.php', 'label' => 'طلباتي', 'icon' => '📋'],
        ['url' => 'charities.php', 'label' => 'الجمعيات', 'icon' => '🏢'],
        ['url' => 'contact.php', 'label' => 'اتصل بنا', 'icon' => '📞']
    ],
    'charity' => [
        ['url' => 'index.php', 'label' => 'الرئيسية', 'icon' => '🏠'],
        ['url' => 'charity-approve-donations.php', 'label' => 'الموافقة على التبرعات', 'icon' => '✅'],
        ['url' => 'charity-receive-donations.php', 'label' => 'استلام التبرعات', 'icon' => '📥'],
        ['url' => 'donations.php', 'label' => 'التبرعات', 'icon' => '📦'],
        ['url' => 'charity-profile.php', 'label' => 'ملف الجمعية', 'icon' => '🏢'],
      
    ],
    'admin' => [
        ['url' => 'admin/dashboard.php', 'label' => 'لوحة التحكم', 'icon' => '📊'],
        ['url' => 'admin/users.php', 'label' => 'المستخدمين', 'icon' => '👥'],
        ['url' => 'admin/donations.php', 'label' => 'التبرعات', 'icon' => '📦'],
        ['url' => 'admin/charities.php', 'label' => 'الجمعيات', 'icon' => '🏢'],
        ['url' => 'admin/reports.php', 'label' => 'التقارير', 'icon' => '📋']
    ]
];

// الحصول على القائمة المناسبة
$currentMenu = $mainMenu[$userType] ?? $mainMenu['guest'];

// تحديد الصفحة النشطة
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
    <title><?php echo $pageTitle ?? SITE_NAME; ?> - <?php echo SITE_NAME; ?></title>
    <meta name="description" content="<?php echo $pageDescription ?? 'منصة لتسهيل عملية التبرع والاستفادة من الملابس والأثاث بين أفراد المجتمع'; ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/style.css">
    <link rel="icon" href="<?php echo BASE_URL; ?>/favicon.ico" type="image/x-icon">
    <?php if (isset($extraCSS)): ?>
        <?php foreach ($extraCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>

<body>
    <!-- الهيدر -->
    <header class="header">
        <nav class="navbar">
            <!-- زر القائمة للجوال -->
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="فتح القائمة">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <a href="<?php echo BASE_URL; ?>/index.php" class="logo">
                <strong><?php echo SITE_NAME; ?></strong>
            </a>

            <ul class="nav-menu">
                <?php foreach ($currentMenu as $item): ?>
                    <li>
                        <a href="<?php echo BASE_URL . '/' . $item['url']; ?>"
                           class="<?php echo ($currentPage === basename($item['url'])) ? 'active' : ''; ?>">
                            <?php echo $item['label']; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="user-menu">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- <span>مرحباً، <?php echo escape($_SESSION['full_name']); ?></span> -->
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-primary">لوحة التحكم</a>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-danger">تسجيل الخروج</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline">تسجيل الدخول</a>
                    <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-primary">التسجيل</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- السايد بار للجوال -->
    <div class="mobile-sidebar" id="mobileSidebar">
        <div class="mobile-sidebar-header">
            <h3><?php echo SITE_NAME; ?></h3>
            <button class="mobile-sidebar-close" id="mobileSidebarClose" aria-label="إغلاق القائمة">×</button>
        </div>
        <div class="mobile-sidebar-content">
            <ul class="mobile-nav-menu">
                <?php foreach ($currentMenu as $item): ?>
                    <li>
                        <a href="<?php echo BASE_URL . '/' . $item['url']; ?>"
                           class="<?php echo ($currentPage === basename($item['url'])) ? 'active' : ''; ?>">
                            <?php echo $item['icon']; ?> <?php echo $item['label']; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="mobile-user-menu">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="mobile-user-info">
                        <!-- <span>👋 مرحباً، <?php echo escape($_SESSION['full_name']); ?></span> -->
                        <?php if ($userType !== 'guest'): ?>
                            <small style="display: block; margin-top: 0.5rem; color: #666;">
                                <?php
                                $userTypes = [
                                    'donor' => '🤝 متبرع',
                                    'beneficiary' => '🙏 مستفيد',
                                    'charity' => '🏢 جمعية خيرية',
                                    'admin' => '👑 مدير'
                                ];
                                echo $userTypes[$userType] ?? '';
                                ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-primary w-100 mb-2">لوحة التحكم</a>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-danger w-100">تسجيل الخروج</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline w-100 mb-2" style="color: var(--primary-color); border-color: var(--primary-color);">تسجيل الدخول</a>
                    <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-primary w-100">التسجيل</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- الخلفية المعتمة للسايد بار -->
    <div class="mobile-sidebar-overlay" id="mobileSidebarOverlay"></div>
