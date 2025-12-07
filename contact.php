<?php
require_once 'config.php';

// تعريف عنوان الصفحة
$pageTitle = 'اتصل بنا';
$pageDescription = 'تواصل معنا للاستفسارات والدعم';

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // التحقق من البيانات
    if (empty($name)) $errors[] = 'الاسم مطلوب';
    if (empty($email)) $errors[] = 'البريد الإلكتروني مطلوب';
    if (empty($subject)) $errors[] = 'الموضوع مطلوب';
    if (empty($message)) $errors[] = 'الرسالة مطلوبة';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صحيح';
    }

    if (empty($errors)) {
        // هنا يمكن إرسال البريد الإلكتروني أو حفظ الرسالة في قاعدة البيانات
        // لأغراض التجريب، سنعرض رسالة نجاح فقط
        $success = 'تم إرسال رسالتك بنجاح! سنقوم بالرد عليك في أقرب وقت ممكن.';

        // مسح النموذج
        $_POST = [];
    }
}

require_once 'includes/header.php';
?>

    <!-- صفحة اتصل بنا -->
    <section class="contact-page" style="padding: 3rem 0;">
        <div class="container">
            <div class="text-center mb-5">
                <h1 style="color: var(--primary-color); margin-bottom: 1rem;">اتصل بنا</h1>
                <p style="color: #666; font-size: 1.1rem;">نحن هنا لمساعدتك والإجابة على جميع استفساراتك</p>
            </div>
            
            <div class="row">
                <!-- معلومات الاتصال -->
                <div class="col-4 col-sm-12 mb-4">
                    <div class="contact-info">
                        <div class="card mb-4">
                            <div class="card-body text-center">
                                <div style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;">📧</div>
                                <h3 style="color: var(--primary-color); margin-bottom: 1rem;">البريد الإلكتروني</h3>
                                <p class="text-muted">info@donation-system.com</p>
                                <p class="text-muted">support@donation-system.com</p>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-body text-center">
                                <div style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;">📞</div>
                                <h3 style="color: var(--primary-color); margin-bottom: 1rem;">الهاتف</h3>
                                <p class="text-muted">+966 50 123 4567</p>
                                <p class="text-muted">+966 11 234 5678</p>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-body text-center">
                                <div style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;">📍</div>
                                <h3 style="color: var(--primary-color); margin-bottom: 1rem;">العنوان</h3>
                                <p class="text-muted">الرياض، المملكة العربية السعودية</p>
                                <p class="text-muted">ص.ب. 12345</p>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-body text-center">
                                <div style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;">⏰</div>
                                <h3 style="color: var(--primary-color); margin-bottom: 1rem;">أوقات العمل</h3>
                                <p class="text-muted">الأحد - الخميس</p>
                                <p class="text-muted">9:00 صباحاً - 5:00 مساءً</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- نموذج الاتصال -->
                <div class="col-8 col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">أرسل رسالة</h2>
                            <p class="text-muted">املأ النموذج أدناه وسنقوم بالرد عليك في أقرب وقت ممكن</p>
                        </div>
                        
                        <div class="card-body">
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul style="margin: 0; padding-right: 1rem;">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo escape($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?php echo escape($success); ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" data-validate="true">
                                <div class="row">
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="name" class="form-label">الاسم الكامل</label>
                                            <input type="text" id="name" name="name" class="form-control" 
                                                   value="<?php echo escape($_POST['name'] ?? ''); ?>" 
                                                   placeholder="أدخل اسمك الكامل" required>
                                        </div>
                                    </div>
                                    <div class="col-6 col-sm-12">
                                        <div class="form-group">
                                            <label for="email" class="form-label">البريد الإلكتروني</label>
                                            <input type="email" id="email" name="email" class="form-control" 
                                                   value="<?php echo escape($_POST['email'] ?? ''); ?>" 
                                                   placeholder="أدخل بريدك الإلكتروني" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="subject" class="form-label">موضوع الرسالة</label>
                                    <select id="subject" name="subject" class="form-control form-select" required>
                                        <option value="">اختر موضوع الرسالة</option>
                                        <option value="general" <?php echo ($_POST['subject'] ?? '') === 'general' ? 'selected' : ''; ?>>استفسار عام</option>
                                        <option value="support" <?php echo ($_POST['subject'] ?? '') === 'support' ? 'selected' : ''; ?>>دعم تقني</option>
                                        <option value="partnership" <?php echo ($_POST['subject'] ?? '') === 'partnership' ? 'selected' : ''; ?>>شراكات</option>
                                        <option value="complaint" <?php echo ($_POST['subject'] ?? '') === 'complaint' ? 'selected' : ''; ?>>شكوى</option>
                                        <option value="suggestion" <?php echo ($_POST['subject'] ?? '') === 'suggestion' ? 'selected' : ''; ?>>اقتراح</option>
                                        <option value="other" <?php echo ($_POST['subject'] ?? '') === 'other' ? 'selected' : ''; ?>>أخرى</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message" class="form-label">الرسالة</label>
                                    <textarea id="message" name="message" class="form-control" rows="6" 
                                              placeholder="اكتب رسالتك هنا..." required><?php echo escape($_POST['message'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                                        إرسال الرسالة
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- أسئلة شائعة -->
            <div class="faq-section mt-5">
                <div class="text-center mb-4">
                    <h2 style="color: var(--primary-color); margin-bottom: 1rem;">أسئلة شائعة</h2>
                    <p style="color: #666;">إجابات على الأسئلة الأكثر شيوعاً</p>
                </div>
                
                <div class="row">
                    <div class="col-6 col-sm-12 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h4 style="color: var(--primary-color); margin-bottom: 1rem;">كيف يمكنني إضافة تبرع؟</h4>
                                <p class="text-muted">بعد تسجيل الدخول كمتبرع، يمكنك الذهاب إلى "إضافة تبرع جديد" وملء النموذج بتفاصيل التبرع والصور.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-sm-12 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h4 style="color: var(--primary-color); margin-bottom: 1rem;">كيف أطلب تبرع؟</h4>
                                <p class="text-muted">سجل دخولك كمستفيد، تصفح التبرعات المتاحة، واضغط على "طلب التبرع" للتبرع الذي تريده.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-sm-12 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h4 style="color: var(--primary-color); margin-bottom: 1rem;">هل الموقع مجاني؟</h4>
                                <p class="text-muted">نعم، جميع خدمات الموقع مجانية بالكامل. هدفنا هو تسهيل عملية التبرع والعطاء في المجتمع.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-sm-12 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h4 style="color: var(--primary-color); margin-bottom: 1rem;">كيف أنضم كجمعية خيرية؟</h4>
                                <p class="text-muted">اختر "جمعية خيرية" عند التسجيل وأرفق المستندات المطلوبة. سيتم مراجعة طلبك والموافقة عليه.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
