    <!-- Profile Settings Modal -->
    <?php 
        // We check if the modal file exists before including it
        if (file_exists('../includes/profile_modal.php')) {
            include '../includes/profile_modal.php';
        }
    ?>

    <!-- Toast Container for Login Alert -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
      <div id="loginToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
          <strong class="me-auto">Demo Login Successful</strong>
          <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
          <?php 
            if (isset($_SESSION['login_success_message'])) {
                echo htmlspecialchars($_SESSION['login_success_message']);
            }
          ?>
        </div>
      </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Login Toast Logic
            <?php
            if (isset($_SESSION['login_success_message'])) {
                echo "var loginToast = new bootstrap.Toast(document.getElementById('loginToast'));";
                echo "loginToast.show();";
                unset($_SESSION['login_success_message']);
            }
            ?>

            // Dark Mode Toggle Logic
            const themeToggleBtn = document.getElementById('theme-toggle-btn');
            const themeToggleIcon = themeToggleBtn.querySelector('i');

            // Function to set the theme
            const setTheme = (theme) => {
                if (theme === 'dark') {
                    document.body.classList.add('dark-mode');
                    themeToggleIcon.classList.remove('fa-moon');
                    themeToggleIcon.classList.add('fa-sun');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark-mode');
                    themeToggleIcon.classList.remove('fa-sun');
                    themeToggleIcon.classList.add('fa-moon');
                    localStorage.setItem('theme', 'light');
                }
            };

            // Check for saved theme in localStorage
            const currentTheme = localStorage.getItem('theme') ? localStorage.getItem('theme') : null;
            if (currentTheme) {
                setTheme(currentTheme);
            }

            // Event listener for the toggle button
            themeToggleBtn.addEventListener('click', () => {
                let theme = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
                setTheme(theme);
            });
        });
    </script>
</body>
</html>
