{{-- No-flash theme boot: apply the saved (or system) theme to <html> before first paint.
     Must stay inline in <head> — Alpine loads too late to prevent a flash. --}}
<script>
    (function () {
        document.documentElement.classList.add('js'); // enables progressive-enhancement styles
        try {
            var stored = localStorage.getItem('acl-theme'); // 'light' | 'dark' | null (= follow system)
            var systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', stored ? stored === 'dark' : systemDark);
        } catch (e) {}
    })();
</script>
