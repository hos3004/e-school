{{--
    في هذه التركيبة يبدأ Livewire محرّك Alpine قبل أن تُنفَّذ سكربتات Filament،
    فتفوت مستمعاتها حدث alpine:init ولا تُسجَّل مكوّناتها
    (filamentSchema · filamentSchemaComponent · filamentActionModals …)،
    فتظهر الويدجتات والنماذج والجداول فارغة.

    نعيد إطلاق الحدث بعد جاهزية Livewire ثم نعيد تهيئة الشجرة.
    Alpine.data يستبدل التسجيل بالاسم، فلا ازدواج.
--}}
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
