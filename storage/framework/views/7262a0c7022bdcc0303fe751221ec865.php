
<script>
    (() => {
        let tries = 0;

        const boot = () => {
            if (++tries > 40) {
                return;
            }

            if (! window.Alpine || ! window.Livewire) {
                setTimeout(boot, 50);

                return;
            }

            document.dispatchEvent(new CustomEvent('alpine:init'));
            window.Alpine.initTree(document.body);
        };

        document.addEventListener('livewire:initialized', boot);
        setTimeout(boot, 100);
    })();
</script>
<?php /**PATH /var/www/html/resources/views/filament/hooks/alpine-boot.blade.php ENDPATH**/ ?>