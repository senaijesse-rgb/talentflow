<?php if (usuarioAtual()): ?>
            <footer class="mt-10 border-t border-slate-200 pt-6 text-xs text-slate-500">
                <p>&copy; <?= date('Y') ?> PDI Connect · Uso interno e confidencial. Dados tratados conforme a LGPD e as políticas internas de privacidade.</p>
            </footer>
        </main>
    </div>
</div>
<?php else: ?>
</main>
<?php endif; ?>
<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
