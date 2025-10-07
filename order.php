<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/src/Utils/db.php';
require_once BASE_PATH . '/src/Services/PeakerrClient.php';
require_once BASE_PATH . '/src/Utils/auth.php';

Auth::startSession();
$currentUser = Auth::currentUser();

// التحقق من تسجيل الدخول
if (!$currentUser) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/order.php';
    header('Location: /auth/login.php');
    exit;
}

// سيتم تعيين pageTitle و pageDescription بناءً على الخدمة المحددة
$pageTitle = 'تأكيد الطلب';
$pageDescription = 'اطلب خدمات وسائل التواصل الاجتماعي والألعاب بسهولة وأمان';
$ogType = 'product';
$message = '';
$messageType = '';

// التحقق من طريقة الطلب - السماح بـ GET للانتقال من صفحة الخدمات
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['service'])) {
    // معالجة GET request من صفحة الخدمات
    $serviceId = isset($_GET['service']) ? intval($_GET['service']) : 0;
    
    if (!$serviceId) {
        header('Location: /catalog.php');
        exit;
    }
    
    // جلب بيانات الخدمة
    try {
        $service = Database::fetchOne("SELECT * FROM services_cache WHERE id = ?", [$serviceId]);
        
        if (!$service) {
            $errorMessage = "الخدمة غير موجودة";
            $pageTitle = 'خطأ - الخدمة غير موجودة';
            $pageDescription = 'الخدمة المطلوبة غير متوفرة حالياً';
            require_once BASE_PATH . '/templates/partials/header.php';
            echo '<div class="container"><div class="alert alert-error">' . $errorMessage . '</div></div>';
            require_once BASE_PATH . '/templates/partials/footer.php';
            exit;
        }
        
        // حساب السعر مرة واحدة
        $price = $service['rate_per_1k_lyd'] ?: ($service['rate_per_1k'] * EXCHANGE_USD_TO_LYD);
        
        // تحديث عنوان الصفحة بناءً على الخدمة
        $pageTitle = 'طلب خدمة: ' . ($service['name_ar'] ?? $service['name']);
        $pageDescription = 'اطلب خدمة ' . ($service['name_ar'] ?? $service['name']) . ' - ' . ($service['category_ar'] ?? $service['category']);
        
        // عرض صفحة الطلب
        require_once BASE_PATH . '/templates/partials/header.php';
        ?>
        
        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="breadcrumb-nav" style="margin-bottom: 1rem;">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="/catalog.php">الخدمات</a></li>
                    <li class="breadcrumb-item"><a href="/service.php?id=<?php echo $service['id']; ?>"><?php echo htmlspecialchars($service['name_ar'] ?: $service['name']); ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">تأكيد الطلب</li>
                </ol>
            </nav>
            
            <div class="card">
                <div class="card-header">
                    <h1 class="card-title">تأكيد الطلب</h1>
                    <p class="card-subtitle">تأكد من بيانات الطلب قبل الإرسال</p>
                </div>
                
                <div class="card-body">
                    <!-- عرض بيانات الخدمة -->
                    <div class="service-summary mb-4">
                        <h3><?php echo htmlspecialchars($service['name_ar'] ?: $service['name']); ?></h3>
                        <p><strong>التصنيف:</strong> <?php echo htmlspecialchars($service['category_ar'] ?: $service['category']); ?></p>
                        <p><strong>النوع:</strong> <?php echo htmlspecialchars($service['subcategory'] ?: $service['type']); ?></p>
                        <p><strong>الكمية:</strong> <?php echo Formatters::formatQuantity($service['min']); ?> - <?php echo Formatters::formatQuantity($service['max']); ?></p>
                        <p><strong>السعر:</strong> 
                            <?php echo Formatters::formatMoneyCompact($price); ?> LYD لكل 1000
                        </p>
                        <div class="total-price-display" style="background: rgba(201, 162, 39, 0.1); padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                            <p><strong>السعر الإجمالي:</strong> <span id="total-price" class="price-highlight">0.00 LYD</span></p>
                        </div>
                    </div>
                    
        <!-- نموذج الطلب -->
        <form method="POST" action="/order.php">
            <?php echo Auth::csrfField(); ?>
            <input type="hidden" name="service_id" value="<?php echo htmlspecialchars($service['id']); ?>">
                        
                        <div class="form-group">
                            <label for="quantity" class="form-label">الكمية المطلوبة:</label>
                            
                            <!-- Quick quantity chips -->
                            <div class="group-pills" style="margin-bottom: 0.75rem;">
                                <?php
                                $quickQuantities = [];
                                $min = (int)$service['min'];
                                $max = (int)$service['max'];
                                
                                // Add common quantities within range
                                if ($min <= 100 && $max >= 100) $quickQuantities[] = 100;
                                if ($min <= 500 && $max >= 500) $quickQuantities[] = 500;
                                if ($min <= 1000 && $max >= 1000) $quickQuantities[] = 1000;
                                if ($min <= 5000 && $max >= 5000) $quickQuantities[] = 5000;
                                
                                foreach ($quickQuantities as $qty): ?>
                                    <button type="button" class="group-pill" onclick="setQuantity(<?php echo $qty; ?>)"><?php echo number_format($qty); ?></button>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="number" 
                                   id="quantity" 
                                   name="quantity" 
                                   class="form-control" 
                                   min="<?php echo $service['min']; ?>" 
                                   max="<?php echo $service['max']; ?>"
                                   step="1"
                                   inputmode="numeric"
                                   pattern="[0-9]*"
                                   value="<?php echo $service['min']; ?>"
                                   aria-describedby="quantity-hint"
                                   required>
                            <div class="form-hint" id="quantity-hint">
                                <span>الحد: <?php echo number_format($service['min']); ?> - <?php echo number_format($service['max']); ?></span>
                                <span style="margin-right: 1rem;">⏱️ التسليم: 0-24 ساعة</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="target" class="form-label">الرابط أو اسم المستخدم:</label>
                            <input type="url" 
                                   id="target" 
                                   name="target" 
                                   class="form-control" 
                                   placeholder="https://example.com/username أو @username"
                                   aria-describedby="target-hint"
                                   required>
                            <div class="form-hint" id="target-hint">
                                <span>🔗 رابط كامل أو اسم المستخدم فقط</span>
                                <span style="margin-right: 1rem;">🔒 آمن ومحمي</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes" class="form-label">ملاحظات (اختياري):</label>
                            <textarea id="notes" 
                                      name="notes" 
                                      class="form-control" 
                                      rows="3" 
                                      placeholder="أي ملاحظات إضافية..."></textarea>
                        </div>
                        
                        <div class="form-group" style="position: sticky; bottom: 1rem; z-index: 10; background: var(--color-card); padding: 1rem 0; margin-top: 2rem; border-top: 1px solid var(--color-border);">
                            <button type="submit" class="btn btn-primary btn-block" style="margin-bottom: 1rem;">
                                ✅ تأكيد الطلب
                            </button>
                            <a href="/catalog.php" class="btn btn-secondary btn-block">
                                ← العودة للخدمات
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        // حساب السعر الإجمالي - نسخة مبسطة ومحسنة
        document.addEventListener('DOMContentLoaded', function() {
            var quantityInput = document.getElementById('quantity');
            var totalPriceElement = document.getElementById('total-price');
            
            // Get price from PHP
            var pricePer1k = parseFloat(<?php echo json_encode($price ?? 0); ?>);
            var minQty = parseInt(<?php echo json_encode($service['min'] ?? 1); ?>);
            var maxQty = parseInt(<?php echo json_encode($service['max'] ?? 1000000); ?>);
            
            if (!quantityInput || !totalPriceElement) {
                return;
            }
            
            if (!pricePer1k || pricePer1k <= 0) {
                totalPriceElement.textContent = 'سعر غير صحيح';
                return;
            }
            
            // Function to update total price
            function updateTotalPrice() {
                var quantity = parseInt(quantityInput.value) || 0;
                var totalPrice = (quantity / 1000) * pricePer1k;
                totalPriceElement.textContent = totalPrice.toFixed(2) + ' LYD';
            }
            
            // Function to validate quantity
            function validateQuantity() {
                var val = parseInt(quantityInput.value);
                if (val < minQty) {
                    quantityInput.setCustomValidity('الكمية يجب أن تكون على الأقل ' + minQty);
                } else if (val > maxQty) {
                    quantityInput.setCustomValidity('الكمية يجب ألا تتجاوز ' + maxQty);
                } else {
                    quantityInput.setCustomValidity('');
                }
            }
            
            // Global function for quick quantity setting
            window.setQuantity = function(qty) {
                quantityInput.value = qty;
                validateQuantity();
                updateTotalPrice();
                
                // Update active state of chips
                var chips = document.querySelectorAll('.group-pill');
                chips.forEach(function(chip) {
                    chip.classList.remove('active');
                });
                
                // Find and activate the clicked chip
                var clickedChip = document.querySelector('.group-pill[onclick*="' + qty + '"]');
                if (clickedChip) {
                    clickedChip.classList.add('active');
                }
            };
            
            // Event listeners
            quantityInput.addEventListener('input', function() {
                validateQuantity();
                updateTotalPrice();
            });
            
            quantityInput.addEventListener('change', function() {
                validateQuantity();
                updateTotalPrice();
            });
            
            quantityInput.addEventListener('keyup', function() {
                validateQuantity();
                updateTotalPrice();
            });
            
            // Initialize
            updateTotalPrice();
        });
        </script>
        
        <?php
        require_once BASE_PATH . '/templates/partials/footer.php';
        exit;
        
    } catch (Exception $e) {
        ErrorHandler::handleDatabaseError($e, "خطأ في جلب بيانات الخدمة. يرجى المحاولة لاحقاً.");
    }
}

