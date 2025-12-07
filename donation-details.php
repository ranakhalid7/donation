<?php
require_once 'config.php';
checkLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];

// الحصول على معرف التبرع
$donationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($donationId == 0) {
    header('Location: donations.php');
    exit();
}

// جلب تفاصيل التبرع
$stmt = $db->prepare("
    SELECT d.*, u.full_name as donor_name, u.phone as donor_phone,
           u.email as donor_email, c.charity_name
    FROM donations d
    JOIN users u ON d.donor_id = u.id
    LEFT JOIN charities c ON d.charity_id = c.id
    WHERE d.id = ?
");
$stmt->execute([$donationId]);
$donation = $stmt->fetch();

if (!$donation) {
    header('Location: donations.php');
    exit();
}

// تعريف عنوان الصفحة
$pageTitle = 'تفاصيل التبرع - ' . $donation['title'];
$pageDescription = substr($donation['description'], 0, 150);

// التحقق من الصلاحيات
$canEdit = ($userType === 'admin' || ($userType === 'donor' && $donation['donor_id'] == $userId));
// يمكن للمستفيدين طلب التبرع إذا كان متاحاً أو مع الجمعية (قبل التوزيع)
$canRequest = ($userType === 'beneficiary' && ($donation['status'] === 'available' || $donation['status'] === 'with_charity'));
// الجمعيات يمكنها رؤية التبرعات في انتظار الموافقة
$isPendingApproval = ($donation['status'] === 'pending_charity_approval');

// جلب الطلبات على هذا التبرع (للمتبرع والمدير)
$requests = [];
if ($canEdit) {
    $requestsStmt = $db->prepare("
        SELECT dr.*, u.full_name, u.phone, u.email, u.address
        FROM donation_requests dr
        JOIN users u ON dr.requester_id = u.id
        WHERE dr.donation_id = ?
        ORDER BY dr.created_at DESC
    ");
    $requestsStmt->execute([$donationId]);
    $requests = $requestsStmt->fetchAll();
}

// معالجة طلب التبرع
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_donation'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = "رمز الأمان غير صحيح";
    } elseif ($canRequest) {
        $reason = trim($_POST['reason']);
        
        // التحقق من عدم وجود طلب سابق
        $checkStmt = $db->prepare("
            SELECT id FROM donation_requests 
            WHERE donation_id = ? AND requester_id = ?
        ");
        $checkStmt->execute([$donationId, $userId]);
        
        if ($checkStmt->fetch()) {
            $error = "لقد قمت بطلب هذا التبرع مسبقاً";
        } else {
            try {
                $insertStmt = $db->prepare("
                    INSERT INTO donation_requests (donation_id, requester_id, message, status)
                    VALUES (?, ?, ?, 'pending')
                ");
                $insertStmt->execute([$donationId, $userId, $reason]);
                
                // إضافة إشعار للمتبرع
                $notifStmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type)
                    VALUES (?, 'طلب تبرع جديد', 'تم استلام طلب جديد على تبرعك', 'info')
                ");
                $notifStmt->execute([$donation['donor_id']]);
                
                $success = "تم إرسال طلبك بنجاح، سيتم التواصل معك قريباً";
            } catch (PDOException $e) {
                $error = "حدث خطأ أثناء إرسال الطلب";
            }
        }
    }
}

// معالجة قبول/رفض الطلب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_request'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = "رمز الأمان غير صحيح";
    } elseif ($canEdit) {
        $requestId = intval($_POST['request_id']);
        $action = $_POST['action'];
        
        if ($action === 'approve') {
            try {
                // قبول الطلب
                $updateStmt = $db->prepare("
                    UPDATE donation_requests 
                    SET status = 'approved'
                    WHERE id = ?
                ");
                $updateStmt->execute([$requestId]);
                
                // تحديث حالة التبرع
                $donationUpdateStmt = $db->prepare("
                    UPDATE donations 
                    SET status = 'reserved'
                    WHERE id = ?
                ");
                $donationUpdateStmt->execute([$donationId]);
                
                // رفض باقي الطلبات
                $rejectStmt = $db->prepare("
                    UPDATE donation_requests 
                    SET status = 'rejected'
                    WHERE donation_id = ? AND id != ?
                ");
                $rejectStmt->execute([$donationId, $requestId]);
                
                // إشعار للمستفيد
                $getRequesterStmt = $db->prepare("SELECT requester_id FROM donation_requests WHERE id = ?");
                $getRequesterStmt->execute([$requestId]);
                $requester = $getRequesterStmt->fetch();
                
                $notifStmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type)
                    VALUES (?, 'تم قبول طلبك', 'تم قبول طلبك للتبرع، سيتم التواصل معك', 'success')
                ");
                $notifStmt->execute([$requester['requester_id']]);
                
                $success = "تم قبول الطلب بنجاح";
                
                // إعادة تحميل البيانات
                header("Refresh:0");
            } catch (PDOException $e) {
                $error = "حدث خطأ أثناء معالجة الطلب";
            }
        } elseif ($action === 'reject') {
            try {
                $updateStmt = $db->prepare("
                    UPDATE donation_requests 
                    SET status = 'rejected'
                    WHERE id = ?
                ");
                $updateStmt->execute([$requestId]);
                
                $success = "تم رفض الطلب";
                header("Refresh:0");
            } catch (PDOException $e) {
                $error = "حدث خطأ أثناء معالجة الطلب";
            }
        }
    }
}

// معالجة تحديث حالة التبرع إلى مكتمل
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_donation'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = "رمز الأمان غير صحيح";
    } elseif ($canEdit && $donation['status'] === 'reserved') {
        try {
            $updateStmt = $db->prepare("
                UPDATE donations 
                SET status = 'completed'
                WHERE id = ?
            ");
            $updateStmt->execute([$donationId]);
            
            $success = "تم تحديث حالة التبرع إلى مكتمل";
            header("Refresh:0");
        } catch (PDOException $e) {
            $error = "حدث خطأ أثناء التحديث";
        }
    }
}

require_once 'includes/header.php';
?>
    <style>
        .donation-details-container {
            display: grid;
            gap: 2rem;
        }

        .detail-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
            gap: 1rem;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row strong {
            min-width: 150px;
            color: var(--primary-color);
            font-weight: 600;
        }

        .detail-row span {
            flex: 1;
            color: #666;
        }

        .detail-row p {
            flex: 1;
            margin: 0;
            line-height: 1.6;
            color: #666;
        }

        .donor-info {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: var(--radius);
            border-right: 4px solid var(--primary-color);
        }

        .donor-info h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .requests-list {
            display: grid;
            gap: 1.5rem;
        }

        .request-item {
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: var(--radius);
            border-right: 4px solid var(--primary-color);
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .request-info {
            flex: 1;
            min-width: 300px;
        }

        .request-info h3 {
            color: var(--primary-color);
            margin-bottom: 0.75rem;
            font-size: 1.2rem;
        }

        .request-info p {
            margin: 0.5rem 0;
            line-height: 1.6;
            color: #666;
        }

        .request-status {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            right: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 90%;
            max-width: 600px;
            position: relative;
        }

        .modal-content h2 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .close {
            color: #aaa;
            position: absolute;
            left: 1rem;
            top: 1rem;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: var(--transition);
        }

        .close:hover,
        .close:focus {
            color: var(--danger-color);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .detail-row {
                flex-direction: column;
                gap: 0.5rem;
            }

            .detail-row strong {
                min-width: auto;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn,
            .action-buttons form {
                width: 100%;
            }

            .action-buttons button {
                width: 100%;
            }

            .request-item {
                flex-direction: column;
            }

            .request-status {
                width: 100%;
                align-items: stretch;
            }

            .request-status form {
                display: flex;
                gap: 0.5rem;
            }

            .request-status button {
                flex: 1;
            }

            .modal-content {
                margin: 10% auto;
                width: 95%;
                padding: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
            }
        }
    </style>

    <!-- Main Content -->
    <section style="padding: 2rem 0; min-height: 70vh;">
    <div class="container">
        <div class="page-header mb-4 d-flex justify-content-between align-items-center" style="flex-wrap: wrap; gap: 1rem;">
            <h1 style="color: var(--primary-color); margin: 0;">تفاصيل التبرع</h1>
            <a href="donations.php" class="btn btn-secondary">العودة للتبرعات</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo escape($error); ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo escape($success); ?></div>
        <?php endif; ?>

        <?php if ($isPendingApproval): ?>
            <div class="alert alert-warning" style="background: #fff3cd; border: 2px solid #ffc107; padding: 1.5rem; border-radius: 10px;">
                <h4 style="color: #856404; margin-bottom: 0.5rem;">⏳ في انتظار موافقة الجمعية</h4>
                <p style="color: #856404; margin: 0;">
                    هذا التبرع في انتظار موافقة جمعية <?php echo escape($donation['charity_name']); ?> قبل نشره للمستفيدين.
                    <?php if ($canEdit): ?>
                        <br>سيتم إشعارك عند الموافقة أو الرفض.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="donation-details-container">
            <!-- تفاصيل التبرع الأساسية -->
            <div class="card">
                <div class="card-header">
                    <h2><?php echo escape($donation['title']); ?></h2>
                    <span class="badge badge-<?php echo $donation['status']; ?>">
                        <?php
                        $statusText = [
                            'pending_charity_approval' => 'في انتظار الموافقة',
                            'available' => 'متاح',
                            'reserved' => 'محجوز',
                            'with_charity' => 'مع الجمعية',
                            'delivered' => 'تم التسليم',
                            'completed' => 'مكتمل',
                            'cancelled' => 'ملغي'
                        ];
                        echo $statusText[$donation['status']] ?? $donation['status'];
                        ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="detail-row">
                        <strong>الفئة:</strong>
                        <span><?php echo escape($donation['category']); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>الحالة:</strong>
                        <span><?php echo escape($donation['condition_item']); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>الكمية:</strong>
                        <span><?php echo escape($donation['quantity']); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>موقع الاستلام:</strong>
                        <span><?php echo escape($donation['pickup_location']); ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>الوصف:</strong>
                        <p><?php echo nl2br(escape($donation['description'])); ?></p>
                    </div>
                    <div class="detail-row">
                        <strong>تاريخ الإضافة:</strong>
                        <span><?php echo date('Y-m-d', strtotime($donation['created_at'])); ?></span>
                    </div>
                    
                    <?php if ($donation['charity_name']): ?>
                    <div class="detail-row">
                        <strong>الجمعية المسؤولة:</strong>
                        <span><?php echo escape($donation['charity_name']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- معلومات المتبرع (للمدير أو المستفيد المعتمد فقط) -->
                    <?php if ($userType === 'admin' || ($userType === 'beneficiary' && $donation['status'] === 'reserved')): ?>
                    <div class="donor-info">
                        <h3>معلومات المتبرع</h3>
                        <div class="detail-row">
                            <strong>الاسم:</strong>
                            <span><?php echo escape($donation['donor_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>رقم الهاتف:</strong>
                            <span><?php echo escape($donation['donor_phone']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>البريد الإلكتروني:</strong>
                            <span><?php echo escape($donation['donor_email']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- أزرار الإجراءات -->
                    <div class="action-buttons">
                        <?php if ($canRequest): ?>
                        <button type="button" class="btn btn-primary" onclick="showRequestModal()">
                            طلب هذا التبرع
                        </button>
                        <?php endif; ?>

                        <?php if ($canEdit && $donation['status'] === 'reserved'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" name="complete_donation" class="btn btn-success" 
                                    onclick="return confirm('هل أنت متأكد من إتمام التبرع؟')">
                                تم التسليم
                            </button>
                        </form>
                        <?php endif; ?>

                        <?php if ($canEdit): ?>
                        <a href="edit-donation.php?id=<?php echo $donationId; ?>" class="btn btn-secondary">
                            تعديل التبرع
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- الطلبات (للمتبرع والمدير) -->
            <?php if ($canEdit && !empty($requests)): ?>
            <div class="card">
                <div class="card-header">
                    <h2>الطلبات على هذا التبرع (<?php echo count($requests); ?>)</h2>
                </div>
                <div class="card-body">
                    <div class="requests-list">
                        <?php foreach ($requests as $request): ?>
                        <div class="request-item">
                            <div class="request-info">
                                <h3><?php echo escape($request['full_name']); ?></h3>
                                <p><strong>السبب:</strong> <?php echo escape($request['message']); ?></p>
                                <p><strong>الهاتف:</strong> <?php echo escape($request['phone']); ?></p>
                                <p><strong>البريد:</strong> <?php echo escape($request['email']); ?></p>
                                <p><strong>العنوان:</strong> <?php echo escape($request['address']); ?></p>
                                <p><strong>التاريخ:</strong> <?php echo date('Y-m-d H:i', strtotime($request['created_at'])); ?></p>
                            </div>
                            <div class="request-status">
                                <span class="badge badge-<?php echo $request['status']; ?>">
                                    <?php 
                                    $requestStatus = [
                                        'pending' => 'قيد الانتظار',
                                        'approved' => 'مقبول',
                                        'rejected' => 'مرفوض'
                                    ];
                                    echo $requestStatus[$request['status']];
                                    ?>
                                </span>
                                
                                <?php if ($request['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" name="update_request" value="approve" 
                                            class="btn btn-sm btn-success" 
                                            onclick="return confirm('هل تريد قبول هذا الطلب؟')">
                                        قبول
                                    </button>
                                    <button type="submit" name="update_request" value="reject" 
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('هل تريد رفض هذا الطلب؟')">
                                        رفض
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    </section>

    <!-- Modal طلب التبرع -->
    <?php if ($canRequest): ?>
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeRequestModal()">&times;</span>
            <h2>طلب التبرع</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="reason">سبب الطلب *</label>
                    <textarea id="reason" name="reason" rows="4" required 
                              placeholder="يرجى توضيح سبب حاجتك لهذا التبرع..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" name="request_donation" class="btn btn-primary">
                        إرسال الطلب
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeRequestModal()">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function showRequestModal() {
        document.getElementById('requestModal').style.display = 'block';
    }

    function closeRequestModal() {
        document.getElementById('requestModal').style.display = 'none';
    }

    // إغلاق Modal عند النقر خارجه
    window.onclick = function(event) {
        const modal = document.getElementById('requestModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>

<?php require_once 'includes/footer.php'; ?>