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

        /* Clock app */
        .app-clock { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3vmin; }
        .app-clock-dark { background: #0b0f17; color: #fff; }
        .app-clock-light { background: #f3f4f6; color: #0b0f17; }
        .clk-time { font-size: 18vmin; font-weight: 700; letter-spacing: .02em; line-height: 1; font-variant-numeric: tabular-nums; }
        .clk-minimal .clk-time { font-weight: 200; letter-spacing: .04em; }
        .clk-ampm { font-size: 5vmin; font-weight: 400; }
        .clk-date { font-size: 5vmin; opacity: .8; }
        .clk-minimal .clk-date { font-weight: 300; }
        .clk-portrait .clk-time { font-size: 22vmin; }
        .flip-row { display: flex; gap: 2vmin; }
        .clk-portrait .flip-row { flex-direction: column; }
        .flip-group { position: relative; background: #1c1c1e; color: #fff; border-radius: 2vmin; padding: 3vmin 4vmin; font-size: 16vmin; font-weight: 700; font-variant-numeric: tabular-nums; box-shadow: 0 1vmin 3vmin rgba(0,0,0,.4); }
        .flip-group::after { content: ''; position: absolute; left: 0; right: 0; top: 50%; height: 2px; background: rgba(0,0,0,.55); transform: translateY(-50%); }
        .flip-anim { animation: flipdown .4s ease; }
        @keyframes flipdown { 0% { transform: perspective(400px) rotateX(0); } 50% { transform: perspective(400px) rotateX(-20deg); } 100% { transform: perspective(400px) rotateX(0); } }

        /* Weather app */
        .app-wx { position: absolute; inset: 0; display: flex; flex-direction: column; color: var(--wx-fg); background: var(--wx-bg); }
        .wx-sky   { --wx-bg: linear-gradient(160deg,#5b8def,#8fb6f5 55%,#cfe0fb); --wx-fg:#fff; --wx-panel: rgba(255,255,255,.20); --wx-panel-fg:#fff; --wx-today: rgba(255,255,255,.32); }
        .wx-sage  { --wx-bg:#e7eede; --wx-fg:#46543f; --wx-panel:#94a47e; --wx-panel-fg:#fff; --wx-today:#6f8060; }
        .wx-light { --wx-bg:#f3f4f6; --wx-fg:#1f2937; --wx-panel:#e5e7eb; --wx-panel-fg:#1f2937; --wx-today:#cbd5e1; }
        .wx-dark  { --wx-bg:#0b0f17; --wx-fg:#f3f4f6; --wx-panel: rgba(255,255,255,.08); --wx-panel-fg:#f3f4f6; --wx-today: rgba(255,255,255,.18); }
        .wx-loading { margin: auto; font-size: 4vmin; opacity: .8; }
        .app-wx .wx-icon svg { width: 14vmin; height: 14vmin; }
        .wx-temp { font-size: 12vmin; font-weight: 300; line-height: 1; }
        .wx-meta { display: flex; gap: 4vmin; font-size: 3.2vmin; }
        .wx-meta span { display: inline-flex; align-items: center; gap: .8vmin; }
        /* modern */
        .wx-top { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1.5vmin; padding: 3vmin; }
        .wx-loc { font-size: 3.6vmin; opacity: .95; }
        .wx-panel { background: var(--wx-panel); color: var(--wx-panel-fg); padding: 2.5vmin 3vmin; border-radius: 3vmin 3vmin 0 0; }
        .wx-when { text-align: right; font-size: 2.6vmin; opacity: .9; margin-bottom: 1.5vmin; }
        .wx-forecast { display: flex; gap: 1.5vmin; }
        .wx-day { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 1vmin; padding: 1.5vmin 1vmin; border-radius: 2vmin; font-size: 2.4vmin; }
        .wx-day.wx-today { background: var(--wx-today); }
        .wx-day .wx-dicon svg { width: 6vmin; height: 6vmin; }
        .wx-drange { display: flex; flex-direction: column; align-items: center; line-height: 1.2; }
        .wx-drange span { opacity: .7; }
        /* compact */
        .wx-compact { justify-content: center; padding: 5vmin; gap: 3vmin; }
        .wx-card { display: flex; flex-direction: column; gap: 3vmin; }
        .wx-head { display: flex; justify-content: space-between; align-items: center; gap: 3vmin; }
        .wx-info { display: flex; flex-direction: column; gap: 1.5vmin; }
        .wx-loc-name { font-size: 7vmin; font-weight: 600; }
        .wx-current { display: flex; flex-direction: column; align-items: center; }
        .wx-compact .wx-temp { font-size: 11vmin; }
        .wx-compact .wx-forecast .wx-day { background: var(--wx-today); color: var(--wx-panel-fg); }
        /* portrait */
        .wx-portrait.wx-modern .wx-forecast { flex-direction: column; }
        .wx-portrait .wx-day { flex-direction: row; justify-content: space-between; align-items: center; }
        .wx-portrait .wx-drange { flex-direction: row; gap: 1.5vmin; }
        .wx-portrait.wx-compact .wx-head { flex-direction: column; align-items: flex-start; }
        .wx-portrait.wx-compact .wx-forecast { flex-direction: column; }

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

                if (state.status === 'pending') {
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
            const built = buildApp(item.app_type, item.config || {}, portrait);
            if (built) {
                frame.appendChild(built.node);
                frame._cleanup = built.stop;
            }
            mount(frame);
            const ms = (item.duration || 10) * 1000;
            frameTimer = setTimeout(() => { swap(frame); advance(); }, ms);
        }

        function buildApp(type, cfg, portrait) {
            if (type === 'clock') return buildClock(cfg, portrait);
            if (type === 'weather') return buildWeather(cfg, portrait);
            return null;
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

        // ---- Clock app -----------------------------------------------------
        function pad2(n) { return (n < 10 ? '0' : '') + n; }

        const DE_MONTHS = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
        const EN_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const DE_DAYS = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];

        function formatClockDate(d, fmt) {
            const day = d.getDate(), mon = d.getMonth(), yr = d.getFullYear();
            if (fmt === 'de_short') return pad2(day) + '.' + pad2(mon + 1) + '.' + yr;
            if (fmt === 'en_long') return EN_MONTHS[mon] + ' ' + pad2(day) + ', ' + yr;
            if (fmt === 'iso') return yr + '-' + pad2(mon + 1) + '-' + pad2(day);
            return DE_DAYS[d.getDay()] + ', ' + day + '. ' + DE_MONTHS[mon] + ' ' + yr;
        }

        function clockTimeParts(now, cfg) {
            const h = now.getHours(), m = pad2(now.getMinutes()), s = pad2(now.getSeconds());
            let ampm = '', hh;
            if (cfg.time_format === '12h') {
                ampm = h >= 12 ? 'PM' : 'AM';
                let h12 = h % 12; if (h12 === 0) h12 = 12;
                hh = pad2(h12);
            } else {
                hh = pad2(h);
            }
            const display = (cfg.time_format === '12h' ? String(parseInt(hh, 10)) : hh) + ':' + m + (cfg.show_seconds ? ':' + s : '');
            return { hh: hh, mm: m, ss: s, display: display, ampm: ampm };
        }

        function buildClock(cfg, portrait) {
            const theme = cfg.theme === 'light' ? 'light' : 'dark';
            const type = cfg.clock_type || 'modern_digital';
            const wrap = document.createElement('div');
            wrap.className = 'app-clock app-clock-' + theme + ' clk-' + type + (portrait ? ' clk-portrait' : '');

            let timeEl = null, dateEl = null, flipGroups = null;

            if (type === 'flip') {
                const row = document.createElement('div'); row.className = 'flip-row';
                flipGroups = [];
                const count = cfg.show_seconds ? 3 : 2;
                for (let i = 0; i < count; i++) {
                    const g = document.createElement('div'); g.className = 'flip-group';
                    const span = document.createElement('span'); span.textContent = '00';
                    g.appendChild(span); row.appendChild(g); flipGroups.push(span);
                }
                wrap.appendChild(row);
            } else {
                timeEl = document.createElement('div'); timeEl.className = 'clk-time';
                wrap.appendChild(timeEl);
            }
            if (cfg.show_date) {
                dateEl = document.createElement('div'); dateEl.className = 'clk-date';
                wrap.appendChild(dateEl);
            }

            function tick() {
                const now = new Date();
                const p = clockTimeParts(now, cfg);
                if (type === 'flip') {
                    const vals = cfg.show_seconds ? [p.hh, p.mm, p.ss] : [p.hh, p.mm];
                    vals.forEach((val, i) => {
                        if (flipGroups[i].textContent !== val) {
                            flipGroups[i].textContent = val;
                            const box = flipGroups[i].parentNode;
                            box.classList.remove('flip-anim'); void box.offsetWidth; box.classList.add('flip-anim');
                        }
                    });
                } else {
                    timeEl.innerHTML = p.display + (p.ampm ? ' <span class="clk-ampm">' + p.ampm + '</span>' : '');
                }
                if (dateEl) dateEl.textContent = formatClockDate(now, cfg.date_format || 'de_long');
            }
            tick();
            const id = setInterval(tick, 1000);
            return { node: wrap, stop: () => clearInterval(id) };
        }

        // ---- Weather app (Open-Meteo, kein API-Key) ------------------------
        function wxIconName(code) {
            if (code === 0) return 'sun';
            if (code === 1 || code === 2) return 'partly';
            if (code === 3) return 'cloud';
            if (code === 45 || code === 48) return 'fog';
            if (code >= 51 && code <= 67) return 'rain';
            if (code >= 71 && code <= 77) return 'snow';
            if (code >= 80 && code <= 82) return 'rain';
            if (code === 85 || code === 86) return 'snow';
            if (code >= 95) return 'storm';
            return 'cloud';
        }

        function wxSvg(name) {
            const s = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">';
            const cloud = '<path d="M7 18h9a3.5 3.5 0 0 0 .3-7A5 5 0 0 0 7 9.5 3.75 3.75 0 0 0 7 18z"/>';
            if (name === 'sun') {
                let rays = '';
                for (let i = 0; i < 8; i++) { const a = i * Math.PI / 4; const x1 = 12 + 7 * Math.cos(a), y1 = 12 + 7 * Math.sin(a), x2 = 12 + 9.5 * Math.cos(a), y2 = 12 + 9.5 * Math.sin(a); rays += '<line x1="' + x1.toFixed(1) + '" y1="' + y1.toFixed(1) + '" x2="' + x2.toFixed(1) + '" y2="' + y2.toFixed(1) + '"/>'; }
                return s + '<circle cx="12" cy="12" r="4.5"/>' + rays + '</svg>';
            }
            if (name === 'partly') return s + '<circle cx="8" cy="8" r="3"/><line x1="8" y1="1.5" x2="8" y2="3"/><line x1="2" y1="8" x2="3.5" y2="8"/><line x1="3.5" y1="3.5" x2="4.6" y2="4.6"/>' + cloud + '</svg>';
            if (name === 'fog') return s + '<line x1="4" y1="8" x2="20" y2="8"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="5" y1="16" x2="19" y2="16"/></svg>';
            if (name === 'rain') return s + cloud + '<line x1="9" y1="20" x2="8" y2="22.5"/><line x1="13" y1="20" x2="12" y2="22.5"/><line x1="17" y1="20" x2="16" y2="22.5"/></svg>';
            if (name === 'snow') return s + cloud + '<circle cx="9" cy="21" r=".6" fill="currentColor"/><circle cx="13" cy="21.5" r=".6" fill="currentColor"/><circle cx="17" cy="21" r=".6" fill="currentColor"/></svg>';
            if (name === 'storm') return s + cloud + '<path d="M13 19l-3 3.5h3l-2 2.5"/></svg>';
            return s + cloud + '</svg>';
        }

        const WX_DAYS = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

        function buildWeather(cfg, portrait) {
            const scheme = ['sky', 'sage', 'light', 'dark'].indexOf(cfg.color_scheme) >= 0 ? cfg.color_scheme : 'sky';
            const design = cfg.design === 'compact' ? 'compact' : 'modern';
            const units = cfg.units === 'imperial'
                ? { t: 'fahrenheit', w: 'mph', ts: '°F', ws: 'mph' }
                : { t: 'celsius', w: 'kmh', ts: '°C', ws: 'km/h' };

            const wrap = document.createElement('div');
            wrap.className = 'app-wx wx-' + scheme + ' wx-' + design + (portrait ? ' wx-portrait' : '');
            wrap.innerHTML = '<div class="wx-loading">Wetter wird geladen …</div>';

            let stopped = false, timer = null;
            const loc = cfg.location_name || '';

            function nowStr() {
                const d = new Date();
                const t = pad2(d.getHours()) + ':' + pad2(d.getMinutes());
                return t + '  ' + DE_DAYS[d.getDay()] + ', ' + d.getDate() + '. ' + DE_MONTHS[d.getMonth()];
            }

            function dayCell(i, code, max, min) {
                const d = new Date(); d.setDate(d.getDate() + i);
                const label = i === 0 ? 'Heute' : WX_DAYS[d.getDay()] + ' ' + pad2(d.getDate());
                return '<div class="wx-day' + (i === 0 ? ' wx-today' : '') + '">' +
                    '<div class="wx-dname">' + label + '</div>' +
                    '<div class="wx-dicon">' + wxSvg(wxIconName(code)) + '</div>' +
                    '<div class="wx-drange">' + Math.round(max) + '°<span>' + Math.round(min) + '°</span></div></div>';
            }

            function render(d) {
                const c = d.current || {};
                const daily = d.daily || {};
                const temp = Math.round(c.temperature_2m);
                const hum = Math.round(c.relative_humidity_2m);
                const wind = (Math.round((c.wind_speed_10m || 0) * 10) / 10);
                const icon = wxSvg(wxIconName(c.weather_code));

                let cells = '';
                const n = Math.min(7, (daily.time || []).length);
                for (let i = 0; i < n; i++) {
                    cells += dayCell(i, daily.weather_code[i], daily.temperature_2m_max[i], daily.temperature_2m_min[i]);
                }

                const meta = '<div class="wx-meta"><span>&#128167; ' + hum + '%</span><span>&#128168; ' + wind + units.ws + '</span></div>';

                if (design === 'compact') {
                    wrap.innerHTML =
                        '<div class="wx-card">' +
                            '<div class="wx-head">' +
                                '<div class="wx-info"><div class="wx-loc-name">' + loc + '</div><div class="wx-when">' + nowStr() + '</div>' + meta + '</div>' +
                                '<div class="wx-current"><div class="wx-icon">' + icon + '</div><div class="wx-temp">' + temp + units.ts + '</div></div>' +
                            '</div>' +
                            '<div class="wx-forecast">' + cells + '</div>' +
                        '</div>';
                } else {
                    wrap.innerHTML =
                        '<div class="wx-top">' +
                            '<div class="wx-loc">&#128205; ' + loc + '</div>' +
                            '<div class="wx-icon">' + icon + '</div>' +
                            '<div class="wx-temp">' + temp + units.ts + '</div>' + meta +
                        '</div>' +
                        '<div class="wx-panel"><div class="wx-when">' + nowStr() + '</div><div class="wx-forecast">' + cells + '</div></div>';
                }
            }

            async function load() {
                try {
                    const url = 'https://api.open-meteo.com/v1/forecast?latitude=' + cfg.latitude + '&longitude=' + cfg.longitude +
                        '&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m' +
                        '&daily=weather_code,temperature_2m_max,temperature_2m_min&forecast_days=7&timezone=auto' +
                        '&temperature_unit=' + units.t + '&wind_speed_unit=' + units.w;
                    const r = await fetch(url, { cache: 'no-store' });
                    const d = await r.json();
                    if (stopped) return;
                    render(d);
                } catch (e) {
                    if (!stopped) wrap.innerHTML = '<div class="wx-loading">Wetterdaten nicht verfügbar</div>';
                }
            }

            load();
            timer = setInterval(load, 10 * 60 * 1000);
            return { node: wrap, stop: () => { stopped = true; if (timer) clearInterval(timer); } };
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