// معالجة POST request (الطلب الفعلي)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /catalog.php');
    exit;
}

// Rate limiting - 5 orders per 5 minutes per session/IP
$rateLimitKey = 'order_attempts_' . (session_id() ?: $_SERVER['REMOTE_ADDR']);
$currentTime = time();
$timeWindow = 300; // 5 minutes
$maxAttempts = 5;

if (!isset($_SESSION['rate_limits'])) {
    $_SESSION['rate_limits'] = [];
}

// Clean old entries
if (isset($_SESSION['rate_limits'][$rateLimitKey])) {
    $_SESSION['rate_limits'][$rateLimitKey] = array_filter(
        $_SESSION['rate_limits'][$rateLimitKey],
        function($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        }
    );
}

// Check rate limit
$attempts = $_SESSION['rate_limits'][$rateLimitKey] ?? [];
if (count($attempts) >= $maxAttempts) {
    error_log("Rate limit exceeded for order submission - IP: " . $_SERVER['REMOTE_ADDR'] . " - Session: " . session_id());
    
    $pageTitle = 'تم تجاوز الحد المسموح';
    $pageDescription = 'تم تجاوز عدد المحاولات المسموح بها';
    require_once BASE_PATH . '/templates/partials/header.php';
    ?>
    <div class="container">
        <div class="alert alert-error">
            <strong>⏰ يرجى الانتظار قليلاً</strong><br>
            لقد تجاوزت عدد المحاولات المسموح بها (<?php echo $maxAttempts; ?> طلبات كل 5 دقائق).<br>
            يرجى الانتظار بضع دقائق قبل المحاولة مرة أخرى.
            <div style="margin-top: 1rem;">
                <a href="/catalog.php" class="btn btn-secondary">← العودة للخدمات</a>
            </div>
        </div>
    </div>
    <?php
    require_once BASE_PATH . '/templates/partials/footer.php';
    exit;
}

