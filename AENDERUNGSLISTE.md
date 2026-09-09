# Änderungsliste - Sprint 3

Stand: 04.09.2026

## Ausgangslage

Die Startseite und der Konfigurator waren optisch begonnen. Der Konfigurator konnte wegen
falscher Scriptpfade und mehrerer Schreibfehler nicht ausgeführt werden. Die Warenkorbdatei war
leer. Außerdem wurden die Namen `SmartSave` und `Nest & Now` sowie unterschiedliche Aussagen
zur Vertragslaufzeit verwendet.

## Durchgeführte Änderungen

### Website

- Scriptpfade im Konfigurator auf den Ordner `js/` korrigiert.
- Schreibfehler in `SmartHomePaketBuilder`, `komponenten` und den Hinzufügen-/Entfernen-Methoden
  behoben.
- Vorlagen für Starter- und Komfortpaket funktionsfähig gemacht.
- Komponenten um einfache Schätzwerte für die Energieersparnis ergänzt.
- Eingabe der bisherigen monatlichen Energiekosten und Vergleich mit den Abo-Kosten ergänzt.
- Startseite auf den Namen SmartSave, 24 Monate Laufzeit und Eigentumsübergang vereinheitlicht.
- Paketpreise der Startseite an die tatsächliche Summe der Komponenten angeglichen.
- Leere Warenkorbseite zu einer funktionsfähigen Zusammenfassung ausgebaut.
- Testabschluss ergänzt; es werden ausdrücklich keine Daten versendet.
- Kleine Anpassungen für schmale Bildschirme und klare Hinweise zum Prototyp ergänzt.

### Qualitätssicherung und Pipeline

- Automatische Tests für Builder, Vorlagen, Preise, Ersparnisgrenze und Fehlerfälle ergänzt.
- Test ergänzt, der lokale HTML-, CSS- und JavaScript-Verweise kontrolliert.
- GitHub-Actions-Pipeline um Node.js und die Tests des Website-Prototyps erweitert.

### Dokumentation

- Sprint-3-Plan, Status, Definition of Done, SDLC, Pipeline und Demo-Ablauf aktualisiert.
- Haupt-README auf den neuen Sprint-3-Prototyp angepasst.
- Eigene Start- und Testanleitung unter `smarthome/README.md` ergänzt.
- Noch offene fachliche und organisatorische Arbeiten in `OFFENE_TODOS.md` gesammelt.

## Bewusst nicht umgesetzt

Echte Bestellung, Zahlung, Login, Datenbank, reale Verbrauchsdaten und Deployment in eine
öffentliche Testumgebung sind nicht Teil dieses Low-Fidelity-Prototyps. Diese Punkte würden den
Rahmen von Sprint 3 überschreiten.
