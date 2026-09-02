# Sprint 3: "Prototype your solution"

Projekt: **SmartSave** – Smart Home für alle

---

## 1. Sprintplanung

**Scrum-Rollen im Team:**
- **Product Owner:** verantwortlich für Backlog-Priorisierung, Ansprechpartner für Anforderungen
- **Scrum Master:** moderiert Dailys, Retros, entfernt Hindernisse
- **Entwicklungsteam:** setzt Backlog-Items technisch um

**Planungstool:** GitLab/GitHub Issues + Board (Spalten: Backlog, To Do, In Progress, Review, Done)

**Sprintdauer:** 1 Woche pro Sprint, tägliche Dailys (Stand-up, 15 Min, auf Englisch)

---

## 2. Software Development Life Cycle (SDLC) – Skizze

```mermaid
flowchart LR
    A[Requirements: Backlog Item] --> B[Planning: Sprint Planning / Estimation]
    B --> C[Design: Kurze technische Skizze / UML bei Bedarf]
    C --> D[Implementation: Feature-Branch + Code]
    D --> E[Quality Assurance: Unit Tests + Code Review]
    E --> F[CI: Automatischer Build + Testlauf]
    F --> G[Deployment: Test-/Staging-Umgebung]
    G --> H[Review/Demo: Sprint Demo]
    H --> I[Retrospective]
    I --> A
```

### Wesentliche Bestandteile
- **Requirements & Backlog:** User Stories mit MoSCoW-Priorisierung (siehe Sprint 2)
- **Priorisierung:** Must-have zuerst, danach Should/Could – abhängig von Kapazität pro Sprint
- **Qualitätssicherung:** Code Review (Pull Request), automatisierte Unit-Tests, manuelles Testen der Demo vor Sprintende
- **Deployment:** Zunächst manuelles Deployment auf lokale/Test-Umgebung (XAMPP), danach automatisiert über CI-Pipeline in eine Staging-Umgebung

---

## 3. Erster Prototyp (Low-Fidelity → funktionsfähig)

Umgesetzt als einfache PHP-Webanwendung (`/prototype`), die den Kernprozess von SmartSave demonstriert:

- Eingabe des aktuellen monatlichen Energieverbrauchs
- Simulierte Geräte (Steckdosen/Thermostat), die ein-/ausgeschaltet werden können
- Live-Berechnung der geschätzten Einsparung und der Amortisation über die Abo-Laufzeit
- Nutzung des **Observer Pattern** zur Verbrauchsüberwachung (siehe [Sprint4_Features_DesignPattern.md](./Sprint4_Features_DesignPattern.md))

Siehe Code unter [prototype/](../prototype/).

---

## 4. Deployment Pipeline – erster Einstieg

Datei: [.github/workflows/ci.yml](../.github/workflows/ci.yml)

Aktueller Umfang (Must-have für Sprint 3/4):
1. Checkout des Codes
2. PHP-Setup
3. Abhängigkeiten installieren (Composer)
4. Syntax-Check (`php -l`)
5. Automatisierte Unit-Tests (PHPUnit)

**Nächster Ausbauschritt (Sprint 4):** Deployment-Schritt in eine Testumgebung, Testabdeckungsmessung, ggf. Continuous Deployment.

---

## 5. Peer-to-Peer Feedback (Platzhalter)

| Feedback von Team ___ | Anmerkung | Reaktion / Anpassung |
|---|---|---|
|  |  |  |

## Retrospektive Sprint 3 (Platzhalter)
- **Was lief gut:**
- **Was sollte verbessert werden:**
- **Maßnahmen für Sprint 4:**
