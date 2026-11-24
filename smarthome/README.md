# SmartSave Website-Prototyp

Dieser Ordner enthält die Website: Startseite, Konfigurator und Übersicht (Warenkorb).

## Starten

Im Ordner `smarthome` einen lokalen Webserver starten:

```bash
python3 -m http.server 8080
```

Mit PHP geht es genauso: `php -S localhost:8080`

Danach `http://localhost:8080` öffnen. Der Link „Login“ oben rechts funktioniert nur, wenn die Website zusammen mit dem PHP-Prototyp läuft: `php -S localhost:8000 prototype/router.php` im Hauptordner des Repos. Das gilt auch für „Konto anlegen und bestellen“ in der Übersicht.
Ein Webserver wird verwendet, damit die Übersicht
die Auswahl zuverlässig über `sessionStorage` übernehmen kann.

## Demo-Ablauf

1. Auf der Startseite ein Paket auswählen oder den Konfigurator öffnen.
2. Komponenten und monatliche Energiekosten im Konfigurator anpassen.
3. Abo-Gebühr und geschätzte Ersparnis vergleichen. Kostet das Abo mehr, als es spart,
   zeigt die Seite das offen an.
4. Auswahl in der Übersicht prüfen und „Konto anlegen und bestellen“ wählen. Die Registrierung der
   PHP-Anwendung übernimmt Paket (Starter oder Komfort) und Energiekosten. Bezahlt wird bei der Lieferung,
   die Website fragt keine Zahlungsdaten ab.

Die Ersparnis ist eine vereinfachte Annahme für das Schulprojekt, keine Energieberatung.

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
npm test        # oder: node --test tests/js/builder.test.js tests/js/html.test.js
npm run lint    # statische Analyse mit ESLint (vorher einmal npm install)
```
