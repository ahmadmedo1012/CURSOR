    </main>

    <!-- Mobile Sidebar (positioned at body level for full-screen coverage) -->
    <div class="backdrop"></div>
    <div class="mobile-sidebar" id="mobile-sidebar" role="dialog" aria-modal="true" aria-labelledby="sidebar-title" aria-hidden="true">
        <div class="sidebar-header">
            <h2 id="sidebar-title" class="sr-only">القائمة الرئيسية</h2>
            <button type="button" data-sidebar-close aria-label="إغلاق القائمة" id="sidebar-close" class="sidebar-close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>

        <!-- تبويبات القائمة -->
        <div class="sidebar-tabs" role="tablist" aria-label="أقسام القائمة">
            <button class="sidebar-tab" data-tab="main" role="tab" aria-selected="true" aria-controls="panel-main" id="tab-main">الرئيسية</button>
            <button class="sidebar-tab" data-tab="services" role="tab" aria-selected="false" aria-controls="panel-services" id="tab-services">الخدمات</button>
            <button class="sidebar-tab" data-tab="account" role="tab" aria-selected="false" aria-controls="panel-account" id="tab-account">الحساب</button>
        </div>

        <!-- لوحة الرئيسية -->
        <div id="panel-main" class="sidebar-panel" role="tabpanel" aria-labelledby="tab-main">
            <nav role="navigation" aria-label="روابط الصفحات الرئيسية">
                <ul role="list">
                    <li><a href="/" class="nav-link">الرئيسية</a></li>
                    <li><a href="/catalog.php" class="nav-link">جميع الخدمات</a></li>
                    <li><a href="/leaderboard.php" class="nav-link">🏆 لوحة المتصدرين</a></li>
                    <ul role="list">
                        <li><a href="/track.php" class="nav-link">تتبع الطلب</a></li>
                        <li><a href="https://wa.me/218912345678" target="_blank" rel="noopener" class="nav-link whatsapp-link" aria-label="تواصل معنا عبر واتساب (يفتح في نافذة جديدة)">📱 واتساب</a></li>
                        <li>
                            <hr class="sidebar-divider" role="separator">
                        </li>
                        <li>
                            <button class="theme-toggle-mobile" id="theme-toggle-mobile" data-theme-toggle aria-label="تبديل بين الوضع المظلم والفاتح">
                                <span class="theme-icon sun-icon" aria-hidden="true">☀️</span>
                                <span class="theme-icon moon-icon" aria-hidden="true">🌙</span>
                                <span class="theme-text">تبديل الوضع</span>
                            </button>
                        </li>
                    </ul>
                </ul>
            </nav>
        </div>

        <!-- لوحة الخدمات -->
        <div id="panel-services" class="sidebar-panel" role="tabpanel" aria-labelledby="tab-services" hidden>
            <nav role="navigation" aria-label="خدمات المنصات الاجتماعية">
                <ul role="list">
                    <li><a href="/catalog.php?group=tiktok" class="nav-link">تيك توك</a></li>
                    <li><a href="/catalog.php?group=instagram" class="nav-link">إنستغرام</a></li>
                    <li><a href="/catalog.php?group=facebook" class="nav-link">فيسبوك</a></li>
                    <li><a href="/catalog.php?group=youtube" class="nav-link">يوتيوب</a></li>
                    <li><a href="/catalog.php?group=twitter" class="nav-link">تويتر</a></li>
                    <li><a href="/catalog.php?group=telegram" class="nav-link">تيليجرام</a></li>
                    <li>
                        <hr class="sidebar-divider" role="separator">
                    </li>
                    <li><a href="/catalog.php" class="nav-link">جميع الخدمات</a></li>
                </ul>
            </nav>
        </div>

        <!-- لوحة الحساب -->
        <div id="panel-account" class="sidebar-panel" role="tabpanel" aria-labelledby="tab-account" hidden>
            <nav role="navigation" aria-label="إدارة الحساب">
                <ul role="list">
                    <li data-auth="guest"><a href="/auth/login.php" class="nav-link">تسجيل الدخول</a></li>
                    <li data-auth="guest"><a href="/auth/register.php" class="nav-link">إنشاء حساب</a></li>
                    <li data-auth="user"><a href="/account/" class="nav-link">حسابي</a></li>
                    <li data-auth="user"><a href="/wallet/" class="nav-link">المحفظة</a></li>
                    <li data-auth="user"><a href="/account/orders.php" class="nav-link">طلباتي</a></li>
                    <li data-auth="user"><a href="/auth/logout.php" class="nav-link">تسجيل الخروج</a></li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- زر واتساب العائم - تصميم جديد أنيق -->
    <?php
    $whatsappMessage = 'مرحباً، أحتاج مساعدة';

    // إضافة سياق الصفحة للرسالة
    if (isset($service) && is_array($service)) {
        $serviceName = $service['name_ar'] ?? $service['name'];
        $whatsappMessage = 'مرحباً، أحتاج مساعدة بخصوص خدمة: ' . $serviceName;
    } elseif (isset($pageTitle) && $pageTitle !== 'الرئيسية') {
        $whatsappMessage = 'مرحباً، أحتاج مساعدة بخصوص: ' . $pageTitle;
    }

    $whatsappUrl = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . urlencode($whatsappMessage);
    ?>

    <!-- زر الواتساب العائم -->
    <div class="floating-whatsapp" id="floating-whatsapp">
        <button class="wa-fab" aria-label="تواصل معنا عبر واتساب" onclick="openWhatsApp()">
            <div class="wa-icon">
                <svg viewBox="0 0 24 24" fill="currentColor" class="wa-svg">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488" />
                </svg>
            </div>
            <div class="wa-pulse"></div>
        </button>

        <!-- Tooltip -->
        <div class="wa-tooltip">
            <span>تواصل معنا</span>
            <div class="wa-tooltip-arrow"></div>
        </div>
    </div>

    <!-- زر العودة لأعلى -->
    <button class="back-to-top" id="back-to-top" aria-label="العودة لأعلى الصفحة">
        <svg viewBox="0 0 24 24" fill="currentColor" class="back-to-top-icon">
            <path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z" />
        </svg>
    </button>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3><?php echo APP_NAME; ?></h3>
                    <p>مركز متخصص في خدمات الهاتف المحمول والألعاب</p>
                </div>
                <div class="footer-links">
                    <div class="footer-section">
                        <h4>روابط سريعة</h4>
                        <ul>
                            <li><a href="/">الرئيسية</a></li>
                            <li><a href="/catalog.php">الخدمات</a></li>
                            <li><a href="/track.php">تتبع الطلب</a></li>
                        </ul>
                    </div>
                    <div class="footer-section">
                        <h4>تواصل معنا</h4>
                        <ul>
                            <li><a href="https://wa.me/218912345678" target="_blank">واتساب</a></li>
                            <li><a href="mailto:info@gamebox.ly">البريد الإلكتروني</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. جميع الحقوق محفوظة.</p>
            </div>
        </div>
    </footer>

    <!-- Non-critical JS: performance monitoring -->
    <script src="<?php echo asset_url('assets/js/perf.js'); ?>" defer></script>

    <!-- JavaScript للأزرار العائمة - Moved to end of body -->
    <script defer>
        // WhatsApp function
        function openWhatsApp() {
            const whatsappUrl = '<?php echo htmlspecialchars($whatsappUrl); ?>';
            window.open(whatsappUrl, '_blank', 'noopener');
        }

        // Back to top functionality
        document.addEventListener('DOMContentLoaded', function() {
            const backToTopBtn = document.getElementById('back-to-top');
            const whatsappBtn = document.getElementById('floating-whatsapp');

            // Show/hide back to top button based on scroll position
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTopBtn.classList.add('show');
                } else {
                    backToTopBtn.classList.remove('show');
                }
            });

            // Smooth scroll to top
            backToTopBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            // WhatsApp button hover effects
            const waFab = document.querySelector('.wa-fab');
            const waTooltip = document.querySelector('.wa-tooltip');

            if (waFab && waTooltip) {
                waFab.addEventListener('mouseenter', function() {
                    waTooltip.classList.add('show');
                });

                waFab.addEventListener('mouseleave', function() {
                    waTooltip.classList.remove('show');
                });
            }
        });
    </script>

    <!-- Mobile Theme Toggle - Final Fix -->
    <script defer>
        // Ensure mobile theme toggle works
        document.addEventListener('DOMContentLoaded', function() {
            var mobileThemeBtn = document.getElementById('theme-toggle-mobile');
            if (mobileThemeBtn) {
                mobileThemeBtn.onclick = function(e) {
                    e.preventDefault();
                    if (window.toggleTheme) {
                        window.toggleTheme();
                    } else {
                        // Fallback
                        var current = document.documentElement.getAttribute('data-theme') || 'dark';
                        var next = (current === 'dark' ? 'light' : 'dark');
                        document.documentElement.setAttribute('data-theme', next);
                        try {
                            localStorage.setItem('theme', next);
                        } catch (e) {}
                        console.log('Mobile theme toggled to:', next);
                    }
                };
            }
        });
    </script>

    </body>

    </html>