// Record this attempt
$_SESSION['rate_limits'][$rateLimitKey][] = $currentTime;

        // التحقق من CSRF token
        try {
            Auth::requireCsrf();
        } catch (Exception $e) {
            $errorMessage = "خطأ في التحقق من الأمان. يرجى إعادة تحميل الصفحة والمحاولة مرة أخرى.";
            $pageTitle = 'خطأ في الأمان';
            $pageDescription = 'خطأ في التحقق من الأمان';
            require_once BASE_PATH . '/templates/partials/header.php';
            echo '<div class="container">';
            echo '<div class="alert alert-error">';
            echo '<h3>خطأ في الأمان</h3>';
            echo '<p>' . htmlspecialchars($errorMessage) . '</p>';
            echo '<div style="margin-top: 1rem;">';
            echo '<a href="javascript:history.back()" class="btn btn-secondary">← العودة</a> ';
            echo '<a href="/order.php?service=' . urlencode($_POST['service_id'] ?? '') . '" class="btn btn-primary">إعادة المحاولة</a>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            require_once BASE_PATH . '/templates/partials/footer.php';
            exit;
        }

// Normalize and validate inputs
error_log("Order processing - Starting input validation");
$serviceId = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
$target = isset($_POST['target']) ? mb_substr(trim($_POST['target']), 0, 255, 'UTF-8') : '';

error_log("Order processing - Validated inputs: serviceId=$serviceId, quantity=$quantity, target=$target");
$notes = isset($_POST['notes']) ? mb_substr(trim($_POST['notes']), 0, 1000, 'UTF-8') : '';

