<?php
require_once 'config.php';

$pageTitle = 'الموافقة على التبرعات';
$pageDescription = 'الموافقة على التبرعات الجديدة قبل نشرها للمستفيدين';

checkLogin();
checkUserType(['charity']);

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// جلب معلومات الجمعية
$charityStmt = $db->prepare("SELECT * FROM charities WHERE user_id = ?");
$charityStmt->execute([$userId]);
$charity = $charityStmt->fetch();

if (!$charity) {
    header('Location: dashboard.php');
    exit();
}

$errors = [];
$success = '';

// معالجة الموافقة على التبرع
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_donation'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'رمز الأمان غير صحيح';
    } else {
        $donationId = intval($_POST['donation_id']);
        $approvalNotes = trim($_POST['approval_notes']);

        // التحقق من أن التبرع مخصص لهذه الجمعية وفي انتظار الموافقة
        $checkStmt = $db->prepare("
            SELECT d.*, u.full_name as donor_name
            FROM donations d
            JOIN users u ON d.donor_id = u.id
            WHERE d.id = ? AND d.charity_id = ? AND d.status = 'pending_charity_approval'
        ");
        $checkStmt->execute([$donationId, $charity['id']]);
        $donation = $checkStmt->fetch();

        if ($donation) {
            try {
                // تحديث حالة التبرع إلى available
                $updateStmt = $db->prepare("
                    UPDATE donations
                    SET status = 'available',
                        charity_approved_at = CURRENT_TIMESTAMP,
                        charity_approval_notes = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$approvalNotes, $donationId]);

                // تسجيل الحركة
                $movementStmt = $db->prepare("
                    INSERT INTO donation_movements (donation_id, from_status, to_status, moved_by, notes)
                    VALUES (?, 'pending_charity_approval', 'available', ?, ?)
                ");
                $movementStmt->execute([$donationId, $userId, 'تمت الموافقة على التبرع من قبل الجمعية: ' . $approvalNotes]);

                // إرسال إشعار للمتبرع
                $notifStmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type)
                    VALUES (?, 'تمت الموافقة على تبرعك', ?, 'success')
                ");
                $notifStmt->execute([
                    $donation['donor_id'],
                    'تمت الموافقة على تبرعك "' . $donation['title'] . '" من قبل جمعية ' . $charity['charity_name'] . ' وتم نشره للمستفيدين'
                ]);

                $success = 'تمت الموافقة على التبرع بنجاح ونشره للمستفيدين';
            } catch (PDOException $e) {
                $errors[] = 'حدث خطأ أثناء الموافقة على التبرع: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'التبرع غير موجود أو غير مخصص لجمعيتكم أو تمت الموافقة عليه مسبقاً';
        }
    }
}

