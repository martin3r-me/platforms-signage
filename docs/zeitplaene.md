---
title: Zeitpläne
order: 4
---

# 🗓️ Zeitpläne

Mit Zeitplänen bestimmst du, **wann** welche Wiedergabeliste läuft. Ohne Zeitplan zeigt ein Bildschirm einfach seine Standard-Wiedergabeliste.

---

## Aufbau: Plan und Regeln

Ein **Zeitplan** ist ein benannter, wiederverwendbarer Plan (z. B. „Kita Mo-Fr"). Er enthält eine oder mehrere **Regeln**. Jede Regel legt fest:

- **Wochentage** (Mo–So)
- **Von–Bis** (Uhrzeit) — oder **Ganztägig**
- die **Wiedergabeliste** (visuell), optional zusätzlich eine **Musik-Liste**
- eine **Priorität** (höher gewinnt bei Überschneidung innerhalb eines Plans)

Der Wochenplan-Editor zeigt alle Regeln als farbige Blöcke im Wochenraster — Klick in den Kalender legt eine neue Regel vorausgefüllt an.

---

## Ganztägig & Mitternacht

- **Ganztägig**-Schalter: speichert das Fenster als 00:00–00:00 = voller Tag (24 h).
- Ein Ende von **00:00** bedeutet „Tagesende" — z. B. läuft `10:30–00:00` von 10:30 Uhr bis Mitternacht.
- Fenster, die über Mitternacht laufen (z. B. 22:00–06:00), werden korrekt auf den Folgetag fortgeführt.
- Aneinandergrenzende Fenster (eines endet 10:30, das nächste beginnt 10:30) übergeben **sauber** — kein Konflikt, keine Lücke.

---

## Mehrere Pläne pro Bildschirm

Einem Bildschirm können **mehrere Zeitpläne** zugewiesen werden, die ineinandergreifen — z. B. „Kita Mo-Fr" (morgens), „Event Mo-Fr" (restlicher Tag) und je ein Plan für Samstag/Sonntag. Der Player sammelt die aktiven Regeln aller zugewiesenen Pläne und wählt die passende.

### Keine Überschneidungen

Mehrere zugewiesene Pläne dürfen sich **zeitlich nicht überschneiden** (getrennt geprüft für Anzeige und Musik). Beim Speichern eines Bildschirms erscheint sonst eine **Fehlermeldung**, die genau benennt, welche Pläne sich an welchem Tag überlappen. So lassen sich Pläne gefahrlos kombinieren.

---

## Standard-Wiedergabeliste als Fallback

Greift zu einem Zeitpunkt **keine** Regel, fällt der Bildschirm auf seine **Standard-Wiedergabeliste** zurück. Decken deine Zeitpläne den ganzen Tag ab, wird die Standard-Liste nie gebraucht.

---

## „Was läuft gerade?"

Auf der Bildschirm-Seite zeigt der Button **„Was läuft gerade?"** für die aktuelle Uhrzeit (in der Zeitzone des Bildschirms) den **aktiven Zeitplan** und die laufende Liste — getrennt für Anzeige und Musik. Ideal, um eine Konfiguration schnell zu prüfen.
