  </div><!-- .content-area -->
</div><!-- .main-wrapper -->

<!-- Toast notification container -->
<div id="toastContainer" class="toast-container"></div>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal" style="display:none">
  <div class="modal-box">
    <div class="modal-icon"><i class="fas fa-exclamation-triangle" style="color:var(--gold)"></i></div>
    <h3 class="modal-title" id="confirmTitle">Confirm Action</h3>
    <p class="modal-msg" id="confirmMessage">Are you sure?</p>
    <div class="modal-actions">
      <button class="btn btn-outline" id="confirmCancel">Cancel</button>
      <button class="btn btn-gold" id="confirmOk">Confirm</button>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
</body>
</html>
