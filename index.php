<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/src/Utils/auth.php';

// تشغيل Migrations تلقائياً
require_once BASE_PATH . '/database/run_migrations.php';
Migrations::run();

Auth::startSession();
$currentUser = Auth::currentUser() ?? null;

$pageTitle = 'الرئيسية';
$pageDescription = 'منصة شاملة لخدمات وسائل التواصل الاجتماعي والألعاب - متابعين، إعجابات، مشاهدات، عملات الألعاب وأكثر';
$ogType = 'website';
require_once BASE_PATH . '/templates/partials/header.php';
?>

<div class="container">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="breadcrumb-nav" style="margin-bottom: 1rem;">
        <ol class="breadcrumb">
            <li class="breadcrumb-item active" aria-current="page">الرئيسية</li>
        </ol>
    </nav>

    <!-- قسم الهيرو -->
    <section class="hero">
        <h1>مرحباً بك في <?php echo APP_NAME; ?></h1>
        <p>مركزك المتخصص لجميع خدمات الهاتف المحمول والألعاب. نحن نقدم أفضل الخدمات بأسعار تنافسية وجودة عالية.</p>

        <?php if ($currentUser): ?>
            <!-- المستخدم مسجل دخول -->
            <div class="hero-actions">
                <a href="/catalog.php" class="btn btn-lg btn-primary">استعرض الخدمات</a>
                <a href="/account/" class="btn btn-primary">حسابي</a>
            </div>
            <p class="welcome-message">مرحباً <?php echo htmlspecialchars($currentUser['name']); ?>! 👋</p>
        <?php else: ?>
            <!-- المستخدم غير مسجل دخول -->
            <div class="hero-actions">
                <a href="/catalog.php" class="btn btn-lg btn-primary">استعرض الخدمات</a>
            </div>
            <div class="auth-required-message">
                <div class="auth-card">
                    <h3>🔒 مطلوب تسجيل الدخول للطلب</h3>
                    <p>يجب عليك تسجيل الدخول أو إنشاء حساب جديد لتتمكن من طلب الخدمات</p>
                    <div class="auth-buttons">
                        <a href="/auth/login.php" class="btn btn-primary">تسجيل الدخول</a>
                        <a href="/auth/register.php" class="btn btn-accent">إنشاء حساب جديد</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <!-- أقسام الخدمات -->
    <section class="services-sections">
        <div class="grid grid-3">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">خدمات التواصل الاجتماعي</h3>
                    <p class="card-subtitle">متابعين، إعجابات، مشاهدات</p>
                </div>
                <div class="card-body">
                    <p>نقدم خدمات متنوعة لجميع منصات التواصل الاجتماعي مثل فيسبوك، إنستغرام، تويتر، تيك توك وغيرها.</p>
                    <ul class="feature-list">
                        <li>متابعين حقيقيين</li>
                        <li>إعجابات وتفاعلات</li>
                        <li>مشاهدات الفيديوهات</li>
                        <li>مشاركات وتعليقات</li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">خدمات الألعاب</h3>
                    <p class="card-subtitle">عملات، نقاط، مكافآت</p>
                </div>
                <div class="card-body">
                    <p>خدمات شاملة لجميع الألعاب الشهيرة على الهاتف المحمول والحاسوب.</p>
                    <ul class="feature-list">
                        <li>عملات الألعاب</li>
                        <li>نقاط الخبرة</li>
                        <li>مكافآت وحزم</li>
                        <li>رفع المستوى</li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">خدمات المنصات</h3>
                    <p class="card-subtitle">مشاهدات، تفاعل، شهرة</p>
                </div>
                <div class="card-body">
                    <p>خدمات متخصصة لجميع المنصات الرقمية مثل يوتيوب، سبوتيفاي، أبل ميوزك وغيرها.</p>
                    <ul class="feature-list">
                        <li>مشاهدات يوتيوب</li>
                        <li>مستمعي سبوتيفاي</li>
                        <li>تحميلات التطبيقات</li>
                        <li>تقييمات إيجابية</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- ميزات الخدمة -->
    <section class="features">
        <div class="grid grid-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">لماذا تختارنا؟</h3>
                </div>
                <div class="card-body">
                    <div class="feature-item">
                        <span class="feature-icon">⚡</span>
                        <div>
                            <h4>سرعة في التنفيذ</h4>
                            <p>نبدأ في تنفيذ طلبك فور تأكيد الدفع</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <span class="feature-icon">🛡️</span>
                        <div>
                            <h4>ضمان الجودة</h4>
                            <p>نقدم خدمات عالية الجودة مع ضمان الاسترداد</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <span class="feature-icon">💰</span>
                        <div>
                            <h4>أسعار تنافسية</h4>
                            <p>أفضل الأسعار في السوق مع جودة عالية</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">كيفية الطلب</h3>
                </div>
                <div class="card-body">
                    <div class="feature-item">
                        <span class="step-number">1</span>
                        <div>
                            <h4>اختر الخدمة</h4>
                            <p>تصفح قائمة الخدمات المتاحة</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <span class="step-number">2</span>
                        <div>
                            <h4>أدخل التفاصيل</h4>
                            <p>أدخل الرابط أو اسم المستخدم والكمية</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <span class="step-number">3</span>
                        <div>
                            <h4>تأكيد الطلب</h4>
                            <p>راجع التفاصيل وأكد طلبك</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- لوحة المتصدرين -->
    <section class="leaderboard-preview">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🏆 المتصدرون هذا الشهر</h3>
                <p class="card-subtitle">أكبر المستخدمين إنفاقاً</p>
            </div>
            <div class="card-body">
                <div id="leaderboard-preview" class="leaderboard-preview-content">
                    <div class="loading-skeleton">
                        <div class="skeleton-item"></div>
                        <div class="skeleton-item"></div>
                        <div class="skeleton-item"></div>
                    </div>
                </div>
                <div class="text-center" style="margin-top: 1rem;">
                    <a href="/leaderboard.php" class="btn btn-primary">عرض جميع المتصدرين</a>
                </div>
            </div>
        </div>
    </section>

    <!-- دعوة للعمل -->
    <section class="cta-section text-center">
        <div class="card">
            <h2 class="cta-title">ابدأ الآن</h2>
            <p class="cta-subtitle">احصل على أفضل الخدمات بأسعار لا تقبل المنافسة</p>

            <div class="cta-actions">
                <?php if ($currentUser): ?>
                    <a href="/catalog.php" class="btn btn-lg btn-primary">تصفح الخدمات</a>
                    <a href="/account/" class="btn btn-primary">حسابي</a>
                <?php else: ?>
                    <a href="/catalog.php" class="btn btn-lg btn-primary">تصفح الخدمات</a>
                    <a href="/auth/register.php" class="btn btn-primary">إنشاء حساب</a>
                <?php endif; ?>

                <a href="https://wa.me/218912345678" target="_blank" class="btn btn-success">تواصل معنا</a>
            </div>
        </div>
    </section>
