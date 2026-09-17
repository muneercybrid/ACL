@push('scripts')
<script>
(function(){
    let idleTime = 0;
    const maxIdle = 30 * 60 * 1000; // 30 minutes in ms
    let timer = setInterval(function(){
        idleTime += 1000;
        if (idleTime >= maxIdle) {
            clearInterval(timer);
            fetch('{{ route("logout") }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}}).then(function(){
                window.location.href = '{{ route("login") }}?timeout=1';
            });
        }
    }, 1000);
    ['mousemove','keydown','click','touchstart','scroll'].forEach(function(evt){
        window.addEventListener(evt, function(){ idleTime = 0; });
    });
})();
</script>
@endpush