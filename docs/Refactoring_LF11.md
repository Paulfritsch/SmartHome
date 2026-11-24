# Refactoring in Lernfeld 11

Stand: 24.11.2026. Grundlage ist der Code am Ende von LF10 (Commit `8cb814d`, 10.09.2026).

## 1. Methode

Wir haben nach **Code Smells aus dem Refactoring-Katalog von Martin Fowler** gearbeitet.
Refactoring heißt dabei: die Struktur ändern, das Verhalten gleich lassen.

| Schritt | Was wir tun | Woran wir es merken |
|---|---|---|
| 1. Smell finden | Code mit Abstand lesen, als Kritiker: Was ist schwer zu verstehen oder zu ändern? | Smell mit Namen aus dem Katalog (z. B. *Large Class*, *Magic Number*) |
| 2. Refactoring wählen | passende Technik aus dem Katalog (z. B. *Extract Class*) | Eintrag in der Tabelle unten |
| 3. Absichern | vorhandene Tests laufen lassen, fehlende Tests vorher ergänzen | Tests grün **vor** dem Umbau |
| 4. Umbauen | in kleinen Schritten, nur Struktur ändern | Tests grün **nach** dem Umbau, ein Commit pro Refactoring |
| 5. Messen | Metrik vorher und nachher vergleichen | Spalte „Messgröße“ unten |

Seit LF11 hilft die **statische Analyse** beim ersten Schritt: PHPStan, PHP_CodeSniffer und ESLint melden
Smells automatisch in der Pipeline (siehe [Qualitaetssicherung.md](Qualitaetssicherung.md)).

## 2. Definierte Verbesserungen (13 definiert, 9 umgesetzt)

| # | Smell (Fowler) | Refactoring | Wo | Stand | Messgröße vorher → nachher |
|---|---|---|---|---|---|
| 1 | Large Class / Divergent Change | **Extract Class** `Dashboard` | `index.php` → `src/Dashboard.php` | umgesetzt (`202aba6`) | `index.php` 140 → 105 Zeilen, Rechenlogik jetzt mit 12 Unit-Tests testbar; später von `Dashboard::forUser()` für das echte Dashboard wiederverwendet |
| 2 | Magic Number | **Replace Magic Number with Symbolic Constant** | `Device::DAYS_PER_MONTH`, `SavingsCalculator::DEFAULT_PRICE_PER_KWH`, alle Preise in `Catalog` | umgesetzt (`202aba6`, später `Catalog`) | Zahl `30` und `0.35` je 1× statt verstreut; Preise an einer Stelle in PHP |
| 3 | Mysterious Name | **Rename Method** `getCurrentConsumptionKwh()` → `getPowerKw()` | `Device`, `EnergyMonitor` | umgesetzt (`202aba6`) | Einheit stimmt jetzt mit dem Namen überein (kW, nicht kWh) |
| 4 | Duplicated Code (Validierung) | **Extract Method** `parseNumber()` | `UserRepository`, auch genutzt von `DeviceRepository` | umgesetzt | eine Prüfung für Zahlen-Eingaben statt Kopien in jedem Formular |
| 5 | Duplicated Code (Tests) | **Extract Method** in ein Test-Trait `RejectsInvalidInput` | `tests/` | umgesetzt (`3d69056`) | Testcode −235 / +91 Zeilen, gleiche Anzahl Tests |
| 6 | Mutable Data / falscher Zustand | **Replace Primitive with Object**: Verbrauch je Gerät statt einer Summe | `SavingsCalculator`, `EnergyMonitor` | umgesetzt (`f5a78b4`) | behebt die Doppelzählung (Bug), 1 Regressionstest |
| 7 | Dead Code | **Remove Dead Code** | `tests/phpunit.xml` (doppelt), `.DS_Store` | umgesetzt (`202aba6`) | 2 überflüssige Dateien weniger |
| 8 | fehlende Typen / unsichtbare Abhängigkeiten | **statische Analyse** einführen und Funde beheben | `ci.yml`, alle PHP- und JS-Dateien | umgesetzt (24.11.) | PHPStan Stufe 6: 49 → 0 Funde, PSR-12: 47 → 0 Fehler, ESLint: 11 → 0 Fehler |
| 9 | Duplicated Code / zu viele ähnliche Tests | Tests zusammenfassen (**Consolidate**): gleiche Prüfungen in weniger Testmethoden | `tests/` | umgesetzt (`87baaf4`) | PHP-Testmethoden 132 → 74, Assertions 390 → 376, Zeilen-Abdeckung gleich 93,8 %; Website-Tests 18 → 10 |
| 10 | Long Function / Large Class | **Extract Function** und Vorlagen aufteilen | `public/dashboard.php` | offen | 462 Zeilen in einer Datei, Ziel unter 200 |
| 11 | Duplicated Code (PHP ↔ JS) | **Preise an einer Stelle**: Website liest die Preise aus PHP (z. B. JSON-Datei) | `Catalog.php`, `builder.js`, `komponenten.js` | offen | heute 2 Stellen für Preise, Ziel 1 |
| 12 | mögliche `null`-Werte | PHPStan von Stufe 6 auf 8 anheben | `public/`, `src/` | offen | 18 Funde auf Stufe 8 |
| 13 | Primitive Obsession | Bestellungen als Objekt statt als Array | `OrderRepository` | offen | heute `array<string, mixed>` an 6 Stellen |

## 3. Beispiele aus dem Code

