<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Utils/auth.php';
require_once __DIR__ . '/../src/Services/NotificationManager.php';

Auth::startSession();

// التحقق من تسجيل دخول الإدارة
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$pageTitle = 'إدارة التذاكر والدعم الفني';
$action = $_GET['action'] ?? 'queue';
$notificationId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;

// Enhanced ticket management functionality
$viewMode = $_GET['view'] ?? 'queue'; // queue, thread, canned
$priority = $_GET['priority'] ?? '';
$assigneeFilter = $_GET['assignee'] ?? '';

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf();
    try {
        switch ($action) {
            case 'create':
                $data = [
                    'title' => $_POST['title'],
                    'message' => $_POST['message'],
                    'type' => $_POST['type'],
                    'priority' => $_POST['priority'],
                    'target_audience' => $_POST['target_audience'],
                    'start_date' => $_POST['start_date'] ?: date('Y-m-d H:i:s'),
                    'end_date' => $_POST['end_date'] ?: null,
                    'is_active' => isset($_POST['is_active']),
                    'show_on_pages' => !empty($_POST['show_on_pages']) ? explode(',', $_POST['show_on_pages']) : null,
                    'dismissible' => isset($_POST['dismissible']),
                    'auto_dismiss_after' => intval($_POST['auto_dismiss_after']),
                    'click_action' => $_POST['click_action'] ?: null,
                    'background_color' => $_POST['background_color'] ?: null,
                    'text_color' => $_POST['text_color'] ?: null,
                    'icon' => $_POST['icon'] ?: null,
                    'created_by' => $_SESSION['admin_user'] ?? 'admin'
                ];
                
                $result = NotificationManager::createNotification($data);
                if ($result) {
                    $successMessage = "تم إنشاء التذكرة بنجاح!";
                    $action = 'queue';
                } else {
                    $errorMessage = "حدث خطأ في إنشاء التذكرة";
                }
                break;
                
            case 'reply':
                // Process ticket reply with canned response support
                $ticketId = intval($_POST['ticket_id']);
                $replyContent = $_POST['reply_content'];
                $isInternal = isset($_POST['is_internal']);
                
                // Insert reply using notifications table as ticket system
                Database::query(
                    "INSERT INTO notifications (user_id, title, message, type, priority, created_at, status) 
                     VALUES (?, ?, ?, ?, ?, NOW(), 'delivered')",
                    [
                        $_POST['user_id'] ?? $_SESSION['admin_user_id'] ?? 1,
                        $isInternal ? '(موظف فقط) رد على التذكرة #' . $ticketId : 'رد على تذكرتك',
                        $replyContent,
                        $isInternal ? 'ticket_reply_internal' : 'ticket_reply',
                        'medium',
                    ]
                );
                
                // Update ticket status if resolved
                if (isset($_POST['close_ticket'])) {
                    Database::query("UPDATE notifications SET status = 'closed' WHERE id = ?", [$ticketId]);
                    $successMessage = "تم الرد على التذكرة وإغلاقها!";
                } else {
                    Database::query("UPDATE notifications SET status = 'replied' WHERE id = ?", [$ticketId]);
                    $successMessage = "تم الرد على التذكرة!";
                }
                break;
                
            case 'assign':
                // Assign ticket to staff member
                $ticketId = intval($_POST['ticket_id']);
                $assigneeId = intval($_POST['assignee_id']);
                
                Database::query(
                    "UPDATE notifications SET assigned_to = ? WHERE id = ?",
                    [$assigneeId, $ticketId]
                );
                
                $successMessage = "تم تكليف التذكرة للموظف!";
                break;
                
            case 'update_status':
                // Update ticket status (open, closed, pending, resolved)
                $ticketId = intval($_POST['ticket_id']);
                $newStatus = $_POST['new_status'];
                
                Database::query(
                    "UPDATE notifications SET status = ? WHERE id = ?",
                    [$newStatus, $ticketId]
                );
                
                $successMessage = "تم تحديث حالة التذكرة!";
                break;
                
            case 'update':
                if ($notificationId) {
                    $data = [
                        'title' => $_POST['title'],
                        'message' => $_POST['message'],
                        'type' => $_POST['type'],
                        'priority' => $_POST['priority'],
                        'target_audience' => $_POST['target_audience'],
                        'start_date' => $_POST['start_date'],
                        'end_date' => $_POST['end_date'] ?: null,
                        'is_active' => isset($_POST['is_active']),
                        'show_on_pages' => !empty($_POST['show_on_pages']) ? explode(',', $_POST['show_on_pages']) : null,
                        'dismissible' => isset($_POST['dismissible']),
                        'auto_dismiss_after' => intval($_POST['auto_dismiss_after']),
                        'click_action' => $_POST['click_action'] ?: null,
                        'background_color' => $_POST['background_color'] ?: null,
                        'text_color' => $_POST['text_color'] ?: null,
                        'icon' => $_POST['icon'] ?: null
                    ];
                    
                    $result = NotificationManager::updateNotification($notificationId, $data);
                    if ($result) {
                        $successMessage = "تم تحديث الإشعار بنجاح!";
                        $action = 'list';
                    } else {
                        $errorMessage = "حدث خطأ في تحديث الإشعار";
                    }
                }
                break;
                
            case 'delete':
                if ($notificationId) {
                    $result = NotificationManager::deleteNotification($notificationId);
                    if ($result) {
                        $successMessage = "تم حذف الإشعار بنجاح!";
                    } else {
                        $errorMessage = "حدث خطأ في حذف الإشعار";
                    }
                }
                $action = 'list';
                break;
                
            case 'cleanup':
                $result = NotificationManager::cleanupExpiredNotifications();
                if ($result) {
                    $successMessage = "تم تنظيف الإشعارات المنتهية الصلاحية بنجاح!";
                } else {
                    $errorMessage = "حدث خطأ في تنظيف الإشعارات";
                }
                $action = 'list';
                break;
        }
    } catch (Exception $e) {
        $errorMessage = "حدث خطأ: " . $e->getMessage();
    }
}

