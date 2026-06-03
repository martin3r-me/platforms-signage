<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live-Vorschau</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 100%; height: 100%; overflow: hidden; background: #000; }
        #root { position: absolute; inset: 0; }
    </style>
</head>
<body>
    @include('signage::partials.app-runtime')
    <div id="root"></div>

    <script>
        (function () {
            const root = document.getElementById('root');
            let current = null;

            function rebuild(cfg) {
                if (!cfg || !cfg.app_type || !window.SignageApps) return;
                if (current && current.stop) { try { current.stop(); } catch (e) {} }
                root.innerHTML = '';
                current = window.SignageApps.build(cfg.app_type, cfg.config || {}, !!cfg.portrait);
                if (current) root.appendChild(current.node);
            }

            window.addEventListener('message', function (e) {
                if (e.data && e.data.__signagePreview) rebuild(e.data);
            });

            // Eltern-Editor signalisieren, dass die Vorschau bereit ist.
            if (window.parent) window.parent.postMessage({ __signagePreviewReady: true }, '*');
        })();
    </script>
</body>
</html>
