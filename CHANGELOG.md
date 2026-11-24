# Releasenotes SmartSave

Alle wichtigen Änderungen am Projekt. Die Versionen folgen [Semantic Versioning](https://semver.org/lang/de/):
Hauptversion bei großen fachlichen Änderungen, Nebenversion bei neuen Funktionen, Patch bei Fehlerbehebungen.

## [2.0.0] – 26.11.2026 (Lernfeld 11)

Aus dem Klick-Prototyp aus LF10 wird eine Anwendung mit Konten, Datenbank und echtem Bestellablauf.
Der Geschäftsprozess „Abo abschließen und Technik liefern“ läuft jetzt vollständig durch die Software:
Kunde bestellt, Team liefert, Kunde sieht seine Ersparnis.

### Neu

- **Konten und Datenbank:** Registrierung, Login und Logout mit SQLite. Passwörter nur als Hash,
  Prepared Statements, CSRF-Schutz in jedem Formular, neue Sitzungs-ID nach dem Login.
- **Räume und Geräte pro Konto:** anlegen, umbenennen, löschen, an- und ausschalten.
  Jede Abfrage ist an die Nutzer-ID gebunden, niemand sieht fremde Daten.
- **Zeitpläne:** pro Gerät „ausschalten um …“ und optional „einschalten um …“. Ersparnis nur abends
  (ab 18 Uhr) oder morgens (4 bis 11 Uhr), höchstens 8 Stunden am Tag, abhängig von der Geräteart.
- **Abo-Modell im Dashboard:** Grundgebühr, Zusatztechnik, Ersparnis (Verbrauchsanzeige 3,7 % nach
  Fraunhofer-Feldversuch Intelliekon, plus Zeitpläne, plus Zusatztechnik, höchstens 30 % der Stromkosten),
  „effektiv gespart“, Prognose über 24 Monate und ein Zeitraffer für die Demo.
- **Pakete Starter (5,99 €) und Komfort (8,57 €)** bei der Registrierung, mit Lieferadresse.
- **Checkout für Zusatztechnik:** Gesamtpreis über die Restlaufzeit verteilt, Bezahlung vor Ort bei der Lieferung.
  Offene Bestellungen kann der Kunde stornieren.
- **Admin-Bereich für das Team:** offene, gelieferte und stornierte Bestellungen, Packliste,
  Lieferadresse und Button „Als geliefert markieren“. Admin wird man nur über `prototype/bin/make-admin.php`.
- **Website und PHP-Anwendung unter einer Adresse:** `php -S localhost:8000 prototype/router.php`.
- **Warenkorb der Website führt zur Registrierung:** Das gewählte Paket (Starter/Komfort) und die
  Energiekosten werden vorausgewählt. Die Kartendaten-Attrappe und die „Testbestellung“ aus LF10 sind entfernt.

### Geändert

- **Preismodell:** statt 4,99–7,99 € je Komponente jetzt Grundgebühr plus Zusatztechnik
  (Gesamtpreis über 24 Monate, auf die Restlaufzeit verteilt).
- **Refactoring** nach Code Smells von Martin Fowler, siehe [docs/Refactoring_LF11.md](docs/Refactoring_LF11.md):
  `Dashboard`-Klasse aus `index.php` herausgelöst, Magic Numbers als Konstanten, `getCurrentConsumptionKwh()`
  heißt jetzt `getPowerKw()`, gemeinsame Prüfungen in Hilfsmethoden und einem Test-Trait.
- **Dashboard-Kacheln** füllen auf Tablets die letzte Zeile ganz aus (keine leere Fläche mehr).

### Behoben

- `SavingsCalculator` hat den Verbrauch eines Geräts bei jedem Einschalten erneut addiert (Doppelzählung).
  Jetzt wird der Wert pro Gerät gespeichert und überschrieben.

### Qualitätssicherung

- **Statische Analyse in der Pipeline:** PHPStan (Stufe 6), PHP_CodeSniffer (PSR-12 plus Komplexitäts-Metrik)
  und ESLint für die Website. Details in [docs/Qualitaetssicherung.md](docs/Qualitaetssicherung.md).
- **Tests getrennt nach Art:** 65 Unit-Tests, 71 Integrationstests gegen SQLite, 19 Website-Tests.
  Code Coverage 93,8 % der Zeilen in `prototype/src`.
- **Testbericht** mit allen Testnamen im Reiter „Summary“ des Pipeline-Laufs und als Download.
- **Responsive-Test** bei 375, 768 und 1280 Pixel Breite dokumentiert.

### Bekannte Einschränkungen

- Alle Geräte sind simuliert, es gibt keine echte Gerätesteuerung.
- Es gibt keine REST-API. Die Seiten werden serverseitig mit PHP erzeugt.
- `dashboard.php` ist mit über 460 Zeilen zu lang. Das Aufteilen ist als nächstes Refactoring geplant.
- Preise stehen in PHP (`Catalog.php`) und für die Website in JavaScript (`builder.js`, `komponenten.js`).
  Ein Test vergleicht nur die Startseite mit dem JavaScript, nicht mit PHP. Gemeinsam gepflegt werden sie noch nicht.

## [1.0.0] – 10.09.2026 (Lernfeld 10)

Erster Prototyp aus dem Design-Thinking-Projekt.

- Website mit Startseite, Paket-Konfigurator (Builder-Pattern) und Warenkorb mit Testbestellung (ohne Datenversand).
- PHP-Prototyp mit drei festen Beispielgeräten und Observer-Pattern für die Verbrauchsanzeige.
- PHPUnit- und Node-Tests in einer GitHub-Actions-Pipeline.
