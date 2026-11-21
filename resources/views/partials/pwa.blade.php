<link rel="manifest" href="{{ asset('manifest.json') }}">
<meta name="theme-color" content="#2563eb">
<link rel="icon" href="{{ asset('icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icon-192.png') }}">
<script>
    if ('serviceWorker' in navigator) addEventListener('load', () => navigator.serviceWorker.register(
        '{{ asset('sw.js') }}'));
</script>
