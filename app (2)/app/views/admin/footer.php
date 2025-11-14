</main>

    <footer class="text-center py-3 mt-5 border-top">
        <!-- $db değişkeni dashboard.php'den geliyor -->
        <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> <?php echo Helper::getSetting($db, 'app_name'); ?></p>
    </footer>

    <!-- JAVASCRIPT DOSYALARI -->
    
    <!-- 1. Bootstrap JS (Dropdown menülerin çalışması için ZORUNLUDUR) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- 2. main.js (Tooltip'lerin ve diğer özel fonksiyonların çalışması için ZORUNLUDUR) -->
    <!-- Bu dosyanın içinde tooltip'leri başlatan kodun olması gerekir (ki orijinal dosyanızda var) -->
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
</body>
</html>