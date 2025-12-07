<?php
require_once 'config.php';

$pageTitle = 'استلام التبرعات';
$pageDescription = 'استلام وإدارة التبرعات المخصصة للجمعية';

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

// معالجة استلام التبرع
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['receive_donation'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = 'رمز الأمان غير صحيح';
    } else {
        $donationId = intval($_POST['donation_id']);
        $notes = trim($_POST['notes']);

        // التحقق من أن التبرع مخصص لهذه الجمعية ومعتمد (available)
        $checkStmt = $db->prepare("SELECT * FROM donations WHERE id = ? AND charity_id = ? AND status = 'available'");
        $checkStmt->execute([$donationId, $charity['id']]);
        $donation = $checkStmt->fetch();

        if ($donation) {
            try {
                // تحديث حالة التبرع
                $updateStmt = $db->prepare("
                    UPDATE donations
                    SET status = 'with_charity',
                        received_by_charity_at = CURRENT_TIMESTAMP,
                        charity_notes = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$notes, $donationId]);

                // تسجيل الحركة
                $movementStmt = $db->prepare("
                    INSERT INTO donation_movements (donation_id, from_status, to_status, moved_by, notes)
                    VALUES (?, 'available', 'with_charity', ?, ?)
                ");
                $movementStmt->execute([$donationId, $userId, $notes]);

                // إرسال إشعار للمتبرع
                $notifStmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type)
                    VALUES (?, 'تم استلام التبرع', ?, 'success')
                ");
                $notifStmt->execute([
                    $donation['donor_id'],
                    'تم استلام تبرعك "' . $donation['title'] . '" من قبل جمعية ' . $charity['charity_name']
                ]);

                $success = 'تم استلام التبرع بنجاح';
            } catch (PDOException $e) {
                $errors[] = 'حدث خطأ أثناء استلام التبرع';
            }
        } else {
            $errors[] = 'التبرع غير موجود أو غير مخصص لجمعيتكم';
        }
    }
}

