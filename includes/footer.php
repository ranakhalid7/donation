    <!-- الفوتر -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-4 col-sm-12 mb-3">
                    <h3><?php echo SITE_NAME; ?></h3>
                    <p>منصة لتسهيل عملية التبرع والاستفادة من الملابس والأثاث بين أفراد المجتمع. نؤمن بقوة العطاء في بناء مجتمع أفضل للجميع.</p>
                    <div style="margin-top: 1.5rem;">
                        <span class="badge badge-success">🌱 صديق للبيئة</span>
                        <span class="badge badge-primary">🤝 مجتمعي</span>
                    </div>
                </div>
                <div class="col-4 col-sm-12 mb-3">
                    <h4>روابط مفيدة</h4>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>/about.php">🏢 من نحن</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/contact.php">📞 اتصل بنا</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/privacy.php">🔒 سياسة الخصوصية</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/terms.php">📋 شروط الاستخدام</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/faq.php">❓ الأسئلة الشائعة</a></li>
                    </ul>
                </div>
                <div class="col-4 col-sm-12 mb-3">
                    <h4>تواصل معنا</h4>
                    <p>
                        <strong>📧 البريد الإلكتروني:</strong><br>
                        info@donation-system.com
                    </p>
                    <p>
                        <strong>📱 الهاتف:</strong><br>
                        +966 50 123 4567
                    </p>
                    <p>
                        <strong>📍 العنوان:</strong><br>
                        المملكة العربية السعودية
                    </p>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. جميع الحقوق محفوظة. | صنع بـ ❤️ لخدمة المجتمع</p>
            </div>
        </div>
    </footer>

    <script src="<?php echo BASE_URL; ?>/scripts.js"></script>

    <?php if (isset($extraJS)): ?>
        <?php foreach ($extraJS as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                DonationSystem.showNotification('<?php echo escape($_SESSION['message']); ?>', '<?php echo escape($_SESSION['message_type'] ?? 'info'); ?>');
            });
        </script>
    <?php
        unset($_SESSION['message'], $_SESSION['message_type']);
    endif;
    ?>

    <?php if (isset($inlineScript)): ?>
        <script>
            <?php echo $inlineScript; ?>
        </script>
    <?php endif; ?>
</body>

</html>
