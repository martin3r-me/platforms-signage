<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>App-Vorschau</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 100%; height: 100%; overflow: hidden; background: #000; }
    </style>
</head>
<body>
    @include('signage::partials.app-runtime')

    <script>
        (function () {
            const cfg = @json($config);
            const built = window.SignageApps.build(@json($appType), cfg, false);
            if (built) document.body.appendChild(built.node);
        })();
    </script>
</body>
</html>
