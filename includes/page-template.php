<?php
/**
 * قالب صفحة جديدة
 *
 * هذا ملف مثال يوضح كيفية إنشاء صفحة جديدة باستخدام
 * ملفات الهيدر والفوتر المشتركة
 */

require_once 'config.php';

// ==========================================
// 1. تعريف متغيرات الصفحة (اختياري)
// ==========================================
$pageTitle = 'عنوان الصفحة';
$pageDescription = 'وصف الصفحة للسيو';

// ==========================================
// 2. إضافة ملفات CSS/JS إضافية (اختياري)
// ==========================================
// $extraCSS = ['css/custom.css'];
// $extraJS = ['js/custom.js'];

// ==========================================
// 3. التحقق من الصلاحيات (إذا لزم الأمر)
// ==========================================
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit();
// }

// تحديد أنواع المستخدمين المسموح لهم
// $allowedUserTypes = ['donor', 'admin'];
// if (!in_array($_SESSION['user_type'], $allowedUserTypes)) {
//     $_SESSION['message'] = 'ليس لديك صلاحية للوصول إلى هذه الصفحة';
//     $_SESSION['message_type'] = 'danger';
//     header('Location: index.php');
//     exit();
// }

// ==========================================
// 4. معالجة النماذج (إذا وجدت)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // التحقق من CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    // معالجة البيانات هنا
    // ...

    // إعادة التوجيه مع رسالة
    $_SESSION['message'] = 'تمت العملية بنجاح!';
    $_SESSION['message_type'] = 'success';
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// ==========================================
// 5. جلب البيانات من قاعدة البيانات
// ==========================================
$db = Database::getInstance();

// مثال: جلب بيانات
// $stmt = $db->prepare("SELECT * FROM table_name WHERE id = ?");
// $stmt->execute([1]);
// $data = $stmt->fetch();

// ==========================================
// 6. تضمين الهيدر
// ==========================================
require_once 'includes/header.php';
?>

    <!-- ==========================================
         محتوى الصفحة
         ========================================== -->

    <!-- قسم البانر/العنوان -->
    <section class="page-header" style="background: linear-gradient(135deg, var(--primary-color), var(--dark-color)); color: white; padding: 3rem 0; text-align: center;">
        <div class="container">
            <h1>عنوان الصفحة</h1>
            <p>وصف مختصر للصفحة</p>
        </div>
    </section>

    <!-- المحتوى الرئيسي -->
    <section class="main-content section-padding">
        <div class="container">
            <div class="row">
                <!-- المحتوى -->
                <div class="col-9 col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h3>عنوان القسم</h3>
                        </div>
                        <div class="card-body">
                            <p>محتوى الصفحة هنا...</p>

                            <!-- مثال على نموذج -->
                            <form method="POST" data-validate="true">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                                <div class="form-group">
                                    <label class="form-label" for="field_name">اسم الحقل</label>
                                    <input type="text" id="field_name" name="field_name" class="form-control" required>
                                </div>

                                <button type="submit" class="btn btn-primary">إرسال</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- سايدبار (اختياري) -->
                <div class="col-3 col-sm-12">
                    <div class="card">
                        <div class="card-body">
                            <h4>روابط سريعة</h4>
                            <ul>
                                <li><a href="#">رابط 1</a></li>
                                <li><a href="#">رابط 2</a></li>
                                <li><a href="#">رابط 3</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- قسم إضافي (اختياري) -->
    <section class="extra-section section-padding-bg">
        <div class="container">
            <div class="section-header">
                <h2>قسم إضافي</h2>
                <p>وصف القسم</p>
            </div>

            <div class="grid grid-3">
                <!-- بطاقات أو محتوى -->
                <div class="card">
                    <div class="card-body">
                        <h4>عنصر 1</h4>
                        <p>محتوى...</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h4>عنصر 2</h4>
                        <p>محتوى...</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h4>عنصر 3</h4>
                        <p>محتوى...</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php
// ==========================================
// 7. كود JavaScript مخصص (اختياري)
// ==========================================
$inlineScript = "
    // كود JavaScript مخصص للصفحة
    console.log('الصفحة جاهزة');

    // مثال على استخدام notification
    // DonationSystem.showNotification('مرحباً!', 'info');
";

// ==========================================
// 8. تضمين الفوتر
// ==========================================
require_once 'includes/footer.php';
?>
