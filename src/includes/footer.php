<?php if (isset($useAdminLayout) && $useAdminLayout): ?>
            </div><!-- .storm-admin-page-body -->
        </main><!-- .storm-admin-content -->
    </div><!-- .storm-admin-layout -->
<?php endif; ?>
<footer>
    <p>&copy; <?php echo date('Y'); ?> STRATEGIC TACTICAL OPERATIONS & ROLEPLAY MILSIM
        <br><a href="policies.php" style="margin-left: 10px;">Privacy Policy</a>
    </p>
</footer>
<?php $footerBase = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../' : ''; ?>
<script src="<?php echo $footerBase; ?>assets/js/main.js"></script>
</body>

</html>