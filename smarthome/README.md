# SmartSave Website-Prototyp

Dieser Ordner enthält den einfachen Klick-Prototyp für Sprint 3.

## Starten

Im Ordner `smarthome` einen lokalen Webserver starten:

```powershell
python -m http.server 8080
```

Danach `http://localhost:8080` öffnen. Ein Webserver wird verwendet, damit der Warenkorb die
Auswahl zuverlässig über `sessionStorage` übernehmen kann.

## Demo-Ablauf

1. Auf der Startseite ein Paket auswählen.
2. Komponenten und bisherige Energiekosten im Konfigurator anpassen.
3. Die Beispielersparnis mit den Abo-Kosten vergleichen.
4. Auswahl im Warenkorb prüfen und die Testbestellung abschließen.

Die Bestellung sendet keine Daten. Auch die Ersparnis ist nur eine vereinfachte Annahme für den
Schulprototyp.

## Tests

Vom Repository-Hauptordner aus:

```powershell
node --test tests/js/builder.test.js tests/js/html.test.js
```
