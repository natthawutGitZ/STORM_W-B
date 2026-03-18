<footer>
    <p>&copy; <?php echo date('Y'); ?> STRATEGIC TACTICAL OPERATIONS & ROLEPLAY MILSIM
        <br><a href="policies.php" style="margin-left: 10px;">Privacy Policy</a>
    </p>
</footer>
<?php $footerBase = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../' : ''; ?>
<script src="<?php echo $footerBase; ?>assets/js/main.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="<?php echo $footerBase; ?>assets/js/page-transition.js"></script>
</body>

</html>