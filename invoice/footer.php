<footer class="main-footer">
    <p><?= e('footer.credit', ['year' => date('Y')]) ?> <a href="https://www.silverwebbuzz.com" target="_blank">silverwebbuzz</a></p>
</footer>
<script>
    const BASE_URL = "<?= BASE_URL ?>";
    // Translations for js/script.js. Only the keys that script.js actually
    // uses cross over, so page weight doesn't grow with the catalog.
    window.SAPI_I18N = <?= json_encode(i18n_js_payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Popper.js (required for Bootstrap 4 tooltips) -->
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<!-- Bootstrap 4 JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
<script src="../js/script.js?v=<?= @filemtime(__DIR__ . '/../js/script.js') ?: time() ?>"></script>
</body>
</html>