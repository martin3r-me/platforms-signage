<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Digital Signage Player</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            width: 100%; height: 100%; overflow: hidden;
            background: #000; color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            cursor: none;
        }
        #stage { position: fixed; inset: 0; background: #000; }
        .frame {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.6s ease-in-out;
            background: #000;
        }
        .frame.visible { opacity: 1; }
        .frame img, .frame video {
            max-width: 100%; max-height: 100%;
            width: 100%; height: 100%;
            object-fit: contain;
        }

        /* Overlays */
        .overlay {
            position: fixed; inset: 0; z-index: 10;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            background: radial-gradient(circle at 50% 35%, #1f2937, #000);
            text-align: center; padding: 5vh 4vw;
        }
        .overlay.hidden { display: none; }
        .brand { font-size: 2.2vw; letter-spacing: .3em; text-transform: uppercase; color: #93c5fd; margin-bottom: 4vh; }
        .pair-label { font-size: 2.4vw; color: #cbd5e1; margin-bottom: 2vh; }
        .pair-code {
            font-size: 9vw; font-weight: 800; letter-spacing: .15em;
            color: #fff; background: rgba(255,255,255,.06);
            border: 2px solid rgba(255,255,255,.15); border-radius: 1.5vw;
            padding: 2vh 4vw;
        }
        .pair-hint { margin-top: 4vh; font-size: 1.6vw; color: #94a3b8; max-width: 60vw; }
        .spinner {
            width: 4vw; height: 4vw; border: .5vw solid rgba(255,255,255,.2);
            border-top-color: #fff; border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Uhr-/Wetter-App-Styles kommen aus dem app-runtime Partial. */

        #tapStart {
            position: fixed; inset: 0; z-index: 20;
            display: flex; align-items: center; justify-content: center;
            background: rgba(0,0,0,.85); cursor: pointer; font-size: 3vw; color: #fff;
        }
        #tapStart.hidden { display: none; }
    </style>
</head>
<body>
    <div id="stage"></div>

    {{-- Pairing / Status-Overlay --}}
    <div id="overlay" class="overlay">
        <div class="brand">Digital Signage</div>
        <div id="overlayBody">
            <div class="spinner"></div>
        </div>
    </div>

    {{-- Tap-to-start (für Audio-Autoplay-Policy) --}}
    <div id="tapStart" class="hidden">Zum Starten tippen</div>

    <audio id="music" loop></audio>

    {{-- Gemeinsame App-Runtime (definiert window.SignageApps) --}}
    @include('signage::partials.app-runtime')

    <script>
    (function () {
        const CONFIG = {
            registerUrl:         @json($registerUrl),
            stateUrlTemplate:    @json($stateUrlTemplate),
            manifestUrlTemplate: @json($manifestUrlTemplate),
            pollInterval: {{ $pollInterval }} * 1000,
        };
        const stateUrl    = (token) => CONFIG.stateUrlTemplate.replace('__TOKEN__', token);
        const manifestUrl = (token) => CONFIG.manifestUrlTemplate.replace('__TOKEN__', token);
        const STORAGE_KEY = 'signage_device_token';

        const stage    = document.getElementById('stage');
        const overlay  = document.getElementById('overlay');
        const overlayBody = document.getElementById('overlayBody');
        const tapStart = document.getElementById('tapStart');
        const musicEl  = document.getElementById('music');

        // Vorschau-Modus: ?token=… nutzt ein vorgegebenes Gerät (kein localStorage, keine Registrierung).
        const urlToken = new URLSearchParams(window.location.search).get('token');
        const previewMode = !!urlToken;
        // Eingebettet (Admin-Live-Vorschau im iframe)? Dann keine Musik – beim
        // "Vollbild öffnen" (eigener Tab) ist self === top, da läuft Musik mit.
        const inIframe = window.self !== window.top;
        let deviceToken = urlToken || localStorage.getItem(STORAGE_KEY);
        let currentVersion = null;
        let playlist = [];
        let musicTracks = [];
        let frameTimer = null;
        let playIndex = 0;
        let userInteracted = false;

        // ---- Helpers -------------------------------------------------------
        function showOverlay(html) {
            overlayBody.innerHTML = html;
            overlay.classList.remove('hidden');
        }
        function hideOverlay() { overlay.classList.add('hidden'); }

        async function postJson(url) {
            const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('register failed');
            return res.json();
        }
        async function getJson(url) {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            if (!res.ok) throw new Error('request failed ' + res.status);
            return res.json();
        }

        // ---- Registration & polling ---------------------------------------
        async function ensureRegistered() {
            if (deviceToken) return;
            if (previewMode) return; // Im Vorschau-Modus nie selbst registrieren.
            const data = await postJson(CONFIG.registerUrl);
            deviceToken = data.device_token;
            localStorage.setItem(STORAGE_KEY, deviceToken);
        }

        async function poll() {
            try {
                await ensureRegistered();
                const state = await getJson(stateUrl(deviceToken));

                if (state.status === 'removed') {
                    handleRemoved();
                } else if (state.status === 'pending') {
                    stopPlayback();
                    showPairing(state.pairing_code);
                } else if (state.status === 'active') {
                    if (state.content_version !== currentVersion) {
                        currentVersion = state.content_version;
                        await loadManifest();
                    }
                }
            } catch (e) {
                // Netzwerkfehler -> Overlay nur zeigen, wenn noch nichts läuft.
                if (!playlist.length) {
                    showOverlay('<div class="pair-label">Verbindung wird hergestellt …</div><div class="spinner" style="margin:0 auto"></div>');
                }
                console.warn('[signage] poll error', e);
            } finally {
                setTimeout(poll, CONFIG.pollInterval);
            }
        }

        function showPairing(code) {
            showOverlay(
                '<div class="pair-label">Kopplungs-Code</div>' +
                '<div class="pair-code">' + (code || '—') + '</div>' +
                '<div class="pair-hint">Gib diesen Code im Digital-Signage-Modul unter „Bildschirme“ ein, um diesen Bildschirm zu verbinden.</div>'
            );
        }

        // ---- Manifest & playback ------------------------------------------
        async function loadManifest() {
            const manifest = await getJson(manifestUrl(deviceToken));
            if (manifest.status === 'removed') {
                handleRemoved();
                return;
            }
            if (manifest.status !== 'active') {
                showPairing(manifest.pairing_code);
                return;
            }

            applyOrientation(manifest.screen && manifest.screen.orientation ? manifest.screen.orientation : 'landscape');

            playlist = manifest.items || [];
            musicTracks = manifest.music || [];

            if (!playlist.length) {
                stopPlayback();
                showOverlay('<div class="pair-label">' + (manifest.name || 'Bildschirm') + '</div><div class="pair-hint">Diesem Bildschirm ist noch keine Wiedergabeliste zugewiesen.</div>');
                return;
            }

            hideOverlay();
            startMusic();
            playIndex = 0;
            startPlayback();
        }

        // Dreht die Bühne entsprechend der Bildschirm-Ausrichtung.
        // landscape (0°), landscape_180 (180°), portrait (90°), portrait_180 (270°).
        let currentOrientation = 'landscape';
        function applyOrientation(orientation) {
            currentOrientation = orientation || 'landscape';
            const s = stage;
            if (orientation === 'portrait' || orientation === 'portrait_180') {
                // 90°/270°: Breite/Höhe tauschen und um den Mittelpunkt drehen.
                const deg = orientation === 'portrait_180' ? 270 : 90;
                s.style.inset = 'auto';
                s.style.top = '50%';
                s.style.left = '50%';
                s.style.width = '100vh';
                s.style.height = '100vw';
                s.style.transformOrigin = 'center center';
                s.style.transform = 'translate(-50%, -50%) rotate(' + deg + 'deg)';
            } else {
                s.style.inset = '0';
                s.style.top = '';
                s.style.left = '';
                s.style.width = '';
                s.style.height = '';
                s.style.transformOrigin = 'center center';
                s.style.transform = (orientation === 'landscape_180') ? 'rotate(180deg)' : 'none';
            }
        }

        function stopPlayback() {
            if (frameTimer) { clearTimeout(frameTimer); frameTimer = null; }
        }

        function clearStage() {
            stage.querySelectorAll('.frame').forEach((f) => { runCleanup(f); f.remove(); });
        }

        // Bildschirm wurde im Backend gelöscht -> Anzeige stoppen und Gerät zurücksetzen.
        function handleRemoved() {
            stopPlayback();
            clearStage();
            playlist = [];
            musicTracks = [];
            currentVersion = null;
            startMusic(); // stoppt Musik (leere Liste)

            if (previewMode) {
                showOverlay('<div class="pair-label">Dieser Bildschirm wurde entfernt.</div>');
                return;
            }

            // Gerät vergessen -> nächster Poll registriert neu und zeigt einen neuen Kopplungs-Code.
            localStorage.removeItem(STORAGE_KEY);
            deviceToken = null;
            showOverlay('<div class="pair-label">Bildschirm wurde entfernt</div><div class="pair-hint">Neuer Kopplungs-Code wird erstellt …</div>');
        }

        function startPlayback() {
            stopPlayback();
            if (!playlist.length) return;
            playIndex = playIndex % playlist.length;
            renderFrame(playlist[playIndex]);
        }

        function advance() {
            playIndex = (playIndex + 1) % playlist.length;
            renderFrame(playlist[playIndex]);
        }

        function renderFrame(item) {
            const frame = document.createElement('div');
            frame.className = 'frame';

            if (item.type === 'video') {
                const v = document.createElement('video');
                v.src = item.url;
                v.muted = true;            // stummes Autoplay ist erlaubt
                v.autoplay = true;
                v.playsInline = true;
                v.onended = () => { swap(frame); advance(); };
                v.onerror = () => { swap(frame); advance(); };
                frame.appendChild(v);
                mount(frame);
                v.play().catch(() => {});
            } else if (item.type === 'app') {
                renderApp(item, frame);
            } else {
                const img = document.createElement('img');
                img.src = item.url;
                img.onerror = () => { swap(frame); advance(); };
                frame.appendChild(img);
                mount(frame);
                const ms = (item.duration || 10) * 1000;
                frameTimer = setTimeout(() => { swap(frame); advance(); }, ms);
            }
        }

        // App-Frames (Uhr, …) werden clientseitig gerendert und für die Dauer angezeigt.
        function renderApp(item, frame) {
            const portrait = currentOrientation.indexOf('portrait') === 0;
            const built = window.SignageApps.build(item.app_type, item.config || {}, portrait);
            if (built) {
                frame.appendChild(built.node);
                frame._cleanup = built.stop;
            }
            mount(frame);
            const ms = (item.duration || 10) * 1000;
            frameTimer = setTimeout(() => { swap(frame); advance(); }, ms);
        }

        function runCleanup(f) {
            if (f && f._cleanup) { try { f._cleanup(); } catch (e) {} f._cleanup = null; }
        }

        function mount(frame) {
            stage.appendChild(frame);
            // Reflow erzwingen, dann einblenden (Fade).
            void frame.offsetWidth;
            frame.classList.add('visible');
            // Alte Frames entfernen (nur den jüngsten Vorgänger ausblenden lassen).
            const frames = stage.querySelectorAll('.frame');
            if (frames.length > 2) {
                runCleanup(frames[0]);
                stage.removeChild(frames[0]);
            }
        }

        function swap(frame) {
            frame.classList.remove('visible');
            setTimeout(() => {
                runCleanup(frame);
                if (frame.parentNode) frame.parentNode.removeChild(frame);
            }, 700);
        }

        // ---- Background music ---------------------------------------------
        let musicIndex = 0;
        let musicEmbed = null;

        function clearMusicEmbed() {
            if (musicEmbed && musicEmbed.parentNode) musicEmbed.parentNode.removeChild(musicEmbed);
            musicEmbed = null;
        }

        function startMusic() {
            clearMusicEmbed();

            // In der eingebetteten Admin-Vorschau (iframe) keine Musik abspielen.
            if (inIframe) { musicEl.pause(); musicEl.removeAttribute('src'); return; }

            if (!musicTracks.length) { musicEl.pause(); musicEl.removeAttribute('src'); return; }

            // Embed-Player (iframe, z.B. TuneIn): kontinuierlich, kein Cycling.
            const embed = musicTracks.find(t => t.type === 'embed');
            if (embed) {
                musicEl.pause(); musicEl.removeAttribute('src');
                renderMusicEmbed(embed.url);
                return;
            }

            // Direkte Streams + Dateien über <audio>.
            musicEl.loop = false;
            musicEl.onended = () => {
                musicIndex = (musicIndex + 1) % musicTracks.length;
                playTrack();
            };
            musicIndex = 0;
            playTrack();
        }

        function playTrack() {
            if (!musicTracks.length) return;
            const track = musicTracks[musicIndex];
            // Einzelner Dauer-Stream soll bei Verbindungsabbruch nicht stumm bleiben:
            musicEl.loop = (musicTracks.length === 1 && track.type === 'stream');
            musicEl.src = track.url;
            const p = musicEl.play();
            if (p && p.catch) {
                p.catch(() => { if (!userInteracted) tapStart.classList.remove('hidden'); });
            }
        }

        function renderMusicEmbed(url) {
            const f = document.createElement('iframe');
            f.id = 'music-embed';
            f.src = url;
            f.allow = 'autoplay; encrypted-media';
            // Unsichtbar im Hintergrund – es geht nur um den Ton.
            f.style.cssText = 'position:fixed; left:-9999px; bottom:0; width:320px; height:120px; border:0;';
            document.body.appendChild(f);
            musicEmbed = f;
            // Embeds starten je nach Browser nur nach Interaktion -> Hinweis-Overlay.
            if (!userInteracted) tapStart.classList.remove('hidden');
        }

        tapStart.addEventListener('click', () => {
            userInteracted = true;
            tapStart.classList.add('hidden');
            startMusic();
            requestFullscreen();
        });

        function requestFullscreen() {
            const el = document.documentElement;
            if (el.requestFullscreen) el.requestFullscreen().catch(() => {});
        }

        // ---- Go ------------------------------------------------------------
        poll();
    })();
    </script>
</body>
</html>
