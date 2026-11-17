# SmartHome

## SmartSave – Smart Living Projekt (LF10)

Berufsschulprojekt (Anwendungsentwickler, 3. Lehrjahr): eine Smart-Home-Lösung im Abo-Modell,
die sich durch die eingesparten Energiekosten selbst finanziert (Bezug zu SDG 12).

### Dokumentation

https://miro.com/app/board/uXjVHuycnkY=/

- [Projektkonzept.md](Projektkonzept.md) – Unternehmenskonzept/Entrepreneurship
- [docs/Sprint1_Define_Understand.md](docs/Sprint1_Define_Understand.md) – Problem Tree, Persona, Empathy Map, Problem Statement
- [docs/Sprint2_Sketch_Solution.md](docs/Sprint2_Sketch_Solution.md) – Customer Journey, Value Proposition, Business Model Canvas, Backlog
- [docs/Sprint3_Prototype.md](docs/Sprint3_Prototype.md) – SDLC, Prototyp, Deployment-Pipeline
- [docs/Sprint4_Features_DesignPattern.md](docs/Sprint4_Features_DesignPattern.md) – Reflexion, Design Pattern (Observer)

### Prototyp

Der Sprint-3-Klick-Prototyp liegt unter [smarthome/](smarthome/). Er enthält Startseite,
Paket-Konfigurator, eine vereinfachte Ersparnisrechnung und den Warenkorb.

```
cd smarthome
python -m http.server 8080
```

Danach `http://localhost:8080` öffnen. Der PHP-Prototyp unter [prototype/](prototype/) und seine
PHPUnit-Tests bleiben als Vorarbeit für die Verbrauchsüberwachung und Sprint 4 erhalten.

## PHP-Prototyp mit Login, Räumen und Geräten

Der PHP-Prototyp hat ein Konto-System mit SQLite-Datenbank (eine Datei, kein Server nötig).
Voraussetzung: PHP 8.1+ mit `pdo_sqlite` und `composer install`.

```
php -S localhost:8000 -t prototype/public
```

Danach `http://localhost:8000` öffnen:

1. **Konto anlegen** mit Name, E-Mail, Passwort (min. 8 Zeichen) und eigenem Strompreis (€/kWh).
   Mit dem Häkchen „Beispielwohnung“ entstehen drei Räume mit Dummy-Geräten.
2. **Dashboard „Meine Wohnung“:** Räume anlegen, benennen, in klein/groß ändern und löschen.
   Geräte pro Raum hinzufügen (Name, Watt, Stunden pro Tag), per Klick an- und ausschalten.
   Verbrauch, Kosten und Ersparnis werden mit dem Strompreis des Nutzers neu berechnet.
3. **Zeitpläne:** Pro Gerät „ausschalten um …“ und optional „wieder einschalten um …“ (ohne Einschaltzeit
   bleibt es bis Mitternacht aus). Das Dashboard zeigt die Ersparnis je Gerät und insgesamt. Gerechnet wird
   anteilig zur ausgeschalteten Zeit (Annahme: gleichmäßige Nutzung über den Tag). Je nach Geräteart zählt
   das unterschiedlich: Licht und Heizung 100 %, Steckdose 0 %, sonstige Geräte nur Standby (10 %).
   Die Faktoren stehen in `prototype/src/DeviceCategory.php`.
4. **Nur eigene Daten:** Jede Datenbankabfrage ist an die Nutzer-ID gebunden.

Die Datenbank liegt in `prototype/data/smartsave.sqlite` (nicht im Git). Zum Zurücksetzen die Datei löschen.
Sicherheit: Passwörter nur als Hash (`password_hash`), Prepared Statements, CSRF-Token in jedem Formular,
neue Sitzungs-ID nach dem Login. Alle Geräte sind simuliert.
