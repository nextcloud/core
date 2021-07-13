self.addEventListener('install', function(e) {});

self.addEventListener('fetch',
    function(e) {
        e.respondWith(fetch(e.request).then(function(response){ return response; }));
    }
);