</div>

<script>
    // تحميل لوحة المتصدرين في الصفحة الرئيسية
    document.addEventListener('DOMContentLoaded', function() {
        const leaderboardContainer = document.getElementById('leaderboard-preview');

        if (leaderboardContainer) {
            // تحميل البيانات من API
            fetch('/api/leaderboard.php?action=current_month&limit=3')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        renderLeaderboardPreview(data.data);
                    } else {
                        showEmptyState();
                    }
                })
                .catch(error => {
                    console.error('خطأ في تحميل لوحة المتصدرين:', error);
                    showEmptyState();
                });
        }

        function renderLeaderboardPreview(leaderboard) {
            const html = leaderboard.map((user, index) => `
            <div class="leaderboard-preview-item rank-${index + 1}">
                <div class="rank-badge">
                    ${index + 1 === 1 ? '🥇' : index + 1 === 2 ? '🥈' : index + 1 === 3 ? '🥉' : '#' + (index + 1)}
                </div>
                <div class="user-info">
                    <div class="user-name">${user.user_name || 'مستخدم غير معروف'}</div>
                    <div class="user-phone">${user.user_phone}</div>
                </div>
                <div class="spent-amount">
                    <div class="amount">${parseFloat(user.spent).toFixed(2)} LYD</div>
                    <div class="transactions">${user.transaction_count} معاملة</div>
                </div>
            </div>
        `).join('');

            leaderboardContainer.innerHTML = html;
        }

        function showEmptyState() {
            leaderboardContainer.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <p>لا توجد بيانات متاحة حالياً</p>
            </div>
        `;
        }
    });
</script>

<?php require_once BASE_PATH . '/templates/partials/footer.php'; ?>