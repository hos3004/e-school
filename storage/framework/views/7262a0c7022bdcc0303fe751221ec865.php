
<script>
    (() => {
        let initialized = false;

        const boot = () => {
            if (! window.Alpine) {
                return;
            }

            document.dispatchEvent(new CustomEvent('alpine:init'));

            if (typeof window.Alpine.initTree === 'function') {
                window.Alpine.initTree(document.body);
            }
        };

        const bootLivewire = () => {
            boot();
            initialized = true;
        };

        document.addEventListener('livewire:initialized', bootLivewire);
        document.addEventListener('livewire:navigated', boot);

        // احترازي في حال إطلاق الحدث قبل تسجيل المستمع
        const checkReady = () => {
            if (! initialized && window.Livewire && window.Alpine) {
                bootLivewire();
            }
        };

        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(checkReady, 50);
            setTimeout(checkReady, 200);
        } else {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(checkReady, 50);
                setTimeout(checkReady, 200);
            });
        }
    })();
</script>

<?php /**PATH /var/www/html/resources/views/filament/hooks/alpine-boot.blade.php ENDPATH**/ ?>