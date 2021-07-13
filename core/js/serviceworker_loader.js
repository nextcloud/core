if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register(OC.getRootPath() + '/core/js/service-worker.js', {scope: OC.getRootPath() + '/'});
    });
}
