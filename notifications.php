<?php
require_once 'config.php';
checkLogin();

$pageTitle = 'الإشعارات';
$pageDescription = 'تابع جميع الإشعارات والتحديثات الخاصة بك';

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// معالجة تحديث حالة الإشعار
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['mark_as_read']) && verifyCSRFToken($_POST['csrf_token'])) {
        $notificationId = intval($_POST['notification_id']);
        
        $updateStmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ");
        $updateStmt->execute([$notificationId, $userId]);
        
        header("Location: notifications.php");
        exit();
    }
    
    if (isset($_POST['mark_all_read']) && verifyCSRFToken($_POST['csrf_token'])) {
        $updateAllStmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? AND is_read = 0
        ");
        $updateAllStmt->execute([$userId]);
        
        $success = "تم تحديث جميع الإشعارات كمقروءة";
    }
    
    if (isset($_POST['delete_notification']) && verifyCSRFToken($_POST['csrf_token'])) {
        $notificationId = intval($_POST['notification_id']);
        
        $deleteStmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $deleteStmt->execute([$notificationId, $userId]);
        
        header("Location: notifications.php");
        exit();
    }
    
    if (isset($_POST['delete_all']) && verifyCSRFToken($_POST['csrf_token'])) {
        $deleteAllStmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
        $deleteAllStmt->execute([$userId]);
        
        $success = "تم حذف جميع الإشعارات";
    }
}

// الفلترة
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$type = isset($_GET['type']) ? $_GET['type'] : 'all';

// بناء الاستعلام
$sql = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$userId];

if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $sql .= " AND is_read = 1";
}

if ($type !== 'all') {
    $sql .= " AND type = ?";
    $params[] = $type;
}

$sql .= " ORDER BY created_at DESC";

// التقسيم إلى صفحات
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// إجمالي الإشعارات
$countStmt = $db->prepare(str_replace("SELECT *", "SELECT COUNT(*)", $sql));
$countStmt->execute($params);
$totalNotifications = $countStmt->fetchColumn();
$totalPages = ceil($totalNotifications / $perPage);

