<?php
$psJsFile = __DIR__ . '/../../../public/assets/js/public-support.js';
$psJsVer = file_exists($psJsFile) ? (string)filemtime($psJsFile) : '1.0';
?>
    <footer class="ps-footer">
        <div>NEXORA HR &bull; Security-First Account Recovery &amp; Pre-Login Support &bull; Never share passwords or OTP codes.</div>
    </footer>

    <script src="<?= asset('js/public-support.js') ?>?v=<?= e($psJsVer) ?>" defer></script>
</body>

</html>
