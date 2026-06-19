    </main>

    <footer class="bg-gray-800 text-white py-6 mt-12">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-400">&copy; <?php echo date('Y'); ?> <?php echo getAppConfig()->getAppName(); ?> - Akıllı Restoran Sistemi. Tüm hakları saklıdır.</p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script type="module" defer>
        // Load error handler module first (before other scripts)
        // Add error handling to prevent blocking if module fails to load
        import('<?php echo BASE_URL; ?>/assets/js/modules/error-handler.js').catch(function(error) {
            console.warn('Error handler module failed to load:', error);
            // Continue execution even if error handler fails
        });
    </script>
    
    <!-- Load core JavaScript files with defer for non-blocking -->
    <script src="<?php echo BASE_URL; ?>/assets/js/utils.js" defer></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/notification.js" defer></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/api.js" defer></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/cart.js" defer></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js" defer></script>
    
    <!-- Mobile Menu Toggle for header.php layout (non-admin pages) -->
    <script defer>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            
            // Only handle if this is NOT admin layout (admin layout uses mobile-nav-overlay)
            if (mobileMenuButton && mobileMenu && !document.getElementById('mobile-nav-overlay')) {
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }
        });
    </script>
</body>
</html>
