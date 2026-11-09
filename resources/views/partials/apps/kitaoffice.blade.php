{{--
    Kita-Office-Board (app_type=kitaoffice). Gestaltung nach Patricks Prototyp:
    Kopf (Wochentag, Datum, KW, Titel, Wetter, Uhr), links CoolCooking-Boxen,
    rechts alle Touren mit Fahrer, Telefon und Kunden.
    Daten: config.endpoint (Boxen + Touren, serverseitig mit Regeln), Wetter direkt
    von Open-Meteo (Fallback Bright Sky/DWD).
--}}
<style>
    .app-ko { --ko-bg:#0c1720; --ko-panel:#142630; --ko-line:#2c414c; --ko-muted:#a6bac5; --ko-green:#75ddba; --ko-orange:#ffd08c; --ko-red:#ffabb9;
        position:absolute; inset:0; background:var(--ko-bg); color:#f0f6f8; font-family:"Segoe UI",system-ui,-apple-system,Roboto,sans-serif;
        display:flex; flex-direction:column; overflow:hidden; }
    .ko-head { flex:none; height:11vh; padding:0 2.2vw; display:flex; align-items:center; justify-content:space-between; gap:2vw; background:#10202a; border-bottom:1px solid var(--ko-line); }
    .ko-day { font-size:3.4vh; font-weight:600; line-height:1.1; }
    .ko-date { font-size:1.8vh; color:var(--ko-muted); margin-top:.4vh; }
    .ko-brand { display:flex; align-items:center; gap:1vw; min-width:0; }
    .ko-mark { width:5vh; height:5vh; border-radius:1.3vh; display:grid; place-items:center; color:var(--ko-green); background:#76ddba19; border:1px solid #76ddba55; font-size:2.8vh; flex:none; }
    .ko-title { font-size:3.8vh; font-weight:600; letter-spacing:-.04em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ko-right { display:flex; align-items:center; gap:2.2vw; }
    .ko-wx { display:flex; align-items:center; gap:.8vw; }
    .ko-wx-icon { width:5vh; height:5vh; color:var(--ko-orange); }
    .ko-wx-icon svg { width:100%; height:100%; }
    .ko-wx-temp { font-size:3.6vh; font-weight:600; line-height:1; }
    .ko-wx-city { font-size:1.4vh; color:var(--ko-muted); margin-top:.3vh; }
    .ko-clock { font-size:4.4vh; font-weight:600; font-variant-numeric:tabular-nums; }
    .ko-work { flex:1; min-height:0; padding:2vh 1.6vw 2.2vh; display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:1.6vw; }
    .ko-slot { min-height:0; min-width:0; background:var(--ko-panel); border:1px solid var(--ko-line); border-radius:1.6vh; display:flex; flex-direction:column; overflow:hidden; }
    .ko-slot-head { flex:none; height:5.4vh; padding:0 1.2vw; display:flex; align-items:center; justify-content:space-between; gap:1vw; border-bottom:1px solid var(--ko-line); }
    .ko-slot-head h2 { margin:0; font-size:2.3vh; font-weight:600; }
    .ko-live { font-size:1.35vh; color:var(--ko-green); white-space:nowrap; }
    .ko-live:before { content:'●'; margin-right:.4vw; }
    .ko-live.bad { color:var(--ko-orange); }
    .ko-slot-body { flex:1; min-height:0; padding:1.2vh .9vw; overflow:hidden; position:relative; }
    .ko-slot-foot { flex:none; height:3.2vh; padding:0 1vw; display:flex; align-items:center; justify-content:space-between; font-size:1.3vh; color:var(--ko-muted); border-top:1px solid var(--ko-line); }
    .ko-msg { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; text-align:center; padding:3vh; color:var(--ko-muted); font-size:2vh; }

    /* Boxen */
    .ko-boxes { height:100%; display:flex; flex-direction:column; gap:1vh; }
    .ko-box { flex:1; min-height:0; max-height:24vh; border:1px solid #304954; background:#192f3b; border-radius:1.1vh; display:grid; grid-template-columns:minmax(0,1.15fr) minmax(0,1fr); align-items:center; gap:.8vw; padding:1.2vh 1vw; }
    .ko-box.lv-alert { background:#702d3b; border-color:#ff8da3; }
    .ko-box.lv-warn { border-color:var(--ko-orange); }
    .ko-box.lv-info { background:#222e36; border-color:#596c77; }
    .ko-box-name { font-size:2.1vh; font-weight:600; line-height:1.25; }
    .ko-box-detail { font-size:1.35vh; color:var(--ko-muted); margin-top:.6vh; line-height:1.45; }
    .ko-box-warn { display:inline-block; margin-top:.8vh; padding:.3vh .6vw; border-radius:.7vh; font-size:1.5vh; font-weight:600; background:#00000033; }
    .lv-alert .ko-box-warn { color:#fff; background:#ff8da333; }
    .lv-warn .ko-box-warn { color:var(--ko-orange); }
    .lv-info .ko-box-warn { color:var(--ko-muted); }
    .ko-box-status { text-align:right; }
    .ko-mode { color:var(--ko-green); font-size:1.5vh; font-weight:600; margin-bottom:.6vh; }
    .lv-info .ko-mode { color:var(--ko-muted); }
    .ko-temps { display:flex; justify-content:flex-end; gap:1.4vw; }
    .ko-temps small { display:block; font-size:1.15vh; color:var(--ko-muted); margin-bottom:.4vh; font-weight:400; }
    .ko-temps b { font-size:2.8vh; font-weight:650; font-variant-numeric:tabular-nums; white-space:nowrap; }
    .ko-temps i { font-style:normal; font-size:1.6vh; color:var(--ko-muted); font-weight:400; }
    .ko-sched { font-size:1.35vh; color:var(--ko-muted); margin-top:.7vh; }

    /* Touren */
    .ko-tours { --ks:1; --cols:1; display:grid; grid-template-columns:repeat(var(--cols), minmax(0,1fr)); gap:calc(var(--ks) * .9vh) .8vw; align-content:start; }
    .ko-tour { border:1px solid #304954; background:#192f3b; border-radius:1vh; padding:calc(var(--ks) * .9vh) .8vw; break-inside:avoid; }
    .ko-tour-top { display:flex; align-items:baseline; gap:.7vw; flex-wrap:wrap; }
    .ko-tour-dep { font-size:calc(var(--ks) * 2.2vh); font-weight:650; font-variant-numeric:tabular-nums; color:var(--ko-green); }
    .ko-tour-name { font-size:calc(var(--ks) * 1.9vh); font-weight:600; }
    .ko-tour-veh { font-size:calc(var(--ks) * 1.4vh); color:var(--ko-muted); margin-left:auto; }
    .ko-tour-driver { margin-top:calc(var(--ks) * .4vh); font-size:calc(var(--ks) * 1.9vh); display:flex; gap:.7vw; flex-wrap:wrap; align-items:baseline; }
    .ko-tour-driver b { font-weight:600; }
    .ko-phone { font-variant-numeric:tabular-nums; }
    .ko-note { font-size:calc(var(--ks) * 1.45vh); color:var(--ko-orange); }
    .ko-wa { font-size:calc(var(--ks) * 1.45vh); color:var(--ko-green); }
    .ko-tour-cust { margin-top:calc(var(--ks) * .4vh); font-size:calc(var(--ks) * 1.5vh); color:var(--ko-muted); line-height:1.4; }
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
            new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Berlin', year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', hourCycle: 'h23', weekday: 'short' })
                .formatToParts(d).forEach(function (x) { p[x.type] = x.value; });
            const wd = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 }[p.weekday];
            return { y: +p.year, m: +p.month, d: +p.day, h: +p.hour, i: +p.minute, wd: wd };
        } catch (e) {
            return { y: d.getFullYear(), m: d.getMonth() + 1, d: d.getDate(), h: d.getHours(), i: d.getMinutes(), wd: d.getDay() };
        }
    }

    /** ISO-Kalenderwoche. */
    function isoWeek(y, m, d) {
        const t = new Date(Date.UTC(y, m - 1, d));
        const day = t.getUTCDay() || 7;
        t.setUTCDate(t.getUTCDate() + 4 - day);
        const start = new Date(Date.UTC(t.getUTCFullYear(), 0, 1));
        return Math.ceil(((t - start) / 86400000 + 1) / 7);
    }

    function ago(seconds) {
        if (seconds == null) return '';
        if (seconds < 90) return 'gerade eben';
        const m = Math.round(seconds / 60);
        if (m < 90) return 'vor ' + m + ' Min.';
        return 'vor ' + Math.round(m / 60) + ' Std.';
    }

    function duration(min) {
        if (min == null) return '';
        const h = Math.floor(min / 60), m = min % 60;
        return h ? (h + ' Std. ' + m + ' Min.') : (m + ' Min.');
    }

    // Bright-Sky-Icons → Namen der gemeinsamen Wetter-Icons.
    const BS_ICON = { 'clear-day': 'sun', 'clear-night': 'sun', 'partly-cloudy-day': 'partly', 'partly-cloudy-night': 'partly', cloudy: 'cloud', fog: 'fog', wind: 'cloud', rain: 'rain', sleet: 'snow', snow: 'snow', hail: 'snow', thunderstorm: 'storm' };

    function buildKitaOffice(cfg, portrait) {
        const apps = window.SignageApps || {};
        const perPage = Math.max(1, Math.min(8, parseInt(cfg.boxes_per_page, 10) || 3));
        const pageMs = Math.max(5, parseInt(cfg.box_page_seconds, 10) || 20) * 1000;

        const wrap = document.createElement('div');
        wrap.className = 'app-ko' + (portrait ? ' ko-portrait' : '');
        wrap.innerHTML =
            '<div class="ko-head">' +
                '<div><div class="ko-day" data-day></div><div class="ko-date" data-date></div></div>' +
                '<div class="ko-brand"><div class="ko-mark">✦</div><div class="ko-title">' + esc(cfg.title || 'Kita Office Dashboard') + '</div></div>' +
                '<div class="ko-right">' +
                    '<div class="ko-wx" data-wx></div>' +
                    '<div class="ko-clock" data-clock>--:--</div>' +
                '</div>' +
            '</div>' +
            '<div class="ko-work">' +
                '<section class="ko-slot"><div class="ko-slot-head"><h2>CoolCooking-Boxen</h2><span class="ko-live" data-box-live>lädt …</span></div>' +
                    '<div class="ko-slot-body" data-boxes><div class="ko-msg">Boxen werden geladen …</div></div>' +
                    '<div class="ko-slot-foot"><span data-box-foot></span><span data-box-page></span></div></section>' +
                '<section class="ko-slot"><div class="ko-slot-head"><h2>Touren &amp; Fahrer</h2><span class="ko-live" data-tour-live>lädt …</span></div>' +
                    '<div class="ko-slot-body" data-tours><div class="ko-msg">Touren werden geladen …</div></div>' +
                    '<div class="ko-slot-foot"><span data-tour-foot></span><span></span></div></section>' +
            '</div>';

        const $ = function (sel) { return wrap.querySelector(sel); };
        let stopped = false, timers = [], data = null, page = 0, lastAlertIds = '', lastToursKey = null;

        // ---------------- Kopf ----------------
        function tickClock() {
            const b = berlin(new Date());
            $('[data-clock]').textContent = pad2(b.h) + ':' + pad2(b.i);
            $('[data-day]').textContent = DAYS[b.wd];
            $('[data-date]').textContent = b.d + '. ' + MONTHS[b.m - 1] + ' ' + b.y + ' · KW ' + isoWeek(b.y, b.m, b.d);
        }

        function renderWeather(temp, icon, stale) {
            const svg = apps.wxSvg ? apps.wxSvg(icon) : '';
            $('[data-wx]').innerHTML = '<div class="ko-wx-icon">' + svg + '</div>' +
                '<div><div class="ko-wx-temp">' + Math.round(temp) + '°</div><div class="ko-wx-city">' + esc(cfg.city || '') + (stale ? ' · veraltet' : '') + '</div></div>';
        }

        async function loadWeather() {
            const lat = Number(cfg.latitude) || 51.23, lon = Number(cfg.longitude) || 6.78;
            try {
                const r = await fetch('https://api.open-meteo.com/v1/forecast?latitude=' + lat + '&longitude=' + lon + '&current=temperature_2m,weather_code&forecast_days=1&timezone=UTC', { cache: 'no-store' });
                const d = await r.json();
                if (stopped) return;
                if (!d.current || typeof d.current.temperature_2m !== 'number') throw new Error('leer');
                renderWeather(d.current.temperature_2m, apps.wxIconName ? apps.wxIconName(d.current.weather_code) : 'cloud', false);
            } catch (e) {
                try {
                    const r = await fetch('https://api.brightsky.dev/current_weather?lat=' + lat + '&lon=' + lon, { cache: 'no-store' });
                    const d = await r.json();
                    if (stopped || !d.weather || typeof d.weather.temperature !== 'number') return;
                    renderWeather(d.weather.temperature, BS_ICON[d.weather.icon] || 'cloud', false);
                } catch (e2) { /* Anzeige behalten */ }
            }
        }

        // ---------------- Boxen ----------------
        function boxHtml(d) {
            const lv = d.error ? 'info' : (d.level || 'ok');
            let left = '<div class="ko-box-name">' + esc(d.name) + '</div>';
            const detail = [];
            if (d.serial) detail.push(esc(d.serial));
            if (d.phone) detail.push('☎ ' + esc(d.phone));
            if (detail.length) left += '<div class="ko-box-detail">' + detail.join(' · ') + '</div>';
            const warn = d.error || d.warning;
            if (warn) left += '<div class="ko-box-warn">' + esc(warn) + '</div>';

            let right = '';
            if (!d.error) {
                const t = d.temperature || {}, c = d.core || {};
                right += '<div class="ko-mode">' + esc(d.mode || '–') + '</div>';
                right += '<div class="ko-temps">' +
                    '<span><small>Temperatur</small><b>' + esc(t.actual || '–') + '</b> <i>/ ' + esc(t.target || '–') + '</i></span>' +
                    '<span><small>Kern</small><b>' + esc(c.actual || '–') + '</b> <i>/ ' + esc(c.target || '–') + '</i></span>' +
                '</div>';
                const sub = [];
                if (d.schedule) sub.push('Heizzeit ' + esc(d.schedule.start) + '–' + esc(d.schedule.end) + (d.scheduled_remaining_minutes != null ? ' · Planende in ' + duration(d.scheduled_remaining_minutes) : ''));
                sub.push('Stand ' + ago(d.age_seconds));
                right += '<div class="ko-sched">' + sub.join('<br>') + '</div>';
            }

            return '<div class="ko-box lv-' + lv + '"><div>' + left + '</div><div class="ko-box-status">' + right + '</div></div>';
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
            if (b.error) {
                live.textContent = 'Quelle nicht erreichbar'; live.className = 'ko-live bad';
                if (!b.devices || !b.devices.length) { body.innerHTML = '<div class="ko-msg">' + esc(b.error) + '</div>'; return; }
            } else {
                live.textContent = 'live'; live.className = 'ko-live';
            }

            const devs = b.devices || [];
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
            $('[data-box-page]').textContent = pages.length > 1 ? ('Seite ' + (page + 1) + ' / ' + pages.length) : '';
            const alerts = devs.filter(function (d) { return d.level === 'alert'; }).length;
            $('[data-box-foot]').textContent = devs.length + ' Boxen' + (alerts ? ' · ' + alerts + ' Warnung' + (alerts > 1 ? 'en' : '') : '');
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
            let h = '<div class="ko-tour"><div class="ko-tour-top">';
            if (t.departure) h += '<span class="ko-tour-dep">' + esc(t.departure) + '</span>';
            h += '<span class="ko-tour-name">' + esc(t.name) + '</span>';
            if (t.vehicle) h += '<span class="ko-tour-veh">' + esc(t.vehicle) + '</span>';
            h += '</div><div class="ko-tour-driver">';
            if (t.driver) {
                h += '<b>' + esc(t.driver) + '</b>';
                if (t.phone) h += '<span class="ko-phone">' + esc(t.phone) + '</span>';
                if (t.whatsapp_only) h += '<span class="ko-wa">nur über WhatsApp</span>';
                else if (!t.phone && t.phone_note) h += '<span class="ko-note">' + esc(t.phone_note) + '</span>';
            } else {
                h += '<span class="ko-note">' + esc(t.phone_note || 'Kein Fahrer eingetragen') + '</span>';
            }
            h += '</div>';
            if (t.customers && t.customers.length) h += '<div class="ko-tour-cust">' + t.customers.map(esc).join(' · ') + '</div>';
            return h + '</div>';
        }

        /** Alle Touren sollen gleichzeitig sichtbar sein: Spalten/Schrift anpassen, bis es passt. */
        function fitTours() {
            const body = $('[data-tours]'), list = body.querySelector('.ko-tours');
            if (!list) return;
            // Noch nicht sichtbar eingehängt (Player blendet Frames erst ein) → später erneut.
            if (!body.clientHeight) { if (!stopped) setTimeout(fitTours, 500); return; }
            const avail = body.clientHeight - 4;
            for (let cols = 1; cols <= 3; cols++) {
                for (let s = 1; s >= 0.62; s -= 0.04) {
                    list.style.setProperty('--cols', cols);
                    list.style.setProperty('--ks', s.toFixed(2));
                    if (list.scrollHeight <= avail) return;
                    if (cols < 3 && s < 0.8) break;   // lieber eine Spalte mehr als zu klein
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
            live.textContent = 'live'; live.className = 'ko-live';
            const tours = t.tours || [];
            $('[data-tour-foot]').textContent = tours.length + ' Touren heute';
            if (!tours.length) { body.innerHTML = '<div class="ko-msg">Heute keine Touren geplant.</div>'; return; }
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
                     'door_warning_until', 'hide_pickups', 'strip_prefixes', 'driver_aliases', 'whatsapp_only', 'drivers', 'facility_phones']
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
