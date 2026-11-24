# SmartHome

## SmartSave – Smart Living Projekt (Version 2.0, LF11)

Berufsschulprojekt (Anwendungsentwickler, 3. Lehrjahr): eine Smart-Home-Lösung im Abo-Modell,
die sich durch die eingesparten Energiekosten selbst finanziert (Bezug zu SDG 12).

In LF10 entstand ein Klick-Prototyp. In LF11 haben wir ihn refaktoriert und zu einer Anwendung mit
Konten, Datenbank, Bestellungen und Admin-Bereich ausgebaut. Was sich geändert hat, steht in den
**[Releasenotes (CHANGELOG.md)](CHANGELOG.md)**.

### Schnellstart

Voraussetzung: PHP 8.1+ mit `pdo_sqlite`, Composer, für Analyse und Website-Tests zusätzlich Node.js.

```
composer install
php -S localhost:8000 prototype/router.php
```

Danach `http://localhost:8000` öffnen. Ablauf: Startseite → Konfigurator → Übersicht →
„Konto anlegen und bestellen“ → Dashboard. Admin-Rechte: `php prototype/bin/make-admin.php <E-Mail>`.

### Aufbau

| Ordner | Inhalt |
|---|---|
| `smarthome/` | Website (HTML, CSS, JavaScript): Startseite, Konfigurator mit Builder-Pattern, Übersicht |
| `prototype/src/` | Backend: Fachlogik und Datenzugriff (Repositories, Abo-Rechnung, Observer) |
| `prototype/public/` | Seiten der PHP-Anwendung: Login, Registrierung, Dashboard, Kasse, Admin |
| `prototype/router.php` | liefert Website und PHP-Anwendung unter einer Adresse aus |
| `tests/` | PHPUnit (Unit und Integration) und Node-Tests der Website |
| `docs/` | Dokumentation der Sprints, Refactoring, Qualitätssicherung |

### Dokumentation

https://miro.com/app/board/uXjVHuycnkY=/

- [CHANGELOG.md](CHANGELOG.md) – Releasenotes
- [docs/Refactoring_LF11.md](docs/Refactoring_LF11.md) – Refactoring-Methode, Verbesserungen, Metriken, Design Patterns, Bewertung
- [docs/Qualitaetssicherung.md](docs/Qualitaetssicherung.md) – Pipeline, statische Analyse, Testarten, Responsive-Test
- [Projektkonzept.md](Projektkonzept.md) – Unternehmenskonzept/Entrepreneurship
- [docs/Sprint1_Define_Understand.md](docs/Sprint1_Define_Understand.md) – Problem Tree, Persona, Empathy Map, Problem Statement
- [docs/Sprint2_Sketch_Solution.md](docs/Sprint2_Sketch_Solution.md) – Customer Journey, Value Proposition, Business Model Canvas, Backlog
- [docs/Sprint3_Prototype.md](docs/Sprint3_Prototype.md) – SDLC, Prototyp, Deployment-Pipeline (LF10)
- [docs/Sprint4_Features_DesignPattern.md](docs/Sprint4_Features_DesignPattern.md) – Reflexion, Design Pattern (Observer) (LF10)

### Qualitätssicherung

Die Pipeline (`.github/workflows/ci.yml`) führt bei jedem Push statische Analyse (PHPStan, PHP_CodeSniffer, ESLint),
Unit-, Integrations- und Website-Tests aus. Lokal:

```
vendor/bin/phpstan analyse
vendor/bin/phpcs
vendor/bin/phpunit
npm install && npm run lint && npm test
```

## Die Anwendung im Detail: Login, Räume, Geräte, Abo und Bestellungen

Der PHP-Prototyp hat ein Konto-System mit SQLite-Datenbank (eine Datei, kein Server nötig).
Voraussetzung: PHP 8.1+ mit `pdo_sqlite` und `composer install`.

```
php -S localhost:8000 prototype/router.php
```

Der Router liefert die Website (Startseite, Konfigurator, Warenkorb) und den Prototyp unter einer Adresse aus.
Auf der Startseite führt „Login“ oben rechts zum Konto. Danach `http://localhost:8000` öffnen:

1. **Konto anlegen** mit Name, E-Mail, Passwort (min. 8 Zeichen) und eigenem Strompreis (€/kWh).
   Mit dem Häkchen „Beispielwohnung“ entstehen drei Räume mit Dummy-Geräten.
2. **Dashboard „Meine Wohnung“:** Räume anlegen, benennen, in klein/groß ändern und löschen.
   Geräte pro Raum hinzufügen (Name, Watt, Stunden pro Tag), per Klick an- und ausschalten.
   Verbrauch, Kosten und Ersparnis werden mit dem Strompreis des Nutzers neu berechnet.