// Validation
error_log("Order processing - Starting validation");
if (!$serviceId || $serviceId <= 0) {
    $errorMessage = "معرف الخدمة غير صحيح";
    require_once BASE_PATH . '/templates/partials/header.php';
    echo '<div class="container"><div class="alert alert-error">' . htmlspecialchars($errorMessage) . '</div></div>';
    require_once BASE_PATH . '/templates/partials/footer.php';
    exit;
}

if (!$quantity || $quantity <= 0) {
    $errorMessage = "❌ الكمية غير صحيحة - يرجى إدخال رقم صحيح";
    $pageTitle = 'خطأ في البيانات';
    $pageDescription = 'خطأ في بيانات الطلب المدخلة';
    require_once BASE_PATH . '/templates/partials/header.php';
    echo '<div class="container"><div class="alert alert-error"><strong>خطأ في الكمية:</strong> ' . htmlspecialchars($errorMessage) . '<br><a href="javascript:history.back()" class="btn btn-secondary" style="margin-top: 1rem;">← العودة وإعادة المحاولة</a></div></div>';
    require_once BASE_PATH . '/templates/partials/footer.php';
    exit;
}

if (empty($target)) {
    $errorMessage = "🔗 يرجى إدخال الرابط أو اسم المستخدم";
    $pageTitle = 'خطأ في البيانات';
    $pageDescription = 'بيانات الهدف مفقودة في الطلب';
    require_once BASE_PATH . '/templates/partials/header.php';
    echo '<div class="container"><div class="alert alert-error"><strong>بيانات مفقودة:</strong> ' . htmlspecialchars($errorMessage) . '<br><small style="opacity: 0.8;">مثال: https://instagram.com/username أو @username</small><br><a href="javascript:history.back()" class="btn btn-secondary" style="margin-top: 1rem;">← العودة وإعادة المحاولة</a></div></div>';
    require_once BASE_PATH . '/templates/partials/footer.php';
    exit;
}

error_log("Order processing - Validation passed");