// جلب الإشعارات
$sql .= " LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// إحصائيات
$statsStmt = $db->prepare("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread,
        COUNT(CASE WHEN is_read = 1 THEN 1 END) as `read`
    FROM notifications
    WHERE user_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();
?>
<?php require_once 'includes/header.php'; ?>
    <style>
        .notification-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            transition: all 0.3s ease;
        }
        
        .notification-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .notification-item.unread {
            background: #f0f7ff;
            border-right: 4px solid #2196F3;
        }
        
        .notification-icon {
            font-size: 24px;
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            border-radius: 50%;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .notification-message {
            color: #666;
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        .notification-time {
            font-size: 12px;
            color: #999;
        }
        
        .notification-actions {
            display: flex;
            gap: 5px;
            flex-direction: column;
        }
        
        .notification-actions button {
            padding: 5px 10px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            background: #f5f5f5;
            color: #666;
            transition: all 0.2s;
        }
        
        .notification-actions button:hover {
            background: #e0e0e0;
        }
        
        .filters-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 20px;
            background: white;
            color: #666;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .filter-tab:hover {
            background: #f5f5f5;
        }
        
        .filter-tab.active {
            background: #2196F3;
            color: white;
            border-color: #2196F3;
        }
        
        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #666;
        }
        
        .pagination a:hover {
            background: #f5f5f5;
        }
        
        .pagination .active {
            background: #2196F3;
            color: white;
            border-color: #2196F3;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                font-size: 1.5rem;
            }

            .stat-details h3 {
                font-size: 1.5rem;
            }

            .filters-tabs {
                gap: 0.5rem;
            }

            .filter-tab {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }

            .bulk-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .bulk-actions form {
                width: 100%;
            }

            .bulk-actions button {
                width: 100%;
            }

            .notification-item {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .notification-icon {
                font-size: 1.5rem;
                min-width: 30px;
                height: 30px;
            }

            .notification-actions {
                flex-direction: row;
                width: 100%;
                justify-content: flex-end;
            }

            .pagination {
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .pagination a,
            .pagination span {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }
        }
    </style>

    <!-- Main Content -->
    <section style="padding: 2rem 0; min-height: 70vh;">
    <div class="container">
        <div class="page-header mb-4">
            <h1 style="color: var(--primary-color);">الإشعارات</h1>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo escape($success); ?></div>
        <?php endif; ?>

        <!-- إحصائيات -->
        <div class="stats-grid mb-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: var(--radius); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 1rem; transition: var(--transition);">
                <div class="stat-icon" style="font-size: 2.5rem;">🔔</div>
                <div class="stat-details">
                    <h3 style="font-size: 2rem; color: var(--primary-color); margin: 0;"><?php echo $stats['total']; ?></h3>
                    <p style="margin: 0.25rem 0 0 0; color: #666; font-size: 0.9rem;">إجمالي الإشعارات</p>
                </div>
            </div>
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: var(--radius); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 1rem; transition: var(--transition);">
                <div class="stat-icon" style="font-size: 2.5rem;">📩</div>
                <div class="stat-details">
                    <h3 style="font-size: 2rem; color: var(--warning-color); margin: 0;"><?php echo $stats['unread']; ?></h3>
                    <p style="margin: 0.25rem 0 0 0; color: #666; font-size: 0.9rem;">غير مقروءة</p>
                </div>
            </div>
            <div class="stat-card" style="background: white; padding: 1.5rem; border-radius: var(--radius); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 1rem; transition: var(--transition);">
                <div class="stat-icon" style="font-size: 2.5rem;">✅</div>
                <div class="stat-details">
                    <h3 style="font-size: 2rem; color: var(--success-color); margin: 0;"><?php echo $stats['read']; ?></h3>
                    <p style="margin: 0.25rem 0 0 0; color: #666; font-size: 0.9rem;">مقروءة</p>
                </div>
            </div>
        </div>

        <!-- الفلاتر -->
        <div class="filters-tabs">
            <a href="?filter=all&type=<?php echo $type; ?>" 
               class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                الكل
            </a>
            <a href="?filter=unread&type=<?php echo $type; ?>" 
               class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                غير مقروءة (<?php echo $stats['unread']; ?>)
            </a>
            <a href="?filter=read&type=<?php echo $type; ?>" 
               class="filter-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">
                مقروءة
            </a>
            
            <span style="margin: 0 10px;">|</span>
            
            <a href="?filter=<?php echo $filter; ?>&type=all" 
               class="filter-tab <?php echo $type === 'all' ? 'active' : ''; ?>">
                جميع الأنواع
            </a>
            <a href="?filter=<?php echo $filter; ?>&type=info" 
               class="filter-tab <?php echo $type === 'info' ? 'active' : ''; ?>">
                معلومات
            </a>
            <a href="?filter=<?php echo $filter; ?>&type=success" 
               class="filter-tab <?php echo $type === 'success' ? 'active' : ''; ?>">
                نجاح
            </a>
            <a href="?filter=<?php echo $filter; ?>&type=warning" 
               class="filter-tab <?php echo $type === 'warning' ? 'active' : ''; ?>">
                تحذير
            </a>
            <a href="?filter=<?php echo $filter; ?>&type=error" 
               class="filter-tab <?php echo $type === 'error' ? 'active' : ''; ?>">
                خطأ
            </a>
        </div>

        <!-- الإجراءات الجماعية -->
        <?php if (!empty($notifications)): ?>
        <div class="bulk-actions">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <button type="submit" name="mark_all_read" class="btn btn-sm btn-primary"
                        onclick="return confirm('تحديث جميع الإشعارات كمقروءة؟')">
                    تحديد الكل كمقروء
                </button>
            </form>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <button type="submit" name="delete_all" class="btn btn-sm btn-danger"
                        onclick="return confirm('هل أنت متأكد من حذف جميع الإشعارات؟')">
                    حذف الكل
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- قائمة الإشعارات -->
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <p>لا توجد إشعارات</p>
            </div>
        <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                    <div class="notification-icon">
                        <?php
                        $icons = [
                            'info' => 'ℹ️',
                            'success' => '✅',
                            'warning' => '⚠️',
                            'error' => '❌'
                        ];
                        echo $icons[$notification['type']] ?? '🔔';
                        ?>
                    </div>
                    
                    <div class="notification-content">
                        <div class="notification-title"><?php echo escape($notification['title']); ?></div>
                        <div class="notification-message"><?php echo escape($notification['message']); ?></div>
                        <div class="notification-time">
                            <?php 
                            $time = strtotime($notification['created_at']);
                            $diff = time() - $time;
                            
                            if ($diff < 60) {
                                echo "منذ لحظات";
                            } elseif ($diff < 3600) {
                                echo "منذ " . floor($diff / 60) . " دقيقة";
                            } elseif ($diff < 86400) {
                                echo "منذ " . floor($diff / 3600) . " ساعة";
                            } else {
                                echo date('Y-m-d H:i', $time);
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="notification-actions">
                        <?php if (!$notification['is_read']): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                            <button type="submit" name="mark_as_read" title="تحديد كمقروء">
                                ✓
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                            <button type="submit" name="delete_notification" 
                                    onclick="return confirm('حذف هذا الإشعار؟')" title="حذف">
                                🗑️
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- التقسيم إلى صفحات -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?filter=<?php echo $filter; ?>&type=<?php echo $type; ?>&page=<?php echo $page - 1; ?>">
                    السابق
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?filter=<?php echo $filter; ?>&type=<?php echo $type; ?>&page=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?filter=<?php echo $filter; ?>&type=<?php echo $type; ?>&page=<?php echo $page + 1; ?>">
                    التالي
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    </section>

<?php require_once 'includes/footer.php'; ?>