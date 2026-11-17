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
6. **Admin-Bereich für das Team:** `admin.php` zeigt offene, gelieferte und stornierte Bestellungen, eine Packliste
   und je offener Bestellung Kunde und Lieferadresse mit Button „Als geliefert markieren“. Admin wird man nur mit
   Zugriff auf den Rechner: erst normal registrieren, dann im Terminal
   `php prototype/bin/make-admin.php anna@example.de` (mit `--remove` wieder entziehen). Es gibt keinen Standard-Admin.
   Datenschutz: Die Lieferadressen sind personenbezogene Daten und gehören nur ins Testsystem mit Fantasiedaten.
7. **Nur eigene Daten:** Jede Datenbankabfrage ist an die Nutzer-ID gebunden.

Die Datenbank liegt in `prototype/data/smartsave.sqlite` (nicht im Git). Zum Zurücksetzen die Datei löschen.
Sicherheit: Passwörter nur als Hash (`password_hash`), Prepared Statements, CSRF-Token in jedem Formular,
neue Sitzungs-ID nach dem Login. Alle Geräte sind simuliert.