// جلب البيانات حسب الإجراء
switch ($action) {
    case 'create':
    case 'edit':
        $notification = null;
        if ($action === 'edit' && $notificationId) {
            $notifications = NotificationManager::getAllNotifications(1, 0);
            $notification = array_filter($notifications, function($n) use ($notificationId) {
                return $n['id'] == $notificationId;
            });
            $notification = reset($notification) ?: null;
        }
        break;
        
    case 'stats':
        $notification = null;
        if ($notificationId) {
            $notifications = NotificationManager::getAllNotifications(1, 0);
            $notification = array_filter($notifications, function($n) use ($notificationId) {
                return $n['id'] == $notificationId;
            });
            $notification = reset($notification) ?: null;
        }
        break;
        
    default:
        // Enhanced ticket queue processing
        try {
            // Get ticket data grouped by status and priority
            $ticketQueue = Database::fetchAll(
                "SELECT 
                    n.id, n.user_id, n.title, n.message, n.type, n.priority, n.status, 
                    n.created_at, n.updated_at, n.assigned_to,
                    u.name as user_name, u.email as user_email, u.phone as user_phone,
                    ROUND(TIMESTAMPDIFF(HOUR, n.created_at, NOW()), 1) as hours_open,
                    COUNT(r.id) as reply_count
                 FROM notifications n
                 LEFT JOIN users u ON n.user_id = u.id
                 LEFT JOIN notifications r ON r.type LIKE '%reply%' AND r.message LIKE CONCAT('%#', n.id, '%')
                 WHERE n.type LIKE '%support%' OR n.type LIKE '%ticket%'
                 GROUP BY n.id
                 ORDER BY 
                     CASE n.status 
                         WHEN 'open' THEN 1 
                         WHEN 'urgent' THEN 2 
                         WHEN 'pending' THEN 3 
                         WHEN 'replied' THEN 4 
                         WHEN 'closed' THEN 5 
                     END,
                     n.priority DESC,
                     n.created_at DESC",
                []
            );
            
            // Group tickets by status for queue view
            $ticketsByStatus = [];
            foreach ($ticketQueue as $ticket) {
                $status = $ticket['status'] ?: 'open';
                $ticketsByStatus[$status][] = $ticket;
            }
            
            // Staff members for assignment (respecting roles)
            $staffMembers = Database::fetchAll(
                "SELECT id, name, email FROM users WHERE role IN ('admin', 'staff', 'support') ORDER BY name",
                []
            );
            
            // Canned replies (using existing constants if no store exists)
            $cannedReplies = [
                [
                    'id' => 'welcome',
                    'title' => 'ترحيب بالعميل',
                    'content' => 'مرحباً بك و نود شكرك لتواصلك معنا.'
                ],
                [
                    'id' => 'investigating',
                    'title' => 'تحقيق في المشكلة',
                    'content' => 'نحن الآن نحقق في هذه المشكلة و سنوفر لك تحديثاً خلال 24 ساعة'
                ],
                [
                    'id' => 'needs_info',
                    'title' => 'طلب معلومات إضافية',
                    'content' => 'نحتاج بعض المعلومات الإضافية لحل مشكلتك. هل يمكنك تزويدنا بـ...'
                ],
                [
                    'id' => 'resolved',
                    'title' => 'تم حل المشكلة',
                    'content' => 'تم حل المشكلة بنجاح. إذا كانت لديك أي استفسارات أخرى فلا تتردد في السؤال.'
                ],
                [
                    'id' => 'payment_help',
                    'title' => 'مساعدة في الدفع',
                    'content' => 'يمكننا مساعدتك في حل مشكلة الدفع. يرجى تأكيد تفاصيل طلبك.'
                ],
                [
                    'id' => 'service_down',
                    'title' => 'خدمة غير متاحة مؤقتاً',
                    'content' => 'نعتذر عن هذه المشكلة. هناك صيانة مجدولة للخادم ونعمل على إعادة الخدمة.'
                ]
            ];
            
        } catch (Exception $e) {
            $ticketQueue = [];
            $ticketsByStatus = [];
            $staffMembers = [];
            $cannedReplies = [];
            $errorMessage = 'خطأ في جلب بيانات التذاكر: ' . $e->getMessage();
        }
        break;
}

