<script>
(function() {
    var THROTTLE_KEY = 'tipowerup_last_update_check';
    var THROTTLE_MS = 6 * 60 * 60 * 1000;

    try {
        var last = parseInt(localStorage.getItem(THROTTLE_KEY) || '0', 10);
        if (Date.now() - last < THROTTLE_MS) return;
    } catch (e) {}

    try {
        localStorage.setItem(THROTTLE_KEY, String(Date.now()));
    } catch (e) {}

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) return;

    var controller = new AbortController();
    var timeoutId = setTimeout(function() { controller.abort(); }, 3000);

    fetch('{{ admin_url("tipowerup/installer/check-updates-bg") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        signal: controller.signal,
        credentials: 'same-origin'
    }).then(function() {
        clearTimeout(timeoutId);
    }).catch(function() {
        clearTimeout(timeoutId);
    });
})();
</script>