**Extract Class (1):** Vorher stand die ganze Rechnung (Verbrauch, Kosten, Ersparnis) direkt in `index.php`
zwischen HTML. Nachher steckt sie in der Klasse `Dashboard`, die Seite zeigt nur noch an:

```php
// prototype/public/dashboard.php
$dashboard = Dashboard::forUser(
    $user->oldMonthlyCost,
    $user->pricePerKwh,
    array_map(static fn (array $row) => $row['device'], $deviceRows)
);
// ... im HTML nur noch: $dashboard->getMonthlyConsumptionKwh()
```

Nachteil vorher: Rechnen und Anzeigen hingen zusammen, ohne Browser ließ sich nichts testen.

**Replace Magic Number und Rename Method (2, 3):**

```php
// vorher
public function getCurrentConsumptionKwh(): float          // liefert kW, heißt aber kWh
public function getMonthlyConsumptionKwh(int $daysPerMonth = 30): float

// nachher
public const DAYS_PER_MONTH = 30;
public function getPowerKw(): float
public function getMonthlyConsumptionKwh(int $daysPerMonth = self::DAYS_PER_MONTH): float
```

**Verbrauch je Gerät (6):** Vorher wurde bei jedem Einschalten addiert, zweimal einschalten hieß doppelter Verbrauch:

```php
// vorher
$this->totalConsumptionKwh += $device->getMonthlyConsumptionKwh();
// nachher: überschreiben statt addieren, jedes Gerät zählt nur mit seinem aktuellen Zustand
$this->consumptionByDevice[spl_object_id($device)] = $device->getMonthlyConsumptionKwh();
```

## 4. Abgleich mit Design Patterns

| Pattern | Wo | Bezug zum Refactoring |
|---|---|---|
| **Observer** | `Device` (Subjekt), `EnergyMonitor` und `SavingsCalculator` (Beobachter) | Refactoring 6 hat einen Fehler in einem Beobachter behoben, ohne das Gerät zu ändern. Genau dafür ist das Muster da. |
| **Repository** | `UserRepository`, `RoomRepository`, `DeviceRepository`, `OrderRepository`, `BookingRepository` | SQL steckt nur in diesen Klassen. Refactoring 4 (gemeinsame Prüfung) und die Integrationstests setzen hier an. |
| **Builder mit Director** | `smarthome/js/builder.js`: `SmartHomePaketBuilder`, `PaketKonfigurator` | Der Warenkorb nutzt den Director, um zu erkennen, ob die Auswahl dem Komfort-Paket entspricht. |
| Strategy (geplant) | Ersparnis je Geräteart | Heute ein `enum DeviceCategory` mit Faktor. Bei mehr Rechenarten wäre Strategy der nächste Schritt. |

## 5. Messbarkeit: Indikatoren und Metriken

| Indikator | Metrik | LF10 (`8cb814d`) | LF11 (24.11.) |
|---|---|---|---|
| Testbarkeit | Testmethoden PHP | 22 | 74 (376 Assertions); vor dem Zusammenfassen 132 |
| Testbarkeit | Zeilen-Abdeckung `prototype/src` | nicht gemessen | 93,8 % |
| Verständlichkeit | zyklomatische Komplexität je Funktion (PHP_CodeSniffer) | nicht gemessen | Mittel 3,1, Maximum 9, keine über 10 |
| Fehlerfreiheit laut Analyse | Funde PHPStan Stufe 6 | nicht gemessen | 0 (vorher 49) |
| Einheitlicher Stil | Fehler PSR-12 | nicht gemessen | 0 (vorher 47), 145 Warnungen für Zeilen über 120 Zeichen |
| Struktur | Klassen in `prototype/src` | 5 | 19 |
| Größe | längste Datei | `index.php` 140 Zeilen | `dashboard.php` 462 Zeilen (Verbesserung 10) |

Die Komplexität wird in jeder Pipeline geprüft: ab 10 gibt es eine Warnung, ab 20 schlägt der Lauf fehl.

## 6. Kritische Bewertung

**Was gut funktioniert hat:** Die Tests waren unser Sicherheitsnetz. Kein Umbau hat bestehendes Verhalten kaputt
gemacht. Für den Doppelzählungs-Bug gibt es jetzt einen Regressionstest, damit er nicht zurückkommt.
Extract Class hat sich direkt ausgezahlt: Die Klasse `Dashboard` wurde später für das echte Dashboard
mit Konten wiederverwendet. Die Namen aus dem Katalog haben im Team geholfen, über dieselbe Sache zu sprechen.

**Was wir anders machen würden:** Refactoring und neue Funktionen sind teilweise im selben Commit gelandet
(`9bf20c2` mit über 2.100 Zeilen). So ist schwer nachzuvollziehen, was nur Umbau war.
Während wir Funktionen gebaut haben, ist `dashboard.php` auf 462 Zeilen gewachsen. Ein neuer Smell entstand,
während wir alte beseitigt haben. Zeilenzahlen sind nur eine grobe Metrik. Erst mit der Komplexitätsmessung
haben wir gesehen, dass die Funktionen selbst klein sind und das Problem die lange Vorlage ist.

**Fazit:** Die Methode funktioniert gut, wenn Tests vorhanden sind. Sie sagt aber nicht, *wann* man aufhören
sollte, Funktionen zu bauen, und wieder aufräumen muss. Dafür hilft jetzt die statische Analyse in der Pipeline,
weil sie neue Smells meldet, bevor sie sich ansammeln.