3. **Zeitpläne:** Pro Gerät „ausschalten um …“ und optional „wieder einschalten um …“ (ohne Einschaltzeit
   rechnen wir mit 8 Stunden Pause). Das Dashboard zeigt die Ersparnis je Gerät und insgesamt.
   Annahmen für die Rechnung:
   - Nur wer **abends** (ab 18 Uhr, Schlaf) oder **morgens** (4 bis 11 Uhr, Arbeit) ausschaltet, spart etwas.
     Tagsüber ausschalten zählt nicht.
   - Es zählen **höchstens 8 Stunden pro Tag**, nie mehr als die Pause selbst und nie mehr, als das Gerät täglich läuft.
   - Steckdose: keine Ersparnis (0 %), sonstige Geräte nur Standby (10 %), Licht und Heizung voll (100 %).

   Die Werte stehen in `prototype/src/Schedule.php` und `prototype/src/DeviceCategory.php`.
4. **Abo und Technik:** Mit dem Konto startet ein Demo-Abo (Grundgebühr 5,99 € pro Monat, 24 Monate).
   Enthalten sind Hub, App, Support, 2× smarte LED, 1× smarte Steckdose und 1× Bewegungsmelder.
   Weitere Technik lässt sich dazubuchen. Ihr Preis ist ein Gesamtpreis über 24 Monate und verteilt sich
   auf die Restlaufzeit (später gebucht = höhere Monatsrate; unter 6 Monaten Restlaufzeit nicht mehr buchbar).
   Das Dashboard zeigt: geschätzte Ersparnis (Verbrauchsanzeige 3,7 % nach dem Feldversuch Intelliekon von Fraunhofer ISE/ISI, plus Zeitpläne, plus Zusatztechnik, höchstens 30 % der Stromkosten) minus Grundgebühr minus Zusatztechnik = effektiv gespart
   pro Monat, dazu bisher Gespartes, Prognose für 24 Monate und „danach gehört dir alles“.
   Ein Demo-Zeitraffer springt in der Laufzeit vor, damit man nicht 24 Monate warten muss.
   Preise und Produkte stehen zentral in `prototype/src/Catalog.php` (Kalkulation als Kommentar).
   Zusatztechnik spart auch: Jedes Produkt hat eine geschätzte Ersparnis in Prozent der bisherigen Stromkosten
   (`saving`, z. B. Heizkörperthermostat 3 %, LED 0,4 %) und eine Höchstzahl sinnvoller Stücke (`maxUseful`).
   Im Shop und an der Kasse steht, ob sich ein Produkt rechnet (Ersparnis gegen Monatspreis), im Dashboard
   geht die Ersparnis in „Effektiv gespart“ ein. Die Prozentwerte sind Schätzungen, keine Messwerte.
5. **Checkout und Bestellungen:** „Dazubuchen“ führt zur Kasse (`checkout.php`): Anzahl (1–5), Lieferadresse und
   der echte Monatspreis für die Restlaufzeit. Als Zahlungsart gibt es nur „Bezahlung vor Ort bei der Lieferung“,
   online wird nichts bezahlt. Eine Bestellung ist `offen`, bis sie geliefert oder vom Nutzer storniert wird
   (stornieren geht nur, solange sie offen ist; die Abo-Gebühr sinkt dann wieder).
   Bei der Registrierung wählt man ein Paket (Starter 5,99 € oder Komfort 8,57 € pro Monat; vorausgewählt nur, wenn man über den Warenkorb der Website kommt)
   und gibt die Lieferadresse an. Daraus entstehen Bestellungen: das Basis-Set (Hub, 2× LED, Steckdose, Bewegungsmelder)
   und beim Komfort-Paket zusätzlich 2× LED, Bewegungsmelder und Temperatursensor. Für ältere Konten ohne Paket gibt es
   `basis-set.php`. Das Basis-Set lässt sich nicht stornieren.
6. **Admin-Bereich für das Team:** `admin.php` zeigt offene, gelieferte und stornierte Bestellungen, eine Packliste
   und je offener Bestellung Kunde und Lieferadresse mit Button „Als geliefert markieren“. Admin wird man nur mit
   Zugriff auf den Rechner (Admins landen nach dem Login direkt hier und sehen sonst nichts, ihre Kundenseiten leiten hierher um): erst normal registrieren, dann im Terminal
   `php prototype/bin/make-admin.php anna@example.de` (mit `--remove` wieder entziehen). Es gibt keinen Standard-Admin.
   Datenschutz: Die Lieferadressen sind personenbezogene Daten und gehören nur ins Testsystem mit Fantasiedaten.
7. **Nur eigene Daten:** Jede Datenbankabfrage ist an die Nutzer-ID gebunden.

Die Datenbank liegt in `prototype/data/smartsave.sqlite` (nicht im Git). Zum Zurücksetzen die Datei löschen.
Sicherheit: Passwörter nur als Hash (`password_hash`), Prepared Statements, CSRF-Token in jedem Formular,
neue Sitzungs-ID nach dem Login. Alle Geräte sind simuliert.