// جلب بيانات الخدمة
try {
    $service = Database::fetchOne(
        "SELECT * FROM services_cache WHERE id = ?",
        [$serviceId]
    );
    
    if (!$service) {
        throw new Exception("الخدمة غير موجودة");
    }
    
    // التحقق من حدود الكمية
    if ($quantity < $service['min'] || $quantity > $service['max']) {
        throw new Exception("الكمية يجب أن تكون بين " . number_format($service['min']) . " و " . number_format($service['max']));
    }
    
    // حساب السعر بالـ LYD
    $rateLyd = isset($service['rate_per_1k_lyd']) && $service['rate_per_1k_lyd'] !== null
        ? (float)$service['rate_per_1k_lyd']
        : ((float)(isset($service['rate_per_1k']) ? $service['rate_per_1k'] : 0) * EXCHANGE_USD_TO_LYD);
    
    $totalPrice = round(($rateLyd / 1000.0) * $quantity, 2);
    
    // التحقق من الرصيد إذا كان المستخدم مسجل دخول
    if ($currentUser) {
        $wallet = Database::fetchOne("SELECT balance FROM wallets WHERE user_id = ?", [$currentUser['id']]);
        $balance = $wallet ? (float)$wallet['balance'] : 0.0;
        
        if ($balance < $totalPrice) {
            $supportMsg = 'مرحباً، أحتاج مساعدة بخصوص شحن المحفظة - رصيدي الحالي: ' . Formatters::formatMoney($balance) . ' والمطلوب: ' . Formatters::formatMoney($totalPrice);
            $supportUrl = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . urlencode($supportMsg);
            $errorMessage = "💰 عذرًا، رصيد محفظتك أقل من قيمة الطلب (رصيدك: " . Formatters::formatMoney($balance) . " | المطلوب: " . Formatters::formatMoney($totalPrice) . ").";
            require_once BASE_PATH . '/templates/partials/header.php';
            ?>
            <div class="container">
                <div class="alert alert-error">
                    <strong>رصيد غير كافي:</strong> <?php echo $errorMessage; ?>
                    <div style="margin-top: 1rem;">
                        <a href="/wallet/topup.php" class="btn btn-primary" style="margin-left: 0.5rem;">💰 شحن المحفظة</a>
                        <a href="<?php echo htmlspecialchars($supportUrl); ?>" target="_blank" rel="noopener" class="btn btn-secondary">📱 مساعدة فورية</a>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body text-center">
                        <a href="/wallet/topup.php" class="btn btn-primary btn-lg">شحن المحفظة</a>
                    </div>
                </div>
            </div>
            <?php
            require_once BASE_PATH . '/templates/partials/footer.php';
            exit;
        }
        
        // بدء معاملة ذرية للخصم
        Database::query("START TRANSACTION");
        
        try {
            // خصم الرصيد
            Database::query(
                "INSERT INTO wallet_transactions (user_id, type, amount, status) VALUES (?, 'deduct', ?, 'approved')",
                [$currentUser['id'], $totalPrice]
            );
            
            Database::query(
                "INSERT INTO wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance - ?",
                [$currentUser['id'], -$totalPrice, $totalPrice]
            );
            
            // التحقق من أن الرصيد لا يزال موجباً
            $newBalance = Database::fetchOne("SELECT balance FROM wallets WHERE user_id = ?", [$currentUser['id']]);
            if ($newBalance['balance'] < 0) {
                throw new Exception("رصيد غير كافي");
            }
            
            // تحديد نوع الهدف (رابط أم اسم مستخدم)
            $isUrl = preg_match('~^https?://~i', $target ?? '');
            $link  = $isUrl ? $target : '';
            $usern = $isUrl ? '' : $target;
            
            // إنشاء الطلب في المزوّد
            $peakerr = new PeakerrClient();
            $orderResult = $peakerr->createOrder(
                intval($service['external_id']),
                intval($quantity),
                $link,
                $usern
            );
            
            // معالجة النتيجة
            if ($orderResult['ok']) {
                // البحث عن مفاتيح الخطأ الشائعة
                if (isset($orderResult['error']) || isset($orderResult['message'])) {
                    $errorMsg = $orderResult['error'] ?? $orderResult['message'];
                    throw new Exception("خطأ من المزوّد: " . $errorMsg);
                }
                
                // إذا كان الرد نصيًا في raw
                if (isset($orderResult['raw'])) {
                    throw new Exception("فشل في إنشاء الطلب - استجابة غير متوقعة من المزوّد");
                }
                
                // نجح الطلب
                if (isset($orderResult['order'])) {
                    // حفظ الطلب في قاعدة البيانات
                    // فحص وجود عمود notes أولاً
                    $notesColumnExists = Database::fetchOne("SHOW COLUMNS FROM orders LIKE 'notes'");
                    if ($notesColumnExists) {
                        Database::query(
                            "INSERT INTO orders (external_order_id, service_id, quantity, link, username, status, price_lyd, user_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $orderResult['order'],
                                $service['id'],
                                $quantity,
                                $link,
                                $usern,
                                $orderResult['status'] ?? 'pending',
                                $totalPrice,
                                $currentUser['id'],
                                'طلب مع رصيد محفظة'
                            ]
                        );
                    } else {
                        Database::query(
                            "INSERT INTO orders (external_order_id, service_id, quantity, link, username, status, price_lyd, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $orderResult['order'],
                                $service['id'],
                                $quantity,
                                $link,
                                $usern,
                                $orderResult['status'] ?? 'pending',
                                $totalPrice,
                                $currentUser['id']
                            ]
                        );
                    }
                    
                    Database::query("COMMIT");
                    
                    $message = "تم إنشاء الطلب بنجاح! رقم الطلب: " . $orderResult['order'];
                    $messageType = 'success';
                    
                    // إعادة توجيه لصفحة التتبع
                    header('Location: /track.php?order=' . urlencode($orderResult['order']));
                    exit;
                } else {
                    throw new Exception("فشل في إنشاء الطلب - لم يتم الحصول على رقم الطلب");
                }
            } else {
                // فشل الطلب
                $errorMsg = $orderResult['error'] ?? "خطأ غير معروف";
                throw new Exception("فشل في إنشاء الطلب: " . $errorMsg);
            }
            
        } catch (Exception $e) {
            Database::query("ROLLBACK");
            throw $e;
        }
        
        } else {
            // المستخدم غير مسجل دخول - إنشاء طلب عادي
            $isUrl = preg_match('~^https?://~i', $target ?? '');
            $link  = $isUrl ? $target : '';
            $usern = $isUrl ? '' : $target;
            
            $peakerr = new PeakerrClient();
            $orderResult = $peakerr->createOrder(
                intval($service['external_id']),
                intval($quantity),
                $link,
                $usern
            );
        
            // معالجة النتيجة للطلب بدون تسجيل دخول
            if ($orderResult['ok']) {
                // التحقق من وجود خطأ في الاستجابة
                if (isset($orderResult['error'])) {
                    $errorMsg = $orderResult['error'];
                    // رسائل خاصة لبعض الأخطاء الشائعة
                    if (stripos($errorMsg, 'not enough funds') !== false || stripos($errorMsg, 'insufficient balance') !== false) {
                        $supportMsg = 'مرحباً، أحتاج مساعدة بخصوص طلب خدمة: ' . ($service['name_ar'] ?? $service['name']) . ' - رصيد المزوّد غير كافي';
                        $supportUrl = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . urlencode($supportMsg);
                        throw new Exception("رصيد المزوّد غير كافي. <a href=\"" . htmlspecialchars($supportUrl) . "\" target=\"_blank\" rel=\"noopener\" class=\"btn btn-secondary\" style=\"margin-right: 0.5rem;\">📱 تواصل مع الدعم</a>");
                    } elseif (stripos($errorMsg, 'invalid service') !== false) {
                        throw new Exception("الخدمة المطلوبة غير متاحة حالياً. يرجى المحاولة لاحقاً.");
                    } elseif (stripos($errorMsg, 'rate limit') !== false) {
                        throw new Exception("تم تجاوز الحد المسموح من الطلبات. يرجى المحاولة بعد قليل.");
                    } else {
                        throw new Exception("تعذر إنشاء الطلب مع المزوّد. الرسالة: " . $errorMsg);
                    }
                }
                if (isset($orderResult['message'])) {
                    throw new Exception("تعذر إنشاء الطلب مع المزوّد. الرسالة: " . $orderResult['message']);
                }
                if (isset($orderResult['raw'])) {
                    throw new Exception("تعذر إنشاء الطلب. راجع الدعم");
                }
                
                if (isset($orderResult['order'])) {
                    // فحص وجود عمود notes أولاً
                    $notesColumnExists = Database::fetchOne("SHOW COLUMNS FROM orders LIKE 'notes'");
                    if ($notesColumnExists) {
                        Database::query(
                            "INSERT INTO orders (external_order_id, service_id, quantity, link, username, status, price_lyd, user_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $orderResult['order'],
                                $service['id'],
                                $quantity,
                                $link,
                                $usern,
                                $orderResult['status'] ?? 'pending',
                                $totalPrice,
                                null, // user_id
                                'طلب بدون تسجيل دخول'
                            ]
                        );
                    } else {
                        Database::query(
                            "INSERT INTO orders (external_order_id, service_id, quantity, link, username, status, price_lyd, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $orderResult['order'],
                                $service['id'],
                                $quantity,
                                $link,
                                $usern,
                                $orderResult['status'] ?? 'pending',
                                $totalPrice,
                                null // user_id
                            ]
                        );
                    }
                    
                    $message = "تم إنشاء الطلب بنجاح! رقم الطلب: " . $orderResult['order'];
                    $messageType = 'success';
                    
                    header('Location: /track.php?order=' . urlencode($orderResult['order']));
                    exit;
                } else {
                    throw new Exception("فشل في إنشاء الطلب - لم يتم الحصول على رقم الطلب");
                }
        } else {
            $errorMsg = $orderResult['error'] ?? "خطأ غير معروف";
            throw new Exception("تعذر إنشاء الطلب مع المزوّد. الرسالة: " . $errorMsg);
        }
        }
    
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Log the full error for debugging
            error_log("Order creation error: " . $errorMessage);
            
            // عرض رسالة خطأ عربية واضحة
            if (strpos($errorMessage, 'المزوّد') !== false) {
                $supportMsg = 'مرحباً، أحتاج مساعدة بخصوص طلب خدمة: ' . ($service['name_ar'] ?? $service['name']) . ' - خطأ في التواصل مع المزود';
                $supportUrl = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . urlencode($supportMsg);
                $message = "عذرًا، حدث خطأ في التواصل مع مزود الخدمة. <a href=\"" . htmlspecialchars($supportUrl) . "\" target=\"_blank\" rel=\"noopener\" class=\"btn btn-secondary\" style=\"margin: 0.5rem 0;\">📱 تواصل مع الدعم الفني</a>";
            } elseif (strpos($errorMessage, 'format') !== false) {
                $message = "يرجى التحقق من صحة الرابط أو اسم المستخدم المدخل.";
            } elseif (strpos($errorMessage, 'quantity') !== false) {
                $message = "يرجى التحقق من الكمية المدخلة (يجب أن تكون ضمن الحدود المسموحة).";
            } else {
                $message = "حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.";
            }
            
            $messageType = 'error';
        }

require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">تأكيد الطلب</h1>
            <p class="card-subtitle">مراجعة تفاصيل طلبك</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>