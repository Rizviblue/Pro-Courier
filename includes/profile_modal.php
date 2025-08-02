<!-- Profile Settings Modal -->
<div class="modal fade" id="profileSettingsModal" tabindex="-1" aria-labelledby="profileSettingsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body text-center p-4">
        <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="profile-modal-icon">
            <i class="fas fa-user"></i>
        </div>
        <h4 class="mt-3"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h4>
        <p class="text-muted"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'admin@courierpro.com'); ?></p>
        <span class="badge bg-primary mb-4"><?php echo ucfirst(htmlspecialchars($_SESSION['user_role'])); ?></span>
        <div class="d-grid gap-2">
            <a href="settings.php" class="btn btn-light border">Edit Profile</a>
            <a href="../logout.php" class="btn btn-light border">Logout</a>
        </div>
      </div>
    </div>
  </div>
</div>