// جلب التبرعات المتاحة للاستلام (المخصصة لهذه الجمعية)
$availableStmt = $db->prepare("
    SELECT d.*, u.full_name as donor_name, u.phone as donor_phone, u.email as donor_email
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE d.charity_id = ? AND d.status = 'available'
    ORDER BY d.created_at DESC
");
$availableStmt->execute([$charity['id']]);
$availableDonations = $availableStmt->fetchAll();

// جلب التبرعات المستلمة (مع الجمعية حالياً)
$receivedStmt = $db->prepare("
    SELECT d.*, u.full_name as donor_name, u.phone as donor_phone
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    WHERE d.charity_id = ? AND d.status = 'with_charity'
    ORDER BY d.received_by_charity_at DESC
");
$receivedStmt->execute([$charity['id']]);
$receivedDonations = $receivedStmt->fetchAll();

// إحصائيات
$statsStmt = $db->prepare("
    SELECT
        COUNT(CASE WHEN status = 'available' THEN 1 END) as available,
        COUNT(CASE WHEN status = 'with_charity' THEN 1 END) as with_charity,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
    FROM donations WHERE charity_id = ?
");
$statsStmt->execute([$charity['id']]);
$stats = $statsStmt->fetch();
?>

<?php require_once 'includes/header.php'; ?>

    <style>
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
        }

        .tab {
            padding: 1rem 2rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: #666;
            transition: var(--transition);
        }

        .tab:hover {
            color: var(--primary-color);
        }

        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .donation-item {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            border-right: 4px solid var(--primary-color);
        }

        .donation-item:hover {
            box-shadow: var(--shadow-md);
        }

        .donation-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .donation-title {
            font-size: 1.3rem;
            color: var(--primary-color);
            margin: 0;
        }

        .donation-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
        }

        .info-value {
            color: #333;
        }

        .receive-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        @media (max-width: 768px) {
            .tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
            }

            .tab {
                padding: 0.75rem 1.5rem;
                white-space: nowrap;
            }

            .donation-info {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <!-- المحتوى الرئيسي -->
    <section style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <div class="page-header mb-4">
                <h1 style="color: var(--primary-color);">استلام وإدارة التبرعات</h1>
                <p class="text-muted">جمعية <?php echo escape($charity['charity_name']); ?></p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-4">
                    <ul style="margin: 0; padding-right: 1rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo escape($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success mb-4"><?php echo escape($success); ?></div>
            <?php endif; ?>

            <!-- الإحصائيات -->
            <div class="stats-grid mb-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                <div class="stat-card" style="background: linear-gradient(135deg, var(--success-color), #27ae60); color: white; padding: 1.5rem; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                    <div class="stat-details">
                        <h3 style="font-size: 2.5rem; margin: 0;"><?php echo $stats['available']; ?></h3>
                        <p style="margin: 0.5rem 0 0 0;">متاح للاستلام</p>
                    </div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, var(--warning-color), #f39c12); color: white; padding: 1.5rem; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                    <div class="stat-details">
                        <h3 style="font-size: 2.5rem; margin: 0;"><?php echo $stats['with_charity']; ?></h3>
                        <p style="margin: 0.5rem 0 0 0;">مع الجمعية</p>
                    </div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 1.5rem; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                    <div class="stat-details">
                        <h3 style="font-size: 2.5rem; margin: 0;"><?php echo $stats['delivered']; ?></h3>
                        <p style="margin: 0.5rem 0 0 0;">تم التوزيع</p>
                    </div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; padding: 1.5rem; border-radius: var(--radius); box-shadow: var(--shadow-sm);">
                    <div class="stat-details">
                        <h3 style="font-size: 2.5rem; margin: 0;"><?php echo $stats['completed']; ?></h3>
                        <p style="margin: 0.5rem 0 0 0;">مكتمل</p>
                    </div>
                </div>
            </div>

            <!-- التبويبات -->
            <div class="tabs">
                <button class="tab active" onclick="openTab(event, 'available')">
                    متاح للاستلام (<?php echo count($availableDonations); ?>)
                </button>
                <button class="tab" onclick="openTab(event, 'received')">
                    مع الجمعية (<?php echo count($receivedDonations); ?>)
                </button>
            </div>

            <!-- التبرعات المتاحة للاستلام -->
            <div id="available" class="tab-content active">
                <?php if (empty($availableDonations)): ?>
                    <div class="text-center" style="padding: 3rem; background: white; border-radius: var(--radius);">
                        <p style="font-size: 1.2rem; color: #666;">لا توجد تبرعات متاحة للاستلام حالياً</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($availableDonations as $donation): ?>
                        <div class="donation-item">
                            <div class="donation-header">
                                <h3 class="donation-title"><?php echo escape($donation['title']); ?></h3>
                                <span class="badge badge-success">متاح للاستلام</span>
                            </div>

                            <div class="donation-info">
                                <div class="info-item">
                                    <span class="info-label">المتبرع</span>
                                    <span class="info-value"><?php echo escape($donation['donor_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">هاتف المتبرع</span>
                                    <span class="info-value"><?php echo escape($donation['donor_phone']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">الفئة</span>
                                    <span class="info-value"><?php echo escape($donation['category']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">الحالة</span>
                                    <span class="info-value"><?php echo escape($donation['condition_item']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">الكمية</span>
                                    <span class="info-value"><?php echo escape($donation['quantity']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">موقع الاستلام</span>
                                    <span class="info-value"><?php echo escape($donation['pickup_location']); ?></span>
                                </div>
                            </div>

                            <div class="info-item mb-3">
                                <span class="info-label">الوصف</span>
                                <span class="info-value"><?php echo escape($donation['description']); ?></span>
                            </div>

                            <form method="POST" class="receive-form">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="donation_id" value="<?php echo $donation['id']; ?>">

                                <div class="form-group">
                                    <label for="notes_<?php echo $donation['id']; ?>" class="form-label">ملاحظات الاستلام</label>
                                    <textarea name="notes" id="notes_<?php echo $donation['id']; ?>"
                                              class="form-control" rows="2"
                                              placeholder="أضف أي ملاحظات عن حالة التبرع عند الاستلام..."></textarea>
                                </div>

                                <button type="submit" name="receive_donation" class="btn btn-success">
                                    ✓ تأكيد الاستلام
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- التبرعات المستلمة -->
            <div id="received" class="tab-content">
                <?php if (empty($receivedDonations)): ?>
                    <div class="text-center" style="padding: 3rem; background: white; border-radius: var(--radius);">
                        <p style="font-size: 1.2rem; color: #666;">لا توجد تبرعات مستلمة حالياً</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($receivedDonations as $donation): ?>
                        <div class="donation-item" style="border-right-color: var(--warning-color);">
                            <div class="donation-header">
                                <h3 class="donation-title"><?php echo escape($donation['title']); ?></h3>
                                <span class="badge badge-warning">مع الجمعية</span>
                            </div>

                            <div class="donation-info">
                                <div class="info-item">
                                    <span class="info-label">المتبرع</span>
                                    <span class="info-value"><?php echo escape($donation['donor_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">تاريخ الاستلام</span>
                                    <span class="info-value"><?php echo date('Y-m-d H:i', strtotime($donation['received_by_charity_at'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">الفئة</span>
                                    <span class="info-value"><?php echo escape($donation['category']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">الكمية</span>
                                    <span class="info-value"><?php echo escape($donation['quantity']); ?></span>
                                </div>
                            </div>

                            <?php if ($donation['charity_notes']): ?>
                            <div class="info-item mb-3">
                                <span class="info-label">ملاحظات الاستلام</span>
                                <span class="info-value"><?php echo escape($donation['charity_notes']); ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="mt-3">
                                <a href="charity-distribute-donation.php?id=<?php echo $donation['id']; ?>"
                                   class="btn btn-primary">
                                    📦 توزيع على مستفيد
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tabs;

            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }

            tabs = document.getElementsByClassName("tab");
            for (i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }

            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>
