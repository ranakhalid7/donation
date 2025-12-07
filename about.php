<?php
require_once 'config.php';

// تعريف عنوان الصفحة ووصفها
$pageTitle = 'من نحن';
$pageDescription = 'تعرف على منصة التبرع بالملابس والأثاث - رسالتنا ورؤيتنا في خدمة المجتمع';

// جلب إحصائيات الموقع
$db = Database::getInstance();

$statsStmt = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM users WHERE status = 'active') as total_users,
        (SELECT COUNT(*) FROM donations) as total_donations,
        (SELECT COUNT(*) FROM donations WHERE status = 'completed') as completed_donations,
        (SELECT COUNT(*) FROM charities WHERE verified = 1) as verified_charities
");
$statsStmt->execute();
$stats = $statsStmt->fetch();

require_once 'includes/header.php';
?>

    <!-- صفحة من نحن -->
    <section class="about-hero" style="background: linear-gradient(135deg, rgba(44,62,80,0.9), rgba(52,152,219,0.9)), url('images/about-bg.jpg'); background-size: cover; color: white; padding: 4rem 0; text-align: center;">
        <div class="container">
            <h1 style="font-size: 3rem; margin-bottom: 1rem; font-weight: bold;">من نحن</h1>
            <p style="font-size: 1.2rem; opacity: 0.9; max-width: 600px; margin: 0 auto;">
                منصة تقنية تهدف لربط قلوب المتبرعين بأيدي المحتاجين، لنبني مجتمعاً متكافلاً يسوده الخير والعطاء
            </p>
        </div>
    </section>

    <!-- المحتوى الرئيسي -->
    <section class="about-content" style="padding: 4rem 0;">
        <div class="container">
            <!-- رسالتنا -->
            <div class="row mb-5">
                <div class="col-6 col-sm-12 mb-4">
                    <div class="content-section">
                        <h2 style="color: var(--primary-color); margin-bottom: 2rem; font-size: 2.5rem;">رسالتنا</h2>
                        <p style="font-size: 1.1rem; line-height: 1.8; color: #666; margin-bottom: 1.5rem;">
                            نؤمن بأن الخير موجود في قلب كل إنسان، ودورنا هو تسهيل إيصال هذا الخير لمن يحتاجه. 
                            منصتنا تربط بين المتبرعين والمستفيدين بطريقة آمنة وفعالة، مما يضمن وصول التبرعات 
                            لمستحقيها ويحقق أكبر فائدة اجتماعية ممكنة.
                        </p>
                        <p style="font-size: 1.1rem; line-height: 1.8; color: #666;">
                            نسعى لتكوين مجتمع رقمي متكافل يساهم في حل مشكلة الفقر والحاجة، ويعزز قيم 
                            التضامن والتراحم بين أفراد المجتمع من خلال التقنية الحديثة.
                        </p>
                    </div>
                </div>
                <div class="col-6 col-sm-12">
                    <div class="image-section" style="background: var(--light-color); border-radius: var(--border-radius); padding: 2rem; text-align: center; height: 100%;">
                        <div style="font-size: 4rem; color: var(--secondary-color); margin-bottom: 1rem;">🤲</div>
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">رسالة الخير</h3>
                        <p style="color: #666;">نؤمن بقوة العطاء في تغيير حياة الناس وبناء مجتمع أفضل للجميع</p>
                    </div>
                </div>
            </div>

            <!-- رؤيتنا -->
            <div class="row mb-5">
                <div class="col-6 col-sm-12 mb-4">
                    <div class="image-section" style="background: var(--light-color); border-radius: var(--border-radius); padding: 2rem; text-align: center; height: 100%;">
                        <div style="font-size: 4rem; color: var(--success-color); margin-bottom: 1rem;">🌟</div>
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">رؤية مستقبلية</h3>
                        <p style="color: #666;">نتطلع لأن نكون المنصة الرائدة في العمل الخيري الرقمي عربياً</p>
                    </div>
                </div>
                <div class="col-6 col-sm-12">
                    <div class="content-section">
                        <h2 style="color: var(--primary-color); margin-bottom: 2rem; font-size: 2.5rem;">رؤيتنا</h2>
                        <p style="font-size: 1.1rem; line-height: 1.8; color: #666; margin-bottom: 1.5rem;">
                            نطمح لأن نكون المنصة الأولى في المنطقة العربية لتسهيل عمليات التبرع والعمل الخيري، 
                            ونسعى لخلق نظام بيئي متكامل يضم جميع أطراف العمل الخيري من متبرعين ومستفيدين 
                            وجمعيات خيرية.
                        </p>
                        <p style="font-size: 1.1rem; line-height: 1.8; color: #666;">
                            هدفنا هو تحقيق مجتمع خالٍ من الحاجة، حيث يمكن لكل فرد الحصول على احتياجاته 
                            الأساسية من ملابس وأثاث وغيرها بكرامة وسهولة.
                        </p>
                    </div>
                </div>
            </div>

            <!-- قيمنا -->
            <div class="our-values mb-5">
                <h2 style="color: var(--primary-color); text-align: center; margin-bottom: 3rem; font-size: 2.5rem;">قيمنا</h2>
                <div class="grid grid-3">
                    <div class="value-card text-center p-4" style="background: white; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <div style="font-size: 3rem; color: var(--secondary-color); margin-bottom: 1rem;">🤝</div>
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">الشفافية</h3>
                        <p style="color: #666;">نؤمن بالشفافية الكاملة في جميع عملياتنا وتعاملاتنا مع المستخدمين</p>
                    </div>
                    <div class="value-card text-center p-4" style="background: white; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <div style="font-size: 3rem; color: var(--success-color); margin-bottom: 1rem;">🔒</div>
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">الأمان</h3>
                        <p style="color: #666;">نضمن حماية بيانات المستخدمين وخصوصيتهم بأعلى معايير الأمان</p>
                    </div>
                    <div class="value-card text-center p-4" style="background: white; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <div style="font-size: 3rem; color: var(--warning-color); margin-bottom: 1rem;">⚡</div>
                        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">الكفاءة</h3>
                        <p style="color: #666;">نسعى لتقديم خدمة سريعة وفعالة توفر الوقت والجهد على المستخدمين</p>
                    </div>
                </div>
            </div>

            <!-- الإحصائيات -->
            <div class="achievements mb-5">
                <h2 style="color: var(--primary-color); text-align: center; margin-bottom: 3rem; font-size: 2.5rem;">إنجازاتنا</h2>
                <div class="grid grid-4">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stats-label">مستخدم نشط</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--success-color), #27ae60);">
                        <div class="stats-number"><?php echo number_format($stats['total_donations']); ?></div>
                        <div class="stats-label">تبرع مسجل</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, var(--warning-color), #f39c12);">
                        <div class="stats-number"><?php echo number_format($stats['completed_donations']); ?></div>
                        <div class="stats-label">تبرع مكتمل</div>
                    </div>
                    <div class="stats-card" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                        <div class="stats-number"><?php echo number_format($stats['verified_charities']); ?></div>
                        <div class="stats-label">جمعية معتمدة</div>
                    </div>
                </div>
            </div>

            <!-- كيف نعمل -->
            <div class="how-we-work">
                <h2 style="color: var(--primary-color); text-align: center; margin-bottom: 3rem; font-size: 2.5rem;">كيف نعمل</h2>
                <div class="row">
                    <div class="col-4 col-sm-12 mb-4">
                        <div class="step-card text-center" style="background: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); height: 100%;">
                            <div style="width: 80px; height: 80px; background: var(--secondary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 2rem; font-weight: bold;">1</div>
                            <h3 style="color: var(--primary-color); margin-bottom: 1rem;">التسجيل والتصنيف</h3>
                            <p style="color: #666;">يسجل المستخدمون حساباتهم ويختارون نوع العضوية (متبرع، مستفيد، جمعية خيرية)</p>
                        </div>
                    </div>
                    <div class="col-4 col-sm-12 mb-4">
                        <div class="step-card text-center" style="background: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); height: 100%;">
                            <div style="width: 80px; height: 80px; background: var(--success-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 2rem; font-weight: bold;">2</div>
                            <h3 style="color: var(--primary-color); margin-bottom: 1rem;">النشر والبحث</h3>
                            <p style="color: #666;">المتبرعون ينشرون تبرعاتهم، والمستفيدون يبحثون عن ما يحتاجونه</p>
                        </div>
                    </div>
                    <div class="col-4 col-sm-12 mb-4">
                        <div class="step-card text-center" style="background: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); height: 100%;">
                            <div style="width: 80px; height: 80px; background: var(--warning-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 2rem; font-weight: bold;">3</div>
                            <h3 style="color: var(--primary-color); margin-bottom: 1rem;">التواصل والتسليم</h3>
                            <p style="color: #666;">نسهل التواصل بين الأطراف لترتيب عملية التسليم والاستلام</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- دعوة للعمل -->
    <section class="cta-section" style="background: var(--primary-color); color: white; padding: 4rem 0; text-align: center;">
        <div class="container">
            <h2 style="margin-bottom: 1rem; font-size: 2.5rem;">انضم إلى مجتمع الخير</h2>
            <p style="font-size: 1.2rem; opacity: 0.9; margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                كن جزءاً من حركة التغيير الإيجابي في المجتمع. سجل اليوم وابدأ رحلتك في العطاء أو الاستفادة
            </p>
            <div class="cta-buttons" style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="register.php?type=donor" class="btn btn-success" style="font-size: 1.1rem; padding: 1rem 2rem;">
                        سجل كمتبرع
                    </a>
                    <a href="register.php?type=beneficiary" class="btn btn-outline" style="font-size: 1.1rem; padding: 1rem 2rem; color: white; border-color: white;">
                        سجل كمستفيد
                    </a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-success" style="font-size: 1.1rem; padding: 1rem 2rem;">
                        اذهب للوحة التحكم
                    </a>
                    <a href="donations.php" class="btn btn-outline" style="font-size: 1.1rem; padding: 1rem 2rem; color: white; border-color: white;">
                        تصفح التبرعات
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
