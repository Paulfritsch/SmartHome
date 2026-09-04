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
