{{--
    Kita-Office-Board (app_type=kitaoffice). Gestaltung nach Patricks Prototyp:
    Kopf (Wochentag + KW, Datum, Logo + Titel, Wetter, Uhr), links oben CoolCooking-Boxen,
    links unten Menüs + Kalender (Anbindung folgt), rechts alle Touren als kompakte Zeilen.
    Daten: config.endpoint (Boxen + Touren, Regeln serverseitig), Wetter direkt von
    Open-Meteo (Fallback Bright Sky/DWD). Themes: hell (Standard), dunkel, automatisch.
--}}
<style>
    .app-ko { --ko-bg:#efede6; --ko-head:#ffffff; --ko-panel:#ffffff; --ko-line:#e2dfd5; --ko-text:#1c2a22; --ko-muted:#6d7a71;
        --ko-accent:#5a9a2e; --ko-accent-text:#3f7a1c; --ko-card:#fbfaf6; --ko-card-line:#e5e2d8; --ko-warn:#9a5b00; --ko-sun:#e3a008;
        --ko-alert-bg:#fde6ea; --ko-alert-line:#d9546c; --ko-alert-text:#a3142f;
        --ko-off-bg:#ecebe5; --ko-off-text:#7b847e; --ko-badge-bg:#5b3b1c; --ko-badge-text:#ffffff; --ko-shadow:0 1px 3px #1c2a2214;
        position:absolute; inset:0; background:var(--ko-bg); color:var(--ko-text);
        font-family:"Segoe UI",system-ui,-apple-system,Roboto,sans-serif; display:flex; flex-direction:column; overflow:hidden;
        border-top:.5vh solid var(--ko-accent); }
    .app-ko.ko-dark { --ko-bg:#0c1720; --ko-head:#10202a; --ko-panel:#142630; --ko-line:#2c414c; --ko-text:#f0f6f8; --ko-muted:#a6bac5;
        --ko-accent:#75ddba; --ko-accent-text:#75ddba; --ko-card:#192f3b; --ko-card-line:#304954; --ko-warn:#ffd08c; --ko-sun:#ffd08c;
        --ko-alert-bg:#702d3b; --ko-alert-line:#ff8da3; --ko-alert-text:#ffffff;
        --ko-off-bg:#222e36; --ko-off-text:#8fa2ad; --ko-badge-bg:#ffd08c; --ko-badge-text:#0c1720; --ko-shadow:none; }

    /* Kopf */
    .ko-head { flex:none; height:11.5vh; padding:0 2vw; display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:2vw;
        background:var(--ko-head); border-bottom:1px solid var(--ko-line); }
    .ko-day { font-size:4.2vh; font-weight:600; line-height:1.05; white-space:nowrap; }
    .ko-day span { font-size:3vh; font-weight:600; margin-left:.8vw; }
    .ko-date { font-size:1.8vh; color:var(--ko-muted); margin-top:.6vh; }
    .ko-brand { text-align:center; min-width:0; }
    .ko-logo { display:block; height:4.4vh; max-width:22vw; margin:0 auto .5vh; object-fit:contain; }
    .ko-title { font-size:3.7vh; font-weight:500; letter-spacing:-.02em; white-space:nowrap; }
    .ko-right { display:flex; align-items:center; justify-content:flex-end; gap:2vw; }
    .ko-wx { text-align:right; }
    .ko-wx-main { display:flex; align-items:center; justify-content:flex-end; gap:.6vw; }
    .ko-wx-icon { width:4.6vh; height:4.6vh; color:var(--ko-sun); }
    .ko-wx-icon svg { width:100%; height:100%; }
    .ko-wx-temp { font-size:3.8vh; font-weight:500; line-height:1; white-space:nowrap; }
    .ko-wx-sub { font-size:1.35vh; color:var(--ko-muted); margin-top:.5vh; white-space:nowrap; }
    .ko-clock { font-size:3.4vh; font-variant-numeric:tabular-nums; white-space:nowrap; }

    /* Arbeitsfläche */
    .ko-work { flex:1; min-height:0; padding:2.2vh 1.4vw; display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr);
        grid-template-rows:minmax(0,1.15fr) minmax(0,1fr); gap:2.2vh 1.4vw; }
    .ko-boxes-slot { grid-column:1; grid-row:1; }
    .ko-tours-slot { grid-column:2; grid-row:1 / span 2; }
    .ko-bottom { grid-column:1; grid-row:2; display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:1.4vw; min-height:0; }
    .ko-slot { min-height:0; min-width:0; background:var(--ko-panel); border:1px solid var(--ko-line); border-radius:1.2vh;
        display:flex; flex-direction:column; overflow:hidden; box-shadow:var(--ko-shadow); }
    .ko-slot-head { flex:none; height:5.6vh; padding:0 1.2vw; display:flex; align-items:center; justify-content:space-between; gap:1vw;
        border-bottom:1px solid var(--ko-line); border-left:.35vw solid var(--ko-accent); }
    .ko-slot-head h2 { margin:0; font-size:2.6vh; font-weight:500; white-space:nowrap; }
    .ko-live { font-size:1.35vh; color:var(--ko-accent-text); white-space:nowrap; }
    .ko-live:before { content:'●'; margin-right:.5vw; font-size:1vh; vertical-align:middle; }
    .ko-live.bad { color:var(--ko-warn); }
    .ko-slot-body { flex:1; min-height:0; padding:1vh .7vw; overflow:hidden; position:relative; }
    .ko-slot-foot { flex:none; height:3vh; padding:0 1vw; display:flex; align-items:center; justify-content:space-between; gap:1vw;
        font-size:1.25vh; color:var(--ko-muted); border-top:1px solid var(--ko-line); white-space:nowrap; }
    .ko-msg { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.8vh;
        text-align:center; padding:3vh; color:var(--ko-muted); font-size:1.9vh; }
    .ko-msg b { color:var(--ko-text); font-weight:600; font-size:2.1vh; }

    /* Boxen */
    .ko-boxes { height:100%; display:flex; flex-direction:column; gap:.9vh; }
    .ko-box { flex:1; min-height:0; max-height:15vh; border:1px solid var(--ko-card-line); background:var(--ko-card); border-radius:.9vh;
        display:grid; grid-template-columns:minmax(0,1.9fr) minmax(0,.6fr) minmax(0,1.1fr); align-items:center; gap:1vw; padding:.9vh 1vw; }
    .ko-box.lv-alert { background:var(--ko-alert-bg); border-color:var(--ko-alert-line); }
    .ko-box.lv-warn { border-color:var(--ko-warn); }
    .ko-box.lv-info { background:var(--ko-off-bg); color:var(--ko-off-text); }
    .ko-box-name { font-size:2.2vh; font-weight:500; line-height:1.2; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
    .ko-box-tel { font-size:2.1vh; margin-top:.3vh; font-variant-numeric:tabular-nums; }
    .ko-box-meta { font-size:1.2vh; color:var(--ko-muted); margin-top:.5vh; }
    .ko-box-mode { text-align:center; }
    .ko-box small { display:block; font-size:1.15vh; color:var(--ko-muted); margin-bottom:.3vh; }
    .ko-box-mode b { font-size:3.3vh; font-weight:600; }
    .ko-box-right { text-align:right; }
    .ko-temps { display:flex; justify-content:flex-end; gap:1.6vw; }
    .ko-temps b { font-size:3.3vh; font-weight:500; font-variant-numeric:tabular-nums; white-space:nowrap; }
    .ko-state { font-size:3.1vh; font-weight:600; line-height:1.1; }
    .lv-alert .ko-state { color:var(--ko-alert-text); }
    .lv-warn .ko-state { color:var(--ko-warn); }
    .ko-state-sub { font-size:1.2vh; color:var(--ko-muted); margin-top:.5vh; }

    /* Touren */
    .ko-tours { --ks:1; --cols:1; display:grid; grid-template-columns:repeat(var(--cols), minmax(0,1fr)); gap:calc(var(--ks) * .6vh) .7vw; align-content:start; }
    .ko-tour { border:1px solid var(--ko-card-line); background:var(--ko-card); border-radius:.8vh; padding:calc(var(--ks) * .45vh) .7vw calc(var(--ks) * .55vh); }
    .ko-tour-row { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.15fr) minmax(0,.85fr); align-items:baseline; gap:1vw; }
    .ko-tour-name { font-size:calc(var(--ks) * 2.2vh); font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ko-tour-name small { font-size:calc(var(--ks) * 1.2vh); color:var(--ko-muted); margin-left:.4vw; font-weight:400; }
    .ko-tour-phone { font-size:calc(var(--ks) * 2.2vh); font-variant-numeric:tabular-nums; text-align:center; white-space:nowrap; }
    .ko-tour-phone .ko-note { font-size:calc(var(--ks) * 1.35vh); color:var(--ko-muted); }
    .ko-tour-phone .ko-note.bad { color:var(--ko-warn); }
    .ko-badge { display:inline-block; margin-left:.5vw; padding:0 .4vw; border-radius:.4vh; font-size:calc(var(--ks) * 1.3vh);
        background:var(--ko-badge-bg); color:var(--ko-badge-text); vertical-align:middle; }
    .ko-tour-driver { font-size:calc(var(--ks) * 2.2vh); color:var(--ko-accent-text); font-weight:500; padding-left:1vw; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ko-tour-cust { font-size:calc(var(--ks) * 1.3vh); color:var(--ko-muted); line-height:1.3; }
</style>
<script>
(function () {
    window.SignageAppBuilders = window.SignageAppBuilders || {};

    const DAYS = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
    const MONTHS = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
    const esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    };
    const pad2 = function (n) { return (n < 10 ? '0' : '') + n; };

    /** Datum/Uhrzeit in Europe/Berlin – unabhängig von der Zeitzone des Geräts. */
    function berlin(d) {
        try {
            const p = {};
            new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Berlin', year: 'numeric', month: 'numeric', day: 'numeric',
                hour: 'numeric', minute: 'numeric', second: 'numeric', hourCycle: 'h23', weekday: 'short' })
                .formatToParts(d).forEach(function (x) { p[x.type] = x.value; });
            const wd = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 }[p.weekday];
            return { y: +p.year, m: +p.month, d: +p.day, h: +p.hour, i: +p.minute, s: +p.second, wd: wd };
        } catch (e) {
            return { y: d.getFullYear(), m: d.getMonth() + 1, d: d.getDate(), h: d.getHours(), i: d.getMinutes(), s: d.getSeconds(), wd: d.getDay() };
        }
    }
    function hm(iso) { const t = Date.parse(iso); if (!isFinite(t)) return ''; const b = berlin(new Date(t)); return pad2(b.h) + ':' + pad2(b.i); }
    function hms(iso) { const t = Date.parse(iso); if (!isFinite(t)) return ''; const b = berlin(new Date(t)); return pad2(b.h) + ':' + pad2(b.i) + ':' + pad2(b.s); }

    /** ISO-Kalenderwoche. */
    function isoWeek(y, m, d) {
        const t = new Date(Date.UTC(y, m - 1, d));
        const day = t.getUTCDay() || 7;
        t.setUTCDate(t.getUTCDate() + 4 - day);
        const start = new Date(Date.UTC(t.getUTCFullYear(), 0, 1));
        return Math.ceil(((t - start) / 86400000 + 1) / 7);
    }

    /** light | dark | auto (hell von 7 bis 19 Uhr). */
    function themeOf(cfg) {
        if (cfg.theme === 'dark') return 'dark';
        if (cfg.theme === 'auto') { const h = berlin(new Date()).h; return h >= 7 && h < 19 ? 'light' : 'dark'; }
        return 'light';
    }

    // Bright-Sky-Icons → Namen der gemeinsamen Wetter-Icons.
    const BS_ICON = { 'clear-day': 'sun', 'clear-night': 'sun', 'partly-cloudy-day': 'partly', 'partly-cloudy-night': 'partly', cloudy: 'cloud', fog: 'fog', wind: 'cloud', rain: 'rain', sleet: 'snow', snow: 'snow', hail: 'snow', thunderstorm: 'storm' };

    function buildKitaOffice(cfg, portrait) {
        const apps = window.SignageApps || {};
        const perPage = Math.max(1, Math.min(8, parseInt(cfg.boxes_per_page, 10) || 3));
        const pageMs = Math.max(5, parseInt(cfg.box_page_seconds, 10) || 20) * 1000;
        const offlineMin = parseInt(cfg.offline_minutes, 10) || 5;
        const until = cfg.door_warning_until || '11:00';

        const wrap = document.createElement('div');
        wrap.className = 'app-ko' + (themeOf(cfg) === 'dark' ? ' ko-dark' : '') + (portrait ? ' ko-portrait' : '');
        wrap.innerHTML =
            '<div class="ko-head">' +
                '<div><div class="ko-day" data-day></div><div class="ko-date" data-date></div></div>' +
                '<div class="ko-brand">' + (cfg.logo_url ? '<img class="ko-logo" alt="" src="' + esc(cfg.logo_url) + '">' : '') +
                    '<div class="ko-title">' + esc(cfg.title || 'Kita Office Dashboard') + '</div></div>' +
                '<div class="ko-right"><div class="ko-wx" data-wx></div><div class="ko-clock" data-clock>--:--:--</div></div>' +
            '</div>' +
            '<div class="ko-work">' +
                '<section class="ko-slot ko-boxes-slot"><div class="ko-slot-head"><h2>Status CoolCooking Boxen</h2><span class="ko-live" data-box-live>lädt …</span></div>' +
                    '<div class="ko-slot-body" data-boxes><div class="ko-msg">Boxen werden geladen …</div></div>' +
                    '<div class="ko-slot-foot"><span data-box-foot></span><span data-box-page></span></div></section>' +
                '<div class="ko-bottom">' +
                    '<section class="ko-slot"><div class="ko-slot-head"><h2>Menüs des Tages</h2></div>' +
                        '<div class="ko-slot-body"><div class="ko-msg"><b>Speiseplan</b>Anbindung über SharePoint folgt</div></div></section>' +
                    '<section class="ko-slot"><div class="ko-slot-head"><h2>Office-Kalender</h2></div>' +
                        '<div class="ko-slot-body"><div class="ko-msg"><b>AG – KITA OFFICE</b>Anbindung über Microsoft 365 folgt</div></div></section>' +
                '</div>' +
                '<section class="ko-slot ko-tours-slot"><div class="ko-slot-head"><h2>Touren &amp; Fahrer</h2><span class="ko-live" data-tour-live>lädt …</span></div>' +
                    '<div class="ko-slot-body" data-tours><div class="ko-msg">Touren werden geladen …</div></div>' +
                    '<div class="ko-slot-foot"><span data-tour-foot></span><span data-tour-count></span></div></section>' +
            '</div>';

        const $ = function (sel) { return wrap.querySelector(sel); };
        let stopped = false, timers = [], data = null, page = 0, lastAlertIds = '', lastToursKey = null;

        // ---------------- Kopf ----------------
        function tickClock() {
            const b = berlin(new Date());
            $('[data-clock]').textContent = pad2(b.h) + ':' + pad2(b.i) + ':' + pad2(b.s);
            $('[data-day]').innerHTML = esc(DAYS[b.wd]) + '<span>KW ' + isoWeek(b.y, b.m, b.d) + '</span>';
            $('[data-date]').textContent = pad2(b.d) + '. ' + MONTHS[b.m - 1] + ' ' + b.y;
            if (cfg.theme === 'auto') wrap.classList.toggle('ko-dark', themeOf(cfg) === 'dark');
        }

        function renderWeather(temp, icon, time) {
            const svg = apps.wxSvg ? apps.wxSvg(icon) : '';
            const t = (Math.round(temp * 10) / 10).toFixed(1).replace('.', ',');
            $('[data-wx]').innerHTML = '<div class="ko-wx-main"><div class="ko-wx-icon">' + svg + '</div><div class="ko-wx-temp">' + t + ' °C</div></div>' +
                '<div class="ko-wx-sub">' + esc(cfg.city || '') + ' · außen' + (time ? ' · ' + esc(hm(time)) : '') + '</div>';
        }

        async function loadWeather() {
            const lat = Number(cfg.latitude) || 51.23, lon = Number(cfg.longitude) || 6.78;
            try {
                const r = await fetch('https://api.open-meteo.com/v1/forecast?latitude=' + lat + '&longitude=' + lon + '&current=temperature_2m,weather_code&forecast_days=1&timezone=UTC', { cache: 'no-store' });
                const d = await r.json();
                if (stopped) return;
                if (!d.current || typeof d.current.temperature_2m !== 'number') throw new Error('leer');
                renderWeather(d.current.temperature_2m, apps.wxIconName ? apps.wxIconName(d.current.weather_code) : 'cloud', d.current.time + 'Z');
            } catch (e) {
                try {
                    const r = await fetch('https://api.brightsky.dev/current_weather?lat=' + lat + '&lon=' + lon, { cache: 'no-store' });
                    const d = await r.json();
                    if (stopped || !d.weather || typeof d.weather.temperature !== 'number') return;
                    renderWeather(d.weather.temperature, BS_ICON[d.weather.icon] || 'cloud', d.weather.timestamp);
                } catch (e2) { /* Anzeige behalten */ }
            }
        }

        // ---------------- Boxen ----------------
        function boxHtml(d) {
            const lv = d.error ? 'info' : (d.level || 'ok');
            const meta = [];
            if (d.serial) meta.push(esc(d.serial));
            if (d.schedule && d.schedule.end) meta.push('Planende ' + esc(d.schedule.end));

            let h = '<div class="ko-box lv-' + lv + '"><div>' +
                '<div class="ko-box-name">' + esc(d.label || d.name) + '</div>' +
                (d.phone ? '<div class="ko-box-tel">Tel. ' + esc(d.phone) + '</div>' : '') +
                (meta.length ? '<div class="ko-box-meta">' + meta.join(' · ') + '</div>' : '') + '</div>';

            // Mitte: Modus (bei Tür/Störung ein Strich – der Zustand steht rechts groß).
            const stateShown = d.door === 'open' || lv === 'alert';
            h += '<div class="ko-box-mode">' + (d.offline || d.error ? '' : '<small>Modus</small><b>' + (stateShown ? '—' : esc(d.mode || '—')) + '</b>') + '</div>';

            h += '<div class="ko-box-right">';
            if (d.error) {
                h += '<div class="ko-state">Keine Daten</div><div class="ko-state-sub">' + esc(d.error) + '</div>';
            } else if (d.offline) {
                h += '<div class="ko-state">Offline</div><div class="ko-state-sub">Keine neuen Daten seit mehr als ' + offlineMin + ' Min.</div>';
            } else if (d.door === 'open') {
                h += '<div class="ko-state">Tür geöffnet</div><div class="ko-state-sub">' +
                    (d.door_open_minutes != null ? 'seit ' + d.door_open_minutes + ' Min. · ' : '') + 'Rotwarnung nur vor ' + esc(until) + ' Uhr</div>';
            } else if (lv === 'alert' && d.warning) {
                h += '<div class="ko-state">' + esc(d.warning) + '</div><div class="ko-state-sub">Gerätemeldung</div>';
            } else {
                const t = d.temperature || {}, c = d.core || {};
                h += '<div class="ko-temps"><span><small>Temperatur</small><b>' + esc(t.actual || '–') + '</b></span>' +
                     '<span><small>Kern</small><b>' + esc(c.actual || '–') + '</b></span></div>';
            }
            return h + '</div></div>';
        }

        function renderBoxes() {
            const body = $('[data-boxes]'), live = $('[data-box-live]');
            const b = data && data.boxes;
            if (!b || !b.available) {
                body.innerHTML = '<div class="ko-msg">Keine MeinConnect-Verbindung ausgewählt.</div>';
                live.textContent = 'nicht verbunden'; live.className = 'ko-live bad';
                $('[data-box-page]').textContent = ''; $('[data-box-foot]').textContent = '';
                return;
            }
            const devs = b.devices || [];
            if (b.error && !devs.length) {
                live.textContent = 'Quelle nicht erreichbar'; live.className = 'ko-live bad';
                body.innerHTML = '<div class="ko-msg">' + esc(b.error) + '</div>';
                return;
            }

            const offline = devs.filter(function (d) { return d.offline || d.error; }).length;
            const alerts = devs.filter(function (d) { return d.level === 'alert'; }).length;
            const parts = [];
            if (alerts) parts.push(alerts + ' Warnung' + (alerts > 1 ? 'en' : ''));
            if (offline) parts.push(offline + ' offline');
            parts.push(devs.length + ' Boxen');
            live.textContent = parts.join(' · ');
            live.className = 'ko-live' + (alerts || offline || b.error ? ' bad' : '');

            const pages = [];
            for (let i = 0; i < devs.length; i += perPage) pages.push(devs.slice(i, i + perPage));
            if (!pages.length) { body.innerHTML = '<div class="ko-msg">Keine Boxen freigegeben.</div>'; return; }

            // Warnungen: nur betroffene Seiten durchlaufen; neue Warnung → sofort dorthin.
            const alertPages = [];
            pages.forEach(function (p, i) { if (p.some(function (d) { return d.level === 'alert'; })) alertPages.push(i); });
            const alertIds = devs.filter(function (d) { return d.level === 'alert'; }).map(function (d) { return d.id; }).join(',');
            if (alertIds && alertIds !== lastAlertIds) {
                const fresh = devs.find(function (d) { return d.level === 'alert' && lastAlertIds.split(',').indexOf(d.id) < 0; });
                if (fresh) page = Math.floor(devs.indexOf(fresh) / perPage);
            }
            lastAlertIds = alertIds;
            if (alertPages.length && alertPages.indexOf(page) < 0) page = alertPages[0];
            if (page >= pages.length) page = 0;

            body.innerHTML = '<div class="ko-boxes">' + pages[page].map(boxHtml).join('') + '</div>';
            $('[data-box-page]').textContent = (page + 1) + ' / ' + pages.length;
            $('[data-box-foot]').textContent = 'Stand ' + hms(b.fetched_at) + ' · alle 30 Sek.' + (b.error ? ' · Quelle gestört' : '');
        }

        function nextBoxPage() {
            const b = data && data.boxes;
            if (!b || !b.devices || b.devices.length <= perPage) return;
            const count = Math.ceil(b.devices.length / perPage);
            const alertPages = [];
            for (let i = 0; i < count; i++) {
                if (b.devices.slice(i * perPage, (i + 1) * perPage).some(function (d) { return d.level === 'alert'; })) alertPages.push(i);
            }
            if (alertPages.length) {
                const at = alertPages.indexOf(page);
                if (alertPages.length < 2 && at >= 0) return;
                page = alertPages[(at + 1) % alertPages.length];
            } else {
                page = (page + 1) % count;
            }
            renderBoxes();
        }

        // ---------------- Touren ----------------
        function tourHtml(t) {
            let phone;
            if (t.phone) {
                phone = esc(t.phone) + (t.whatsapp_only ? '<span class="ko-badge">nur über WhatsApp</span>' : '');
            } else if (t.whatsapp_only) {
                phone = '<span class="ko-badge">nur über WhatsApp</span>';
            } else {
                phone = '<span class="ko-note' + (t.phone_status === 'ambiguous' ? ' bad' : '') + '">' + esc(t.phone_note || 'Keine Nummer hinterlegt') + '</span>';
            }
            return '<div class="ko-tour"><div class="ko-tour-row">' +
                '<div class="ko-tour-name">' + esc(t.name) + (t.departure ? '<small>' + esc(t.departure) + '</small>' : '') + '</div>' +
                '<div class="ko-tour-phone">' + phone + '</div>' +
                '<div class="ko-tour-driver">' + esc(t.driver || '') + '</div>' +
                '</div>' +
                (t.customers && t.customers.length ? '<div class="ko-tour-cust">' + t.customers.map(esc).join(' · ') + '</div>' : '') +
                '</div>';
        }

        /** Alle Touren sollen gleichzeitig sichtbar sein: Schrift/Spalten anpassen, bis es passt. */
        function fitTours() {
            const body = $('[data-tours]'), list = body.querySelector('.ko-tours');
            if (!list) return;
            // Noch nicht sichtbar eingehängt (Player blendet Frames erst ein) → später erneut.
            if (!body.clientHeight) { if (!stopped) setTimeout(fitTours, 500); return; }
            const avail = body.clientHeight - 4;
            for (let cols = 1; cols <= 2; cols++) {
                for (let s = 1; s >= 0.6; s -= 0.04) {
                    list.style.setProperty('--cols', cols);
                    list.style.setProperty('--ks', s.toFixed(2));
                    if (list.scrollHeight <= avail) return;
                    if (cols < 2 && s < 0.72) break;   // lieber zweispaltig als zu klein
                }
            }
        }

        function renderTours() {
            const body = $('[data-tours]'), live = $('[data-tour-live]');
            const t = data && data.tours;
            if (!t || !t.available) {
                body.innerHTML = '<div class="ko-msg">Keine DedeFleet-Verbindung ausgewählt.</div>';
                live.textContent = 'nicht verbunden'; live.className = 'ko-live bad';
                return;
            }
            if (t.error) {
                live.textContent = 'Quelle nicht erreichbar'; live.className = 'ko-live bad';
                body.innerHTML = '<div class="ko-msg">' + esc(t.error) + '</div>';
                return;
            }
            const tours = t.tours || [];
            live.textContent = 'Heute · ' + tours.length + ' Fahrten'; live.className = 'ko-live';
            $('[data-tour-foot]').textContent = 'Stand ' + hm(data.generated_at) + ' · Telefonliste: manuell';
            $('[data-tour-count]').textContent = tours.length + ' Fahrten';
            if (!tours.length) { body.innerHTML = '<div class="ko-msg">Heute keine Touren geplant.</div>'; lastToursKey = null; return; }
            const key = JSON.stringify(tours);
            if (key === lastToursKey) return;
            lastToursKey = key;
            body.innerHTML = '<div class="ko-tours">' + tours.map(tourHtml).join('') + '</div>';
            requestAnimationFrame(fitTours);
        }

        // ---------------- Daten ----------------
        async function load() {
            if (!cfg.endpoint) { data = {}; renderBoxes(); renderTours(); return; }
            try {
                let url = cfg.endpoint;
                if (cfg.preview_config) {
                    const pick = {};
                    ['meinconnect_connection_id', 'dedefleet_connection_id', 'boxes_per_page', 'offline_minutes', 'door_warning_minutes',
                     'door_warning_until', 'hide_pickups', 'strip_prefixes', 'driver_aliases', 'whatsapp_only', 'drivers', 'facility_phones', 'box_strip_prefixes']
                        .forEach(function (k) { pick[k] = cfg[k]; });
                    url += (url.indexOf('?') >= 0 ? '&' : '?') + 'cfg=' + encodeURIComponent(JSON.stringify(pick));
                }
                const r = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
                const d = await r.json();
                if (stopped) return;
                data = d;
                renderBoxes();
                renderTours();
            } catch (e) {
                // Netzfehler: bestehende Anzeige behalten, nur markieren.
                if (stopped) return;
                if (!data) { data = {}; renderBoxes(); renderTours(); }
                $('[data-box-live]').className = 'ko-live bad';
                $('[data-tour-live]').className = 'ko-live bad';
            }
        }

        tickClock();
        loadWeather();
        load();
        timers.push(setInterval(tickClock, 1000));
        timers.push(setInterval(load, 30 * 1000));
        timers.push(setInterval(loadWeather, 10 * 60 * 1000));
        timers.push(setInterval(nextBoxPage, pageMs));
        const onResize = function () { lastToursKey = null; renderTours(); };
        window.addEventListener('resize', onResize);

        return {
            node: wrap,
            stop: function () {
                stopped = true;
                timers.forEach(clearInterval);
                window.removeEventListener('resize', onResize);
            },
        };
    }

    window.SignageAppBuilders.kitaoffice = buildKitaOffice;
})();
</script>
