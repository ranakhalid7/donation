<?php
    require_once 'config.php';

    $pageTitle = 'توزيع التبرع';
    $pageDescription = 'اختيار المستفيد وتوزيع التبرع';

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

    // الحصول على معرف التبرع
    $donationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($donationId == 0) {
        header('Location: charity-receive-donations.php');
        exit();
    }

    // جلب تفاصيل التبرع
    $donationStmt = $db->prepare("
        SELECT d.*, u.full_name as donor_name
        FROM donations d
        JOIN users u ON d.donor_id = u.id
        WHERE d.id = ? AND d.charity_id = ? AND d.status = 'with_charity'
    ");
    $donationStmt->execute([$donationId, $charity['id']]);
    $donation = $donationStmt->fetch();

    if (!$donation) {
        header('Location: charity-receive-donations.php');
        exit();
    }

    $errors = [];
    $success = '';

    // معالجة التوزيع
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['distribute'])) {
        if (!verifyCSRFToken($_POST['csrf_token'])) {
            $errors[] = 'رمز الأمان غير صحيح';
        } else {
            $requestId = intval($_POST['request_id']);
            $notes = trim($_POST['notes']);

            // جلب تفاصيل الطلب
            $requestStmt = $db->prepare("
                SELECT dr.*, u.full_name, u.id as beneficiary_id
                FROM donation_requests dr
                JOIN users u ON dr.requester_id = u.id
                WHERE dr.id = ? AND dr.donation_id = ? AND dr.status = 'pending'
            ");
            $requestStmt->execute([$requestId, $donationId]);
            $request = $requestStmt->fetch();

            if ($request) {
                $beneficiaryId = $request['beneficiary_id'];
                $beneficiary = ['id' => $beneficiaryId, 'full_name' => $request['full_name']];

                try {
                    // تحديث حالة التبرع
                    $updateStmt = $db->prepare("
                        UPDATE donations
                        SET status = 'delivered',
                            beneficiary_id = ?,
                            delivered_to_beneficiary_at = CURRENT_TIMESTAMP,
                            charity_notes = CONCAT(COALESCE(charity_notes, ''), '\n\nتوزيع: ', ?)
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$beneficiaryId, $notes, $donationId]);

                    // تسجيل الحركة
                    $movementStmt = $db->prepare("
                        INSERT INTO donation_movements (donation_id, from_status, to_status, moved_by, notes)
                        VALUES (?, 'with_charity', 'delivered', ?, ?)
                    ");
                    $movementStmt->execute([$donationId, $userId, 'تم التوزيع على: ' . $beneficiary['full_name'] . '. ' . $notes]);

                    // إرسال إشعار للمستفيد
                    $notifStmt = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type)
                        VALUES (?, 'تم استلام تبرع', ?, 'success')
                    ");
                    $notifStmt->execute([
                        $beneficiaryId,
                        'تم تسليمك تبرع "' . $donation['title'] . '" من جمعية ' . $charity['charity_name']
                    ]);

                    // إرسال إشعار للمتبرع
                    $notifDonor = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type)
                        VALUES (?, 'تم توزيع تبرعك', ?, 'success')
                    ");
                    $notifDonor->execute([
                        $donation['donor_id'],
                        'تم توزيع تبرعك "' . $donation['title'] . '" على مستفيد من خلال جمعية ' . $charity['charity_name']
                    ]);

                    // تحديث حالة الطلب إلى مقبول
                    $updateRequestStmt = $db->prepare("UPDATE donation_requests SET status = 'approved' WHERE id = ?");
                    $updateRequestStmt->execute([$requestId]);

                    // رفض باقي الطلبات
                    $rejectOthersStmt = $db->prepare("
                        UPDATE donation_requests
                        SET status = 'rejected'
                        WHERE donation_id = ? AND id != ?
                    ");
                    $rejectOthersStmt->execute([$donationId, $requestId]);

                    $success = 'تم توزيع التبرع بنجاح';

                    // إعادة التوجيه بعد 2 ثانية
                    header("Refresh: 2; url=charity-receive-donations.php");
                } catch (PDOException $e) {
                    $errors[] = 'حدث خطأ أثناء توزيع التبرع';
                }
            } else {
                $errors[] = 'الطلب غير موجود أو تم معالجته مسبقاً';
            }
        }
    }

    // جلب طلبات المستفيدين على هذا التبرع
    $requestsStmt = $db->prepare("
        SELECT dr.*, u.full_name, u.phone, u.address, u.email
        FROM donation_requests dr
        JOIN users u ON dr.requester_id = u.id
        WHERE dr.donation_id = ? AND dr.status = 'pending'
        ORDER BY dr.created_at ASC
    ");
    $requestsStmt->execute([$donationId]);
    $requests = $requestsStmt->fetchAll();
?>

<?php require_once 'includes/header.php'; ?>

    <style>
        .donation-preview {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 2rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            border-right: 5px solid var(--primary-color);
        }

        .beneficiary-card {
            background: white;
            padding: 1rem;
            border-radius: var(--radius);
            border: 2px solid var(--border-color);
            margin-bottom: 1rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .beneficiary-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-sm);
        }

        .beneficiary-card.selected {
            border-color: var(--success-color);
            background: #e8f5e9;
        }

        .beneficiary-radio {
            display: none;
        }

        .beneficiary-info {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 1rem;
        }

        .beneficiary-details {
            flex: 1;
        }

        .beneficiary-name {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .beneficiary-meta {
            font-size: 0.9rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .beneficiary-info {
                flex-direction: column;
            }
        }
    </style>

    <!-- المحتوى الرئيسي -->
    <section style="padding: 2rem 0; min-height: 70vh;">
        <div class="container">
            <div class="page-header mb-4 d-flex justify-content-between align-items-center" style="flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="color: var(--primary-color); margin: 0 0 0.5rem 0;">توزيع التبرع</h1>
                    <p class="text-muted" style="margin: 0;">اختر المستفيد لتوزيع التبرع عليه</p>
                </div>
                <a href="charity-receive-donations.php" class="btn btn-secondary">عودة</a>
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
                <div class="alert alert-success mb-4">
                    <?php echo escape($success); ?>
                    <br><small>سيتم تحويلك تلقائياً...</small>
                </div>
            <?php endif; ?>

            <!-- معاينة التبرع -->
            <div class="donation-preview">
                <h3 style="color: var(--primary-color); margin-bottom: 1rem;">معلومات التبرع</h3>
                <div class="row">
                    <div class="col-6 col-sm-12 mb-3">
                        <strong>العنوان:</strong> <?php echo escape($donation['title']); ?>
                    </div>
                    <div class="col-6 col-sm-12 mb-3">
                        <strong>المتبرع:</strong> <?php echo escape($donation['donor_name']); ?>
                    </div>
                    <div class="col-4 col-sm-12 mb-3">
                        <strong>الفئة:</strong> <?php echo escape($donation['category']); ?>
                    </div>
                    <div class="col-4 col-sm-12 mb-3">
                        <strong>الحالة:</strong> <?php echo escape($donation['condition_item']); ?>
                    </div>
                    <div class="col-4 col-sm-12 mb-3">
                        <strong>الكمية:</strong> <?php echo escape($donation['quantity']); ?>
                    </div>
                    <div class="col-12 mb-2">
                        <strong>الوصف:</strong><br>
                        <?php echo escape($donation['description']); ?>
                    </div>
                </div>
            </div>

            <!-- نموذج التوزيع -->
            <h3 style="color: var(--primary-color); margin-bottom: 1rem;">طلبات المستفيدين على هذا التبرع</h3>

            <?php if (empty($requests)): ?>
                <div class="alert alert-warning">
                    <h4>لا توجد طلبات على هذا التبرع حالياً</h4>
                    <p>يجب أن يقدم المستفيدون طلباً على التبرع أولاً قبل إمكانية توزيعه.</p>
                    <a href="charity-receive-donations.php" class="btn btn-secondary mt-3">عودة</a>
                </div>
            <?php else: ?>
                <p class="text-muted mb-4">
                    عدد الطلبات: <strong><?php echo count($requests); ?></strong> - اختر الطلب المناسب للتوزيع
                </p>

                <?php foreach ($requests as $request): ?>
                    <div class="beneficiary-card" style="margin-bottom: 2rem; padding: 1.5rem; background: white; border-radius: var(--radius); box-shadow: var(--shadow-sm); border-right: 4px solid var(--primary-color);">
                        <div style="display: flex; justify-content: space-between; align-items: start; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
                            <div style="flex: 1;">
                                <h4 style="color: var(--primary-color); margin: 0 0 0.5rem 0;">
                                    👤 <?php echo escape($request['full_name']); ?>
                                </h4>
                                <div style="font-size: 0.9rem; color: #666;">
                                    <div>📱 <?php echo escape($request['phone']); ?></div>
                                    <div>📧 <?php echo escape($request['email']); ?></div>
                                    <?php if ($request['address']): ?>
                                        <div>📍 <?php echo escape($request['address']); ?></div>
                                    <?php endif; ?>
                                    <div style="margin-top: 0.5rem;">
                                        🕒 تاريخ الطلب: <?php echo date('Y-m-d H:i', strtotime($request['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <span class="badge badge-warning" style="font-size: 0.9rem; padding: 0.5rem 1rem;">قيد الانتظار</span>
                        </div>

                        <div style="background: #f8f9fa; padding: 1rem; border-radius: var(--radius); margin-bottom: 1rem;">
                            <strong style="color: var(--primary-color);">رسالة الطلب:</strong>
                            <p style="margin: 0.5rem 0 0 0; line-height: 1.6; color: #333;">
                                <?php echo nl2br(escape($request['message'])); ?>
                            </p>
                        </div>

                        <form method="POST" style="display: inline-block; width: 100%;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">

                            <div class="form-group">
                                <label for="notes_<?php echo $request['id']; ?>" class="form-label">ملاحظات التوزيع</label>
                                <textarea name="notes" id="notes_<?php echo $request['id']; ?>"
                                          class="form-control" rows="2"
                                          placeholder="أضف ملاحظات عن عملية التوزيع..." required></textarea>
                            </div>

                            <button type="submit" name="distribute" class="btn btn-success btn-lg w-100"
                                    onclick="return confirm('هل أنت متأكد من توزيع التبرع على <?php echo escape($request['full_name']); ?>؟')">
                                ✓ توزيع على <?php echo escape($request['full_name']); ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
