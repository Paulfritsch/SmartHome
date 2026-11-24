# Qualitätssicherung im SDLC

Stand: 24.11.2026. Bei jedem Push und jedem Pull Request auf `main` läuft die Pipeline
[`.github/workflows/ci.yml`](../.github/workflows/ci.yml) auf GitHub Actions.
Scheitert ein Schritt, ist der Lauf rot und der Fehler steht im Log.

## Ablauf der Pipeline

| # | Schritt | Werkzeug | Prüft | Bricht ab bei |
|---|---|---|---|---|
| 1 | Abhängigkeiten | `composer validate`, `composer install`, `npm ci` | `composer.json` gültig, Pakete installierbar | jedem Fehler |
| 2 | Syntax | `php -l` | jede PHP-Datei lässt sich parsen | Syntaxfehler |
| 3 | **Statische Analyse PHP** | **PHPStan** Stufe 6 ([`phpstan.neon`](../phpstan.neon)) | Typen, unbekannte Methoden und Variablen, Bedingungen, die immer wahr sind, fehlende Typangaben | jedem Fund |
| 4 | **Code-Stil und Metriken PHP** | **PHP_CodeSniffer** ([`phpcs.xml`](../phpcs.xml)) | PSR-12 (Einrückung, Klammern, Leerzeichen), zyklomatische Komplexität, Verschachtelungstiefe | Fehlern; Warnungen werden nur angezeigt |
| 5 | **Statische Analyse JavaScript** | **ESLint** ([`eslint.config.mjs`](../eslint.config.mjs)) | ungenutzte Variablen, `==` statt `===`, `var`, unsichtbare Leerzeichen | jedem Fund |
| 6 | Unit-Tests | PHPUnit, Suite `Unit` | 65 Tests: einzelne Klassen ohne Datenbank (`Device`, `Schedule`, `Subscription` …) | fehlgeschlagenem Test |
| 7 | Integrationstests | PHPUnit, Suite `Integration` | 71 Tests: Repositories mit echter SQLite-Datenbank im Arbeitsspeicher | fehlgeschlagenem Test |
| 8 | Code Coverage | PHPUnit mit Xdebug | Anteil der getesteten Zeilen in `prototype/src` | – |
| 9 | Website-Tests | `node --test` | 19 Tests: Builder, Preise, Links, Barrierefreiheit, kein Kartenformular im Warenkorb | fehlgeschlagenem Test |
| 10 | Bericht | `$GITHUB_STEP_SUMMARY`, Upload | alle Ergebnisse im Reiter „Summary“, Download `test-reports` | – |

## Statische Analyse: Einstellungen und Begründung

**PHPStan, Stufe 6 von 10.** Ab Stufe 6 verlangt PHPStan Typangaben auch für Arrays. Bei der Einführung
gab es 49 Funde, die wir behoben haben:

- 40× „Variable might not be defined“ in den Seiten unter `public/`: Die Repositories werden in
  `_bootstrap.php` angelegt. Das sieht PHPStan nicht. Jede Seite nennt sie jetzt mit `@var` am Anfang,
  damit ist auch für Menschen sichtbar, welche Objekte die Seite nutzt.
- 2× unnötiger Null-Check (`?->`) in `dashboard.php` und 1× unnötiger Fallback in `ExampleApartment`:
  Der Code war schon sicher, die Prüfung war überflüssig.
- 6× fehlende Typangabe bei den Datenlieferanten der Tests.

Stufe 8 meldet heute noch 18 Funde (mögliche `null`-Werte). Das ist als offene Verbesserung in
[Refactoring_LF11.md](Refactoring_LF11.md) eingetragen.

**PHP_CodeSniffer, PSR-12.** 47 Stil-Fehler wurden mit `phpcbf` automatisch korrigiert (Einrückung, Leerzeilen).
Zwei begründete Ausnahmen stehen in `phpcs.xml`: Zeilenenden regelt Git, und die HTML-Vorlagen in `public/`
nutzen die Kurzschreibweise `if (...):`. 141 Warnungen für Zeilen über 120 Zeichen bleiben sichtbar,
brechen den Lauf aber nicht ab.

**Metriken mit PHP_CodeSniffer.** Zyklomatische Komplexität (Anzahl der Wege durch eine Funktion):
Warnung ab 10, Fehler ab 20. Stand: 85 Funktionen, Mittelwert 3,1, Maximum 9 (`DeviceRepository::add`).

**ESLint.** Empfohlene Regeln plus `eqeqeq`, `prefer-const` und `no-var`. 11 Funde wurden behoben,
z. B. ungenutzte Variablen in `catch`-Blöcken und ein unsichtbares geschütztes Leerzeichen vor dem Euro-Zeichen,
das jetzt als ` ` im Code steht.

## Testarten

| Art | Wo | Beispiel |
|---|---|---|
| Unit | `tests/DeviceTest.php`, `ScheduleTest.php`, `SubscriptionTest.php` … | Ein Zeitplan rechnet höchstens 8 Stunden Ersparnis pro Tag an |
| Integration | `tests/*RepositoryTest.php`, `DeviceScheduleTest.php` | Ein Nutzer kann fremde Geräte nicht ändern oder löschen (echte SQL-Abfragen) |
| Website | `tests/js/builder.test.js`, `html.test.js` | Komfort-Paket kostet 8,57 €, alle Links der Seiten existieren |
| E2E | noch nicht in der Pipeline | Ablauf Konfigurator → Registrierung → Dashboard wurde am 24.11. von Hand im Browser getestet |

## Responsive-Test

Am 24.11.2026 im Browser bei drei Breiten geprüft: Smartphone (375 px), Tablet (768 px), Desktop (1280 px).
Geprüft wurde, dass keine Seite seitlich scrollt (`scrollWidth` ≤ Fensterbreite) und alle Inhalte lesbar sind.

| Seite | 375 px | 768 px | 1280 px |
|---|---|---|---|
| Startseite `index.html` | ok | ok | ok |
| Konfigurator | ok | ok | ok |
| Warenkorb | ok ([Bild](responsive/warenkorb_375.jpg)) | ok ([Bild](responsive/warenkorb_768.jpg)) | ok ([Bild](responsive/warenkorb_1280.jpg)) |
| Dashboard | ok ([Bild](responsive/dashboard_375.jpg)) | behoben, dann ok ([Bild](responsive/dashboard_768.png)) | ok ([Bild](responsive/dashboard_1280.jpg)) |
| Kasse `checkout.php` | ok | ok | – |
| Admin `admin.php` | ok | – | – |

Gefunden und behoben: Auf dem Tablet blieb im Dashboard neben der fünften Kennzahl eine leere graue Fläche.
Die Kacheln nutzen jetzt `flex-wrap`, die letzte Zeile wird ganz ausgefüllt.

## Lokal ausführen

```bash
composer install
npm install
vendor/bin/phpstan analyse          # statische Analyse PHP
vendor/bin/phpcs                    # Code-Stil und Metriken (vendor/bin/phpcbf korrigiert automatisch)
npm run lint                        # ESLint
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration
npm test                            # Website-Tests
```

Unter Windows läuft PHPStan schneller mit `--debug` (ohne parallele Prozesse).
