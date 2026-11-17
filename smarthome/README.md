# SmartSave Website-Prototyp

Dieser Ordner enthält den Klick-Prototyp der Website: Startseite, Konfigurator und Bezahlung.

## Starten

Im Ordner `smarthome` einen lokalen Webserver starten:

```bash
python3 -m http.server 8080
```

Mit PHP geht es genauso: `php -S localhost:8080`

Danach `http://localhost:8080` öffnen. Der Link „Login“ oben rechts funktioniert nur, wenn die Website zusammen mit dem PHP-Prototyp läuft: `php -S localhost:8000 prototype/router.php` im Hauptordner des Repos. Ein Webserver wird verwendet, damit die Bezahlseite
die Auswahl zuverlässig über `sessionStorage` übernehmen kann.

## Demo-Ablauf

1. Auf der Startseite ein Paket auswählen oder den Konfigurator öffnen.
2. Komponenten und monatliche Energiekosten im Konfigurator anpassen.
3. Abo-Gebühr und geschätzte Ersparnis vergleichen. Kostet das Abo mehr, als es spart,
   zeigt die Seite das offen an.
4. Auswahl auf der Bezahlseite prüfen und die Testbestellung abschließen.

Die Bestellung sendet und speichert keine Daten. Die Ersparnis ist eine vereinfachte Annahme
für das Schulprojekt, keine Energieberatung.

## Gestaltung

- **Farben:** Kobaltblau als Fläche, Gelb nur als Textmarker für das Wichtige, dunkle Tinte für Text.
- **Schrift:** Archivo (variable Schrift mit Breiten- und Gewichtsachse). Die Dateien liegen lokal in
  `fonts/`, es wird nichts bei Google geladen. Lizenz: SIL Open Font License, siehe
  `fonts/Archivo-OFL-Lizenz.txt`.
- **Piktogramme:** Eigene SVG-Zeichnungen, direkt in den HTML-Seiten definiert (`<symbol id="ico-...">`).
- **Besonderheit:** Die 24-Monats-Leiste auf der Startseite zeigt die Vertragslaufzeit.
  Die Animation wird bei aktivierter Einstellung „Bewegung reduzieren“ abgeschaltet.

## Tests

Vom Repository-Hauptordner aus:

```bash
node --test tests/js/builder.test.js tests/js/html.test.js
```
