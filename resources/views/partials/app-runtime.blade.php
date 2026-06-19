{{-- Gemeinsame App-Runtime (Uhr, Wetter) – genutzt vom Player und von der App-Vorschau. --}}
<style>
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
    .flip-row { display: flex; gap: 2.5vmin; }
    .clk-portrait .flip-row { flex-direction: column; }
    .flip-group { position: relative; width: 20vmin; height: 24vmin; font-size: 15vmin; font-weight: 700; font-variant-numeric: tabular-nums; perspective: 50vmin; filter: drop-shadow(0 1vmin 2vmin rgba(0,0,0,.45)); }
    .clk-portrait .flip-group { width: 34vmin; }
    .flip-half, .flip-leaf { position: absolute; left: 0; width: 100%; height: 50%; overflow: hidden; background: #1c1c1e; color: #fff; text-align: center; backface-visibility: hidden; }
    .app-clock-light .flip-half, .app-clock-light .flip-leaf { background: #2b2b2e; }
    .flip-half span, .flip-leaf span { display: block; height: 24vmin; line-height: 24vmin; }
    .flip-top { top: 0; border-radius: 1.6vmin 1.6vmin 0 0; }
    .flip-bottom { bottom: 0; border-radius: 0 0 1.6vmin 1.6vmin; }
    .flip-bottom span { transform: translateY(-50%); }
    .flip-group::after { content: ''; position: absolute; top: calc(50% - 0.2vmin); left: 0; right: 0; height: 0.4vmin; background: rgba(0,0,0,.5); z-index: 6; }
    .flip-leaf-top { top: 0; border-radius: 1.6vmin 1.6vmin 0 0; transform-origin: center bottom; z-index: 5; animation: flip-down .28s ease-in forwards; }
    .flip-leaf-bottom { bottom: 0; border-radius: 0 0 1.6vmin 1.6vmin; transform-origin: center top; transform: rotateX(90deg); z-index: 5; animation: flip-up .28s ease-out .28s forwards; }
    .flip-leaf-bottom span { transform: translateY(-50%); }
    @keyframes flip-down { from { transform: rotateX(0deg); } to { transform: rotateX(-90deg); } }
    @keyframes flip-up { from { transform: rotateX(90deg); } to { transform: rotateX(0deg); } }

    /* Bento clock – Zeit in abgerundeten Glas-Kacheln auf weichem Verlauf */
    .clk-bento { gap: 4vmin; }
    .app-clock-dark.clk-bento { background: radial-gradient(125% 125% at 30% 15%, #1e293b 0%, #0b0f17 62%); }
    .app-clock-light.clk-bento { background: radial-gradient(125% 125% at 30% 15%, #ffffff 0%, #dde3ec 72%); }
    .bento-row { display: flex; align-items: center; gap: 3vmin; }
    .clk-portrait .bento-row { flex-direction: column; }
    .bento-tile {
        width: 26vmin; height: 26vmin;
        display: flex; align-items: center; justify-content: center;
        font-size: 14vmin; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums;
        border-radius: 4vmin;
        background: rgba(255,255,255,.06);
        border: .2vmin solid rgba(255,255,255,.12);
        box-shadow: 0 2vmin 5vmin rgba(0,0,0,.35), inset 0 .25vmin 0 rgba(255,255,255,.16);
        backdrop-filter: blur(1.5vmin);
    }
    .app-clock-light .bento-tile {
        background: rgba(255,255,255,.65);
        border-color: rgba(15,23,42,.06);
        box-shadow: 0 2vmin 5vmin rgba(15,23,42,.12), inset 0 .25vmin 0 rgba(255,255,255,.8);
    }
    .clk-portrait .bento-tile { width: 42vmin; height: 24vmin; }
    .bento-ampm { font-size: 6vmin; font-weight: 600; opacity: .75; }
    .clk-bento .clk-date {
        padding: 1.6vmin 4.5vmin; border-radius: 100vmin;
        background: rgba(255,255,255,.06); border: .2vmin solid rgba(255,255,255,.12);
        font-size: 3.6vmin; opacity: 1;
    }
    .app-clock-light.clk-bento .clk-date {
        background: rgba(255,255,255,.65); border-color: rgba(15,23,42,.06);
    }

    /* Weather app */
    .app-wx { position: absolute; inset: 0; display: flex; flex-direction: column; color: var(--wx-fg); background: var(--wx-bg); }
    .wx-sky   { --wx-bg: linear-gradient(160deg,#5b8def,#8fb6f5 55%,#cfe0fb); --wx-fg:#fff; --wx-panel: rgba(255,255,255,.20); --wx-panel-fg:#fff; --wx-today: rgba(255,255,255,.32); }
    .wx-sage  { --wx-bg:#e7eede; --wx-fg:#46543f; --wx-panel:#94a47e; --wx-panel-fg:#fff; --wx-today:#6f8060; }
    .wx-light { --wx-bg:#f3f4f6; --wx-fg:#1f2937; --wx-panel:#e5e7eb; --wx-panel-fg:#1f2937; --wx-today:#cbd5e1; }
    .wx-dark  { --wx-bg:#0b0f17; --wx-fg:#f3f4f6; --wx-panel: rgba(255,255,255,.08); --wx-panel-fg:#f3f4f6; --wx-today: rgba(255,255,255,.18); }
    .wx-loading { margin: auto; font-size: 4vmin; opacity: .8; }
    .app-wx .wx-icon svg { width: 14vmin; height: 14vmin; }

    /* Dezente Wetter-Animationen – nur das große, aktuelle Icon. */
    /* Volle Sonne: Strahlen drehen sich um den (symmetrischen) Mittelpunkt – sieht sauber aus. */
    .app-wx .wx-icon svg .wx-rays { transform-box: fill-box; transform-origin: center; animation: wx-spin 16s linear infinite; }
    .app-wx .wx-icon svg .wx-suncore { transform-box: fill-box; transform-origin: center; animation: wx-pulse 3.5s ease-in-out infinite; }
    /* "Teilweise bewölkt": keine Rotation (die kleine Sonne sitzt versetzt) – nur die Wolke driftet. */
    .app-wx .wx-icon svg .wx-cloud { transform-box: fill-box; transform-origin: center; animation: wx-drift 5s ease-in-out infinite; }
    .app-wx .wx-icon svg .wx-drop { transform-box: fill-box; animation: wx-rain 1.1s linear infinite; }
    .app-wx .wx-icon svg .wx-flake { transform-box: fill-box; animation: wx-snow 2.4s linear infinite; }
    .app-wx .wx-icon svg .wx-bolt { animation: wx-bolt 2.6s ease-in-out infinite; }
    .app-wx .wx-icon svg .wx-fog line { transform-box: fill-box; transform-origin: center; animation: wx-fog 4s ease-in-out infinite; }
    @keyframes wx-spin { to { transform: rotate(360deg); } }
    @keyframes wx-pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.08); opacity: .82; } }
    @keyframes wx-drift { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(0.6px); } }
    @keyframes wx-rain { 0% { transform: translateY(-1px); opacity: 0; } 30% { opacity: 1; } 100% { transform: translateY(3px); opacity: 0; } }
    @keyframes wx-snow { 0% { transform: translateY(-1px); opacity: 0; } 30% { opacity: 1; } 100% { transform: translateY(3px); opacity: .2; } }
    @keyframes wx-bolt { 0%, 90%, 100% { opacity: 1; } 93% { opacity: .15; } 96% { opacity: 1; } }
    @keyframes wx-fog { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(1.4px); } }
    @media (prefers-reduced-motion: reduce) {
        .app-wx .wx-icon svg * { animation: none !important; }
    }
    .wx-temp { font-size: 12vmin; font-weight: 300; line-height: 1; }
    .wx-meta { display: flex; gap: 4vmin; font-size: 3.2vmin; }
    .wx-meta span { display: inline-flex; align-items: center; gap: .8vmin; }
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
    .wx-compact { justify-content: center; padding: 5vmin; gap: 3vmin; }
    .wx-card { display: flex; flex-direction: column; gap: 3vmin; }
    .wx-head { display: flex; justify-content: space-between; align-items: center; gap: 3vmin; }
    .wx-info { display: flex; flex-direction: column; gap: 1.5vmin; }
    .wx-loc-name { font-size: 7vmin; font-weight: 600; }
    .wx-current { display: flex; flex-direction: column; align-items: center; }
    .wx-compact .wx-temp { font-size: 11vmin; }
    .wx-compact .wx-forecast .wx-day { background: var(--wx-today); color: var(--wx-panel-fg); }
    .wx-portrait.wx-modern .wx-forecast { flex-direction: column; }
    .wx-portrait .wx-day { flex-direction: row; justify-content: space-between; align-items: center; }
    .wx-portrait .wx-drange { flex-direction: row; gap: 1.5vmin; }
    .wx-portrait.wx-compact .wx-head { flex-direction: column; align-items: flex-start; }
    .wx-portrait.wx-compact .wx-forecast { flex-direction: column; }

    /* Menu app – Speisekarte */
    .app-menu { position: absolute; inset: 0; display: flex; flex-direction: column; gap: 3vmin; padding: 6vmin; overflow: hidden; }
    .app-menu-dark  { background: #0b0f17; color: #f3f4f6; }
    .app-menu-light { background: #f7f5f0; color: #1f2937; }
    .menu-title { font-size: 6.5vmin; font-weight: 700; text-align: center; letter-spacing: .01em; }
    .menu-cols { flex: 1; display: grid; gap: 5vmin; grid-template-columns: repeat(var(--cols, 1), minmax(0, 1fr)); align-content: start; overflow: hidden; }
    .menu-cat-name { font-size: 3.8vmin; font-weight: 600; padding-bottom: 1vmin; margin-bottom: 1.6vmin; border-bottom: .3vmin solid currentColor; opacity: .85; }
    .menu-item { display: flex; justify-content: space-between; align-items: baseline; gap: 2vmin; margin-bottom: 1.4vmin; }
    .menu-item-name { font-size: 3vmin; font-weight: 500; }
    .menu-item-desc { font-size: 2.2vmin; opacity: .65; margin-top: .3vmin; }
    .menu-item-price { font-size: 3vmin; font-weight: 600; white-space: nowrap; }
    .menu-special { border: .3vmin solid currentColor; border-radius: 2.5vmin; padding: 2.5vmin 3.5vmin; }
    .menu-special-label { font-size: 2.2vmin; text-transform: uppercase; letter-spacing: .12em; opacity: .6; margin-bottom: 1vmin; }
</style>
<script>
window.SignageApps = (function () {
    function pad2(n) { return (n < 10 ? '0' : '') + n; }

    const DE_MONTHS = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
    const EN_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const DE_DAYS = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
    const WX_DAYS = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

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

        let timeEl = null, dateEl = null, flipGroups = null, bentoTiles = null, ampmEl = null;

        if (type === 'flip') {
            const row = document.createElement('div'); row.className = 'flip-row';
            flipGroups = [];
            const count = cfg.show_seconds ? 3 : 2;
            for (let i = 0; i < count; i++) {
                const g = document.createElement('div'); g.className = 'flip-group';
                g.innerHTML = '<div class="flip-half flip-top"><span>00</span></div>'
                            + '<div class="flip-half flip-bottom"><span>00</span></div>';
                row.appendChild(g);
                flipGroups.push({ el: g, value: null });
            }
            wrap.appendChild(row);
        } else if (type === 'bento') {
            const row = document.createElement('div'); row.className = 'bento-row';
            bentoTiles = [];
            const count = cfg.show_seconds ? 3 : 2;
            for (let i = 0; i < count; i++) {
                const t = document.createElement('div'); t.className = 'bento-tile';
                row.appendChild(t);
                bentoTiles.push(t);
            }
            if (cfg.time_format === '12h') {
                ampmEl = document.createElement('div'); ampmEl.className = 'bento-ampm';
                row.appendChild(ampmEl);
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

        // Echte Flip-Clock-Animation: obere Hälfte klappt herunter, untere klappt nach.
        function setFlip(group, val) {
            const g = group.el;
            const topSpan = g.querySelector('.flip-top span');
            const botSpan = g.querySelector('.flip-bottom span');

            if (group.value === null) { // initial ohne Animation
                topSpan.textContent = val; botSpan.textContent = val; group.value = val; return;
            }
            if (group.value === val) return;

            const old = group.value;
            group.value = val;
            topSpan.textContent = val;   // neuer obere Wert (hinter dem klappenden Blatt sichtbar)
            botSpan.textContent = old;   // alter unterer Wert, bis das untere Blatt landet

            const upper = document.createElement('div');
            upper.className = 'flip-leaf flip-leaf-top';
            upper.innerHTML = '<span>' + old + '</span>';
            const lower = document.createElement('div');
            lower.className = 'flip-leaf flip-leaf-bottom';
            lower.innerHTML = '<span>' + val + '</span>';
            g.appendChild(upper); g.appendChild(lower);

            upper.addEventListener('animationend', () => upper.remove());
            lower.addEventListener('animationend', () => { botSpan.textContent = val; lower.remove(); });
        }

        function tick() {
            const now = new Date();
            const p = clockTimeParts(now, cfg);
            if (type === 'flip') {
                const vals = cfg.show_seconds ? [p.hh, p.mm, p.ss] : [p.hh, p.mm];
                vals.forEach((val, i) => setFlip(flipGroups[i], val));
            } else if (type === 'bento') {
                const vals = cfg.show_seconds ? [p.hh, p.mm, p.ss] : [p.hh, p.mm];
                vals.forEach((val, i) => { bentoTiles[i].textContent = val; });
                if (ampmEl) ampmEl.textContent = p.ampm;
            } else {
                timeEl.innerHTML = p.display + (p.ampm ? ' <span class="clk-ampm">' + p.ampm + '</span>' : '');
            }
            if (dateEl) dateEl.textContent = formatClockDate(now, cfg.date_format || 'de_long');
        }
        tick();
        const id = setInterval(tick, 1000);
        return { node: wrap, stop: () => clearInterval(id) };
    }

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
        const cloud = '<path class="wx-cloud" d="M7 18h9a3.5 3.5 0 0 0 .3-7A5 5 0 0 0 7 9.5 3.75 3.75 0 0 0 7 18z"/>';
        if (name === 'sun') {
            let rays = '';
            for (let i = 0; i < 8; i++) { const a = i * Math.PI / 4; const x1 = 12 + 7 * Math.cos(a), y1 = 12 + 7 * Math.sin(a), x2 = 12 + 9.5 * Math.cos(a), y2 = 12 + 9.5 * Math.sin(a); rays += '<line x1="' + x1.toFixed(1) + '" y1="' + y1.toFixed(1) + '" x2="' + x2.toFixed(1) + '" y2="' + y2.toFixed(1) + '"/>'; }
            return s + '<g class="wx-rays">' + rays + '</g><circle class="wx-suncore" cx="12" cy="12" r="4.5"/></svg>';
        }
        if (name === 'partly') return s + '<g class="wx-rays-sm"><line x1="8" y1="1.5" x2="8" y2="3"/><line x1="2" y1="8" x2="3.5" y2="8"/><line x1="3.5" y1="3.5" x2="4.6" y2="4.6"/></g><circle class="wx-suncore-sm" cx="8" cy="8" r="3"/>' + cloud + '</svg>';
        if (name === 'fog') return s + '<g class="wx-fog"><line x1="4" y1="8" x2="20" y2="8"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="5" y1="16" x2="19" y2="16"/></g></svg>';
        if (name === 'rain') return s + cloud + '<g class="wx-rain"><line class="wx-drop" style="animation-delay:0s" x1="9" y1="20" x2="8" y2="22.5"/><line class="wx-drop" style="animation-delay:.35s" x1="13" y1="20" x2="12" y2="22.5"/><line class="wx-drop" style="animation-delay:.7s" x1="17" y1="20" x2="16" y2="22.5"/></g></svg>';
        if (name === 'snow') return s + cloud + '<g class="wx-snow"><circle class="wx-flake" style="animation-delay:0s" cx="9" cy="21" r=".6" fill="currentColor"/><circle class="wx-flake" style="animation-delay:.7s" cx="13" cy="21.5" r=".6" fill="currentColor"/><circle class="wx-flake" style="animation-delay:1.4s" cx="17" cy="21" r=".6" fill="currentColor"/></g></svg>';
        if (name === 'storm') return s + cloud + '<path class="wx-bolt" d="M13 19l-3 3.5h3l-2 2.5"/></svg>';
        return s + cloud + '</svg>';
    }

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

    function buildMenu(cfg, portrait) {
        const theme = cfg.theme === 'light' ? 'light' : 'dark';
        const cols = portrait ? 1 : (parseInt(cfg.columns, 10) === 2 ? 2 : 1);
        const esc = function (s) {
            return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
            });
        };

        const wrap = document.createElement('div');
        wrap.className = 'app-menu app-menu-' + theme;

        function itemHtml(it) {
            let h = '<div class="menu-item"><div><div class="menu-item-name">' + esc(it.name) + '</div>';
            if (it.description) h += '<div class="menu-item-desc">' + esc(it.description) + '</div>';
            h += '</div>';
            if (it.price) h += '<div class="menu-item-price">' + esc(it.price) + '</div>';
            return h + '</div>';
        }

        let html = '';
        if (cfg.title) html += '<div class="menu-title">' + esc(cfg.title) + '</div>';
        html += '<div class="menu-cols" style="--cols:' + cols + '">';
        (cfg.categories || []).forEach(function (cat) {
            if (!cat) return;
            html += '<div class="menu-cat"><div class="menu-cat-name">' + esc(cat.name) + '</div>';
            (cat.items || []).forEach(function (it) { if (it) html += itemHtml(it); });
            html += '</div>';
        });
        html += '</div>';

        const sp = cfg.special;
        if (sp && sp.name) {
            html += '<div class="menu-special"><div class="menu-special-label">Tagesempfehlung</div>' + itemHtml(sp) + '</div>';
        }

        wrap.innerHTML = html;
        return { node: wrap, stop: function () {} };
    }

    function build(type, cfg, portrait) {
        if (type === 'clock') return buildClock(cfg, portrait);
        if (type === 'weather') return buildWeather(cfg, portrait);
        if (type === 'menu') return buildMenu(cfg, portrait);
        return null;
    }

    return { build: build };
})();
</script>