// معالجة رفض التبرع
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_donation'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'رمز الأمان غير صحيح';
    } else {
        $donationId = intval($_POST['donation_id']);
        $rejectionReason = trim($_POST['rejection_reason']);

        // التحقق من أن التبرع مخصص لهذه الجمعية وفي انتظار الموافقة
        $checkStmt = $db->prepare("
            SELECT d.*, u.full_name as donor_name
            FROM donations d
            JOIN users u ON d.donor_id = u.id
            WHERE d.id = ? AND d.charity_id = ? AND d.status = 'pending_charity_approval'
        ");
        $checkStmt->execute([$donationId, $charity['id']]);
        $donation = $checkStmt->fetch();

        if ($donation) {
            try {
                // تحديث حالة التبرع إلى cancelled
                $updateStmt = $db->prepare("
                    UPDATE donations
                    SET status = 'cancelled',
                        charity_approval_notes = ?
                    WHERE id = ?
                ");
                $updateStmt->execute(['رفض: ' . $rejectionReason, $donationId]);

                // تسجيل الحركة
                $movementStmt = $db->prepare("
                    INSERT INTO donation_movements (donation_id, from_status, to_status, moved_by, notes)
                    VALUES (?, 'pending_charity_approval', 'cancelled', ?, ?)
                ");
                $movementStmt->execute([$donationId, $userId, 'تم رفض التبرع من قبل الجمعية: ' . $rejectionReason]);

                // إرسال إشعار للمتبرع
                $notifStmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type)
                    VALUES (?, 'تم رفض التبرع', ?, 'warning')
                ");
                $notifStmt->execute([
                    $donation['donor_id'],
                    'تم رفض تبرعك "' . $donation['title'] . '" من قبل جمعية ' . $charity['charity_name'] . '. السبب: ' . $rejectionReason
                ]);

                $success = 'تم رفض التبرع وإرسال إشعار للمتبرع';
            } catch (PDOException $e) {
                $errors[] = 'حدث خطأ أثناء رفض التبرع: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'التبرع غير موجود أو غير مخصص لجمعيتكم';
        }
    }
}

// جلب التبرعات في انتظار الموافقة
$pendingStmt = $db->prepare("
    SELECT d.*, u.full_name as donor_name, u.phone as donor_phone, u.email as donor_email
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE d.charity_id = ? AND d.status = 'pending_charity_approval'
    ORDER BY d.created_at DESC
");
$pendingStmt->execute([$charity['id']]);
$pendingDonations = $pendingStmt->fetchAll();

// إحصائيات
$statsStmt = $db->prepare("
    SELECT
        COUNT(*) as total_pending
    FROM donations
    WHERE charity_id = ? AND status = 'pending_charity_approval'
");
$statsStmt->execute([$charity['id']]);
$stats = $statsStmt->fetch();

?>
<?php require_once 'includes/header.php'; ?>

<!-- صفحة الموافقة على التبرعات -->
<section class="approve-donations" style="padding: 2rem 0; min-height: 70vh;">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                            الموافقة على التبرعات
                        </h2>
                        <p class="text-muted">التبرعات الجديدة التي تحتاج إلى موافقتكم قبل نشرها للمستفيدين</p>
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

                        <!-- إحصائيات -->
                        <div class="row mb-4">
                            <div class="col-4 col-sm-12">
                                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 10px; text-align: center;">
                                    <div style="font-size: 2.5rem; font-weight: bold;"><?php echo $stats['total_pending']; ?></div>
                                    <div style="font-size: 1rem; margin-top: 0.5rem;">في انتظار الموافقة</div>
                                </div>
                            </div>
                        </div>

                        <!-- قائمة التبرعات في انتظار الموافقة -->
                        <?php if (empty($pendingDonations)): ?>
                            <div class="alert alert-info text-center">
                                <h4>لا توجد تبرعات في انتظار الموافقة</h4>
                                <p>جميع التبرعات تمت الموافقة عليها أو لا توجد تبرعات جديدة حالياً</p>
                            </div>
                        <?php else: ?>
                            <div class="donations-grid">
                                <?php foreach ($pendingDonations as $donation): ?>
                                    <div class="donation-card" style="border: 2px solid #ffc107; border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem; background: #fffbf0;">
                                        <div class="row">
                                            <div class="col-8 col-sm-12">
                                                <h3 style="color: var(--primary-color); margin-bottom: 1rem;">
                                                    <?php echo escape($donation['title']); ?>
                                                </h3>

                                                <div class="donation-info">
                                                    <p><strong>المتبرع:</strong> <?php echo escape($donation['donor_name']); ?></p>
                                                    <p><strong>الهاتف:</strong> <?php echo escape($donation['donor_phone']); ?></p>
                                                    <p><strong>البريد الإلكتروني:</strong> <?php echo escape($donation['donor_email']); ?></p>
                                                    <p><strong>الفئة:</strong>
                                                        <?php
                                                        $categories = [
                                                            'clothing' => 'ملابس',
                                                            'furniture' => 'أثاث',
                                                            'electronics' => 'إلكترونيات',
                                                            'other' => 'أخرى'
                                                        ];
                                                        echo $categories[$donation['category']];
                                                        ?>
                                                    </p>
                                                    <p><strong>الحالة:</strong>
                                                        <?php
                                                        $conditions = [
                                                            'new' => 'جديد',
                                                            'excellent' => 'ممتاز',
                                                            'good' => 'جيد',
                                                            'fair' => 'مقبول'
                                                        ];
                                                        echo $conditions[$donation['condition_item']];
                                                        ?>
                                                    </p>
                                                    <p><strong>الكمية:</strong> <?php echo escape($donation['quantity']); ?></p>
                                                    <p><strong>موقع الاستلام:</strong> <?php echo escape($donation['pickup_location']); ?></p>
                                                    <p><strong>الوصف:</strong></p>
                                                    <p style="background: white; padding: 1rem; border-radius: 5px; margin-top: 0.5rem;">
                                                        <?php echo nl2br(escape($donation['description'])); ?>
                                                    </p>
                                                    <p><strong>تاريخ التبرع:</strong> <?php echo date('Y-m-d H:i', strtotime($donation['created_at'])); ?></p>
                                                </div>

                                                <!-- الصور -->
                                                <?php if (!empty($donation['images'])): ?>
                                                    <?php $images = json_decode($donation['images'], true); ?>
                                                    <?php if (!empty($images)): ?>
                                                        <div class="donation-images" style="margin-top: 1rem;">
                                                            <strong>الصور:</strong>
                                                            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 0.5rem;">
                                                                <?php foreach ($images as $image): ?>
                                                                    <img src="<?php echo escape($image); ?>"
                                                                         alt="صورة التبرع"
                                                                         style="width: 100px; height: 100px; object-fit: cover; border-radius: 5px; cursor: pointer;"
                                                                         onclick="window.open('<?php echo escape($image); ?>', '_blank')">
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>

                                            <div class="col-4 col-sm-12">
                                                <!-- نموذج الموافقة -->
                                                <div style="background: white; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                                                    <h4 style="color: #28a745; margin-bottom: 1rem;">الموافقة على التبرع</h4>
                                                    <form method="POST" style="margin-bottom: 0;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="donation_id" value="<?php echo $donation['id']; ?>">

                                                        <div class="form-group">
                                                            <label for="approval_notes_<?php echo $donation['id']; ?>" class="form-label">
                                                                ملاحظات الموافقة (اختياري)
                                                            </label>
                                                            <textarea
                                                                name="approval_notes"
                                                                id="approval_notes_<?php echo $donation['id']; ?>"
                                                                class="form-control"
                                                                rows="3"
                                                                placeholder="أي ملاحظات أو تعليقات على التبرع..."></textarea>
                                                        </div>

                                                        <button type="submit" name="approve_donation" class="btn btn-success" style="width: 100%;">
                                                            ✓ الموافقة والنشر
                                                        </button>
                                                    </form>
                                                </div>

                                                <!-- نموذج الرفض -->
                                                <div style="background: white; padding: 1rem; border-radius: 5px;">
                                                    <h4 style="color: #dc3545; margin-bottom: 1rem;">رفض التبرع</h4>
                                                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من رفض هذا التبرع؟');" style="margin-bottom: 0;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <input type="hidden" name="donation_id" value="<?php echo $donation['id']; ?>">

                                                        <div class="form-group">
                                                            <label for="rejection_reason_<?php echo $donation['id']; ?>" class="form-label">
                                                                سبب الرفض <span style="color: red;">*</span>
                                                            </label>
                                                            <textarea
                                                                name="rejection_reason"
                                                                id="rejection_reason_<?php echo $donation['id']; ?>"
                                                                class="form-control"
                                                                rows="3"
                                                                placeholder="يرجى ذكر سبب رفض التبرع..."
                                                                required></textarea>
                                                        </div>

                                                        <button type="submit" name="reject_donation" class="btn btn-danger" style="width: 100%;">
                                                            ✗ رفض التبرع
                                                        </button>
                                                    </form>
                                                </div>

                                                <!-- رابط التفاصيل -->
                                                <a href="donation-details.php?id=<?php echo $donation['id']; ?>"
                                                   class="btn btn-primary"
                                                   style="width: 100%; margin-top: 1rem;"
                                                   target="_blank">
                                                    عرض التفاصيل الكاملة
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