include __DIR__ . '/../templates/partials/header.php';
?>

<div class="container-fluid">
    <!-- Enhanced Ticket Management Header -->
    <div class="tickets-header">
        <div class="tickets-header-left">
            <h1 class="tickets-title">
                <?php 
                if ($action === 'thread' && $notificationId): 
                    echo "التذكرة #" . $notificationId;
                elseif ($viewMode === 'canned'):
                    echo "الردود الجاهزة";
                else:
                    echo "طابور التذاكر والدعم الفني";
                endif;
                ?>
            </h1>
            <div class="tickets-subtitle">
                <?php if ($viewMode === 'queue'): ?>
                    <span class="badge badge-queue"><?php echo count($ticketQueue ?? []); ?> تذكرة</span>
                    <span class="badge badge-open"><?php echo count($ticketsByStatus['open'] ?? []); ?> مفتوحة</span>
                    <span class="badge badge-urgent"><?php echo count($ticketsByStatus['urgent'] ?? []); ?> عاجلة</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="tickets-header-right">
            <div class="view-controls">
                <a href="?view=queue<?php echo $priority ? '&priority=' . urlencode($priority) : ''; ?>" 
                   class="btn btn-sm <?php echo $viewMode === 'queue' ? 'btn-primary' : 'btn-outline'; ?>">
                   📋 طابور التذاكر
                </a>
                <a href="?view=canned" class="btn btn-sm <?php echo $viewMode === 'canned' ? 'btn-primary' : 'btn-outline'; ?>">
                   📝 الردود الجاهزة
                </a>
            </div>
        </div>
    </div>
        
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        
    <!-- Main Ticket Management Content -->
    <div class="tickets-content">
        
        <?php if ($viewMode === 'canned'): ?>
            <!-- Canned Replies Management -->
            <div class="canned-replies-section">
                <div class="section-header">
                    <h2>📝 إدارة الردود الجاهزة</h2>
                    <p>استخدم هذه الردود الجاهزة للرد السريع على التذاكر الشائعة</p>
                </div>
                
                <div class="canned-replies-grid">
                    <?php foreach ($cannedReplies as $reply): ?>
                        <div class="canned-reply-card" data-reply-id="<?php echo $reply['id']; ?>">
                            <div class="reply-header">
                                <h3><?php echo htmlspecialchars($reply['title']); ?></h3>
                                <span class="reply-type"><?php echo htmlspecialchars($reply['id']); ?></span>
                            </div>
                            <div class="reply-content">
                                <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                            </div>
                            <div class="reply-actions">
                                <button class="btn btn-sm btn-primary" onclick="insertCannedReply('<?php echo addslashes($reply['content']); ?>')">
                                    ✅ استخدام هذا الرد
                                </button>
                                <button class="btn btn-sm btn-outline" onclick="editCannedReply('<?php echo $reply['id']; ?>')">
                                    ✏️ تعديل
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        <?php elseif ($action === 'thread' && $notificationId): ?>
            <!-- Thread View for Individual Ticket -->
            <div class="ticket-thread-section">
                <?php 
                // Get the main ticket
                $mainTicket = null;
                foreach ($ticketQueue as $ticket) {
                    if ($ticket['id'] == $notificationId) {
                        $mainTicket = $ticket;
                        break;
                    }
                }
                ?>
                
                <?php if (empty($notifications)): ?>
                    <div class="alert alert-info">
                        لا توجد إشعارات حتى الآن
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>العنوان</th>
                                    <th>النوع</th>
                                    <th>الأولوية</th>
                                    <th>الجمهور المستهدف</th>
                                    <th>الإحصائيات</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($notification['message'], 0, 50)) . '...'; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $notification['type']; ?>">
                                                <?php echo htmlspecialchars($notification['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $notification['priority'] === 'high' ? 'warning' : ($notification['priority'] === 'urgent' ? 'error' : 'info'); ?>">
                                                <?php echo htmlspecialchars($notification['priority']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($notification['target_audience']); ?></td>
                                        <td>
                                            <small>
                                                المشاهدات: <?php echo $notification['total_views'] ?? 0; ?><br>
                                                الرفض: <?php echo $notification['total_dismissals'] ?? 0; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($notification['is_active']): ?>
                                                <span class="badge badge-success">نشط</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">غير نشط</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-secondary">تعديل</a>
                                            <a href="?action=stats&id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-info">إحصائيات</a>
                                            <a href="?action=delete&id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-error" onclick="return confirm('هل أنت متأكد من الحذف؟')">حذف</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
            <?php elseif ($action === 'create' || $action === 'edit'): ?>
                <!-- نموذج إنشاء/تعديل الإشعار -->
                <form method="POST" action="?action=<?php echo htmlspecialchars($action); ?><?php echo $notificationId ? '&id=' . htmlspecialchars($notificationId) : ''; ?>">
                    <?php echo Auth::csrfField(); ?>
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label for="title">عنوان الإشعار *</label>
                            <input type="text" name="title" id="title" required 
                                   value="<?php echo htmlspecialchars($notification['title'] ?? ''); ?>" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="type">نوع الإشعار</label>
                            <select name="type" id="type" class="form-control">
                                <option value="info" <?php echo ($notification['type'] ?? 'info') === 'info' ? 'selected' : ''; ?>>معلومات</option>
                                <option value="success" <?php echo ($notification['type'] ?? '') === 'success' ? 'selected' : ''; ?>>نجاح</option>
                                <option value="warning" <?php echo ($notification['type'] ?? '') === 'warning' ? 'selected' : ''; ?>>تحذير</option>
                                <option value="error" <?php echo ($notification['type'] ?? '') === 'error' ? 'selected' : ''; ?>>خطأ</option>
                                <option value="promotion" <?php echo ($notification['type'] ?? '') === 'promotion' ? 'selected' : ''; ?>>عرض ترويجي</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">رسالة الإشعار *</label>
                        <textarea name="message" id="message" required rows="4" class="form-control"><?php echo htmlspecialchars($notification['message'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="grid grid-3">
                        <div class="form-group">
                            <label for="priority">الأولوية</label>
                            <select name="priority" id="priority" class="form-control">
                                <option value="low" <?php echo ($notification['priority'] ?? 'normal') === 'low' ? 'selected' : ''; ?>>منخفضة</option>
                                <option value="normal" <?php echo ($notification['priority'] ?? 'normal') === 'normal' ? 'selected' : ''; ?>>عادية</option>
                                <option value="high" <?php echo ($notification['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>عالية</option>
                                <option value="urgent" <?php echo ($notification['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>عاجلة</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="target_audience">الجمهور المستهدف</label>
                            <select name="target_audience" id="target_audience" class="form-control">
                                <option value="all" <?php echo ($notification['target_audience'] ?? 'all') === 'all' ? 'selected' : ''; ?>>جميع الزوار</option>
                                <option value="logged_in" <?php echo ($notification['target_audience'] ?? '') === 'logged_in' ? 'selected' : ''; ?>>المستخدمين المسجلين فقط</option>
                                <option value="guests" <?php echo ($notification['target_audience'] ?? '') === 'guests' ? 'selected' : ''; ?>>الزوار غير المسجلين فقط</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="auto_dismiss_after">الاختفاء التلقائي (ثانية)</label>
                            <input type="number" name="auto_dismiss_after" id="auto_dismiss_after" 
                                   value="<?php echo $notification['auto_dismiss_after'] ?? 0; ?>" 
                                   min="0" max="300" class="form-control">
                            <small class="text-muted">0 = لا يختفي تلقائياً</small>
                        </div>
                    </div>
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label for="start_date">تاريخ البداية</label>
                            <input type="datetime-local" name="start_date" id="start_date" 
                                   value="<?php echo $notification['start_date'] ? date('Y-m-d\TH:i', strtotime($notification['start_date'])) : date('Y-m-d\TH:i'); ?>" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">تاريخ النهاية</label>
                            <input type="datetime-local" name="end_date" id="end_date" 
                                   value="<?php echo $notification['end_date'] ? date('Y-m-d\TH:i', strtotime($notification['end_date'])) : ''; ?>" 
                                   class="form-control">
                            <small class="text-muted">اتركه فارغاً للعرض المستمر</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="show_on_pages">الصفحات المحددة</label>
                        <input type="text" name="show_on_pages" id="show_on_pages" 
                               value="<?php echo $notification['show_on_pages'] ? implode(',', json_decode($notification['show_on_pages'], true)) : ''; ?>" 
                               placeholder="index.php,catalog.php,order.php" class="form-control">
                        <small class="text-muted">أسماء الملفات مفصولة بفواصل، اتركه فارغاً للعرض في جميع الصفحات</small>
                    </div>
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label for="click_action">إجراء النقر</label>
                            <input type="url" name="click_action" id="click_action" 
                                   value="<?php echo htmlspecialchars($notification['click_action'] ?? ''); ?>" 
                                   placeholder="https://example.com" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="icon">الأيقونة</label>
                            <input type="text" name="icon" id="icon" 
                                   value="<?php echo htmlspecialchars($notification['icon'] ?? ''); ?>" 
                                   placeholder="🔔 أو emoji" class="form-control">
                        </div>
                    </div>
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label for="background_color">لون الخلفية</label>
                            <input type="color" name="background_color" id="background_color" 
                                   value="<?php echo $notification['background_color'] ?? '#1A3C8C'; ?>" 
                                   class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="text_color">لون النص</label>
                            <input type="color" name="text_color" id="text_color" 
                                   value="<?php echo $notification['text_color'] ?? '#FFFFFF'; ?>" 
                                   class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" <?php echo ($notification['is_active'] ?? true) ? 'checked' : ''; ?>>
                            نشط
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="dismissible" <?php echo ($notification['dismissible'] ?? true) ? 'checked' : ''; ?>>
                            يمكن رفضه
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $action === 'edit' ? 'تحديث' : 'إنشاء'; ?>
                        </button>
                        <a href="?" class="btn btn-secondary">إلغاء</a>
                    </div>
                </form>
                
            <?php elseif ($action === 'stats' && $notification): ?>
                <!-- إحصائيات الإشعار -->
                <div class="grid grid-2">
                    <div class="card">
                        <div class="card-header">
                            <h3>تفاصيل الإشعار</h3>
                        </div>
                        <div class="card-body">
                            <p><strong>العنوان:</strong> <?php echo htmlspecialchars($notification['title']); ?></p>
                            <p><strong>النوع:</strong> <?php echo htmlspecialchars($notification['type']); ?></p>
                            <p><strong>الأولوية:</strong> <?php echo htmlspecialchars($notification['priority']); ?></p>
                            <p><strong>تاريخ الإنشاء:</strong> <?php echo Formatters::formatDateTime($notification['created_at']); ?></p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h3>الإحصائيات</h3>
                        </div>
                        <div class="card-body">
                            <p><strong>إجمالي المشاهدات:</strong> <?php echo $notification['total_views'] ?? 0; ?></p>
                            <p><strong>المشاهدات الفريدة:</strong> <?php echo $notification['unique_views'] ?? 0; ?></p>
                            <p><strong>عدد الرفض:</strong> <?php echo $notification['total_dismissals'] ?? 0; ?></p>
                            <p><strong>آخر مشاهدة:</strong> <?php echo $notification['last_viewed_at'] ? Formatters::formatDateTime($notification['last_viewed_at']) : 'لم يتم مشاهدة'; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="?" class="btn btn-primary">العودة للقائمة</a>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 6px;
    text-transform: uppercase;
}

.badge-info { background: var(--primary-color); color: white; }
.badge-success { background: var(--success-color); color: white; }
.badge-warning { background: var(--warning-color); color: #000; }
.badge-error { background: var(--error-color); color: white; }
.badge-secondary { background: #6c757d; color: white; }

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>

<script>
// Add loading states for notification actions
(function() {
    'use strict';
    
    // PRODUCTION GUARDS - Disable all debug output
    window.__DEBUG__ = false;
    window.__PROD__ = true;
    
    // Disable console methods in production
    if (!window.__DEBUG__) {
        for (const m of ['log','debug','info','warn','table','trace','time','timeEnd']) {
            console[m] = ()=>{};
        }
        if (window.performance) {
            ['mark','measure','clearMarks','clearMeasures'].forEach(k=>{
                if(performance[k]) performance[k]=()=>{};
            });
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        var forms = document.querySelectorAll('form[method="POST"]');
        
        forms.forEach(function(form) {
            form.addEventListener('submit', function() {
                var card = document.querySelector('.card');
                if (card && window.showLoading) {
                    window.showLoading(card, 'جاري المعالجة...');
                }
            });
        });
    });
})();

// Enhanced notifications management with persistence and search
document.addEventListener('DOMContentLoaded', function() {
    const screenName = 'notifications_management';
    
    // Restore saved preferences
    const savedPrefs = loadTablePreferences(screenName) || {};
    
    // Set up table view management
    const tableContainer = document.querySelector('.table');
    if (tableContainer) {
        // Add search functionality
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = '🔍 البحث في الإشعارات...';
        searchInput.className = 'form-control';
        searchInput.style.cssText = 'margin-bottom: 1rem; max-width: 300px;';
        
        // Insert search input before table
        tableContainer.parentNode.insertBefore(searchInput, tableContainer.parentNode.querySelector('.table').previousElementSibling);
        
        // Setup debounced search
        setupDebouncedSearch(searchInput, screenName, function(searchTerm) {
            if (!searchTerm.trim()) {
                // Show all rows when search is empty
                document.querySelectorAll('tbody tr').forEach(row => {
                    row.style.display = '';
                });
                return;
            }
            
            // Filter rows based on search term
            document.querySelectorAll('tbody tr').forEach(row => {
                const searchText = row.textContent.trim().toLowerCase();
                row.style.display = searchText.includes(searchTerm.toLowerCase()) ? '' : 'none';
            });
            
            // Save search state
            savedPrefs.lastSearch = searchTerm;
            saveTablePreferences(screenName, savedPrefs);
        });
        
        // Restore last search
        if (savedPrefs.lastSearch) {
            searchInput.value = savedPrefs.lastSearch;
            const event = new Event('input');
            searchInput.dispatchEvent(event);
        }   
    }
    
    // Enhanced confirmation for dangerous actions
    document.querySelectorAll('a[onclick*="confirm"]').forEach(link => {
        const originalConfirm = link.onclick;
        link.onclick = function(e) {
            e.preventDefault();
            const originalHref = this.href;
            
            if (confirmAction('⚠️ هل أنت متأكد من هذا الإجراء؟\n\nهذا الإجراء لا يمكن التراجع عنه.')) {
                window.location.href = originalHref;
            }
            return false;
        };
    });
    
    // Save view state automatically
    const observer = new MutationObserver(() => {
        saveTablePreferences(screenName, {
            ...savedPrefs,
            timestamp: Date.now()
        });
    });
    
    const tableBody = document.querySelector('tbody');
    if (tableBody) {
        observer.observe(tableBody, { childList: true, subtree: true });
    }
});

// Enhanced Ticket Management JavaScript
function insertCannedReply(content) {
    const textarea = document.getElementById('replyContent');
    if (textarea) {
        textarea.value = content;
        textarea.focus();
        textarea.setSelectionRange(content.length, content.length);
    }
}

function editCannedReply(replyId) {
    // In a real implementation, open edit modal
    // Silent handling in production
}

function assignTicket(ticketId, staffId) {
    if (!staffId) return;
    
    if (confirm('هل تريد تكليف هذه التذكرة للموظف المحدد؟')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="ticket_id" value="${ticketId}">
            <input type="hidden" name="assignee_id" value="${staffId}">
            ${document.querySelector('input[name="csrf_token"]').outerHTML}
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function confirmTicketAction(action, ticketId) {
    const actionText = {
        'approve': 'الموافقة على التذكرة',
        'reject': 'رفض التذكرة',
        'close': 'إغلاق التذكرة'
    }[action] || action;
    
    return confirm(`هل تريد تأكيد ${actionText} #${ticketId}؟`);
}

// Auto-scroll to bottom of thread
document.addEventListener('DOMContentLoaded', function() {
    const threadMessages = document.getElementById('threadMessages');
    if (threadMessages) {
        threadMessages.scrollTop = threadMessages.scrollHeight;
    }
    
    // Enhanced reply form
    const replyForm = document.getElementById('ticketReplyForm');
    if (replyForm) {
        replyForm.addEventListener('submit', function(e) {
            const content = document.getElementById('replyContent').value.trim();
            if (!content) {
                e.preventDefault();
                // Silent handling in production
                return;
            }
            
            if (!confirm('هل تريد إرسال هذا الرد؟')) {
                e.preventDefault();
                return;
            }
        });
    }
});

// Canned reply buttons functionality
document.querySelectorAll('.btn-canned-quick').forEach(btn => {
    btn.addEventListener('click', function() {
        const content = this.dataset.content;
        insertCannedReply(content);
    });
});
</script>

<!-- Enhanced Ticket Management Styles -->
<style>
/* Ticket Management Layout */
.tickets-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: var(--card-bg);
    border-radius: 12px;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
}

.tickets-title {
    margin: 0 0 0.5rem 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
}

.tickets-subtitle {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-queue { background: rgba(13, 110, 253, 0.15); color: #0d6efd; }
.badge-open { background: rgba(255, 193, 7, 0.15); color: #ffc107; }
.badge-urgent { background: rgba(220, 53, 69, 0.15); color: #dc3545; }

.view-controls {
    display: flex;
    gap: 0.5rem;
}

/* Status Columns Layout */
.tickets-status-columns {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.status-column {
    background: var(--color-elev);
    border-radius: 12px;
    border: 1px solid var(--border-color);
    min-height: 400px;
}

.column-header {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.column-icon {
    font-size: 1.5rem;
}

.column-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.column-count {
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
    margin-inline-start: auto;
}

/* Ticket Cards */
.ticket-card {
    background: var(--card-bg);
    border-radius: 8px;
    padding: 1rem;
    margin: 0.5rem;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
    cursor: pointer;
}

.ticket-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.ticket-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.ticket-id-small {
    font-weight: 700;
    color: var(--primary-color);
    font-size: 0.9rem;
}

.ticket-title-small {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    line-height: 1.3;
}

.ticket-user-small {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
}

.ticket-meta-small {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.ticket-priority-small {
    padding: 0.125rem 0.375rem;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 600;
}

.sla-small {
    font-size: 0.7rem;
    font-weight: 600;
}

.ticket-time {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-bottom: 0.75rem;
}

.ticket-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.assign-select {
    padding: 0.25rem 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 0.8rem;
    background: var(--card-bg);
    color: var(--text-primary);
}

/* Priority Colors */
.priority-urgent { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
.priority-high { background: rgba(255, 152, 0, 0.15); color: #ff9800; }
.priority-medium { background: rgba(13, 110, 253, 0.15); color: #0d6efd; }
.priority-low { background: rgba(40, 167, 69, 0.15); color: #28a745; }

/* Status Colors */
.status-open { background: rgba(255, 193, 7, 0.15); color: #ffc107; }
.status-urgent { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
.status-pending { background: rgba(13, 202, 240, 0.15); color: #0dcaf0; }
.status-replied { background: rgba(13, 110, 253, 0.15); color: #0d6efd; }
.status-closed { background: rgba(40, 167, 69, 0.15); color: #28a745; }

/* Canned Replies */
.canned-replies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.canned-reply-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.canned-reply-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.reply-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.reply-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.reply-type {
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 8px;
    font-size: 0.8rem;
}

.reply-content {
    margin-bottom: 1rem;
    color: var(--text-primary);
    line-height: 1.5;
}

.reply-actions {
    display: flex;
    gap: 0.5rem;
}

/* Thread View */
.ticket-thread-container {
    max-width: 900px;
    margin: 0 auto;
}

.ticket-header-card {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.ticket-header-left {
    flex: 1;
}

.ticket-id {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.ticket-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.ticket-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.ticket-header-right {
    margin-inline-start: 1rem;
}

.user-avatar-large {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    margin-bottom: 0.5rem;
}

.user-details {
    text-align: center;
}

.user-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.user-contact {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.thread-messages-container {
    background: var(--color-elev);
    border-radius: 12px;
    padding: 1rem;
    max-height: 500px;
    overflow-y: auto;
    margin-bottom: 1rem;
}

.thread-message {
    background: var(--card-bg);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid var(--border-color);
}

.thread-message:last-child {
    margin-bottom: 0;
}

.internal-reply {
    border-inline-start: 4px solid var(--warning-color);
    background: rgba(255, 193, 7, 0.05);
}

.message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.message-author {
    font-weight: 600;
    color: var(--text-primary);
}

.message-time {
    color: var(--text-secondary);
}

.message-content {
    color: var(--text-primary);
    line-height: 1.5;
}

.reply-form-container {
    background: var(--card-bg);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border-color);
}

.reply-editor {
    margin-bottom: 1rem;
}

.reply-textarea {
    width: 100%;
    padding: 1rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--card-bg);
    color: var(--text-primary);
    font-size: 1rem;
    resize: vertical;
    min-height: 120px;
}

.canned-quick-insert {
    margin-top: 0.5rem;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    align-items: center;
}

.btn-canned-quick {
    background: none;
    border: 1px solid var(--border-color);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.8rem;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-canned-quick:hover {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .tickets-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .tickets-status-columns {
        grid-template-columns: 1fr;
    }
    
    .canned-replies-grid {
        grid-template-columns: 1fr;
    }
    
    .ticket-header-card {
        flex-direction: column;
        text-align: center;
    }
    
    .ticket-header-right {
        margin-inline-start: 0;
        margin-top: 1rem;
    }
    
    .ticket-meta {
        justify-content: center;
    }
}

@media (max-width: 430px) {
    .tickets-header {
        padding: 1rem;
    }
    
    .priority-filters {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .ticket-card {
        margin: 0.25rem;
        padding: 0.75rem;
    }
    
    .reply-form-container {
        padding: 1rem;
    }
}
</style>

<?php include __DIR__ . '/../templates/partials/footer.php'; ?>
