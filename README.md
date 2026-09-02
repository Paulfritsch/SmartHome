# SmartHome

## SmartSave – Smart Living Projekt (LF10)

Berufsschulprojekt (Anwendungsentwickler, 2. Lehrjahr): eine Smart-Home-Lösung im Abo-Modell,
die sich durch die eingesparten Energiekosten selbst finanziert (Bezug zu SDG 12).

### Dokumentation
- [Projektkonzept.md](Projektkonzept.md) – Unternehmenskonzept/Entrepreneurship
- [docs/Sprint1_Define_Understand.md](docs/Sprint1_Define_Understand.md) – Problem Tree, Persona, Empathy Map, Problem Statement
- [docs/Sprint2_Sketch_Solution.md](docs/Sprint2_Sketch_Solution.md) – Customer Journey, Value Proposition, Business Model Canvas, Backlog
- [docs/Sprint3_Prototype.md](docs/Sprint3_Prototype.md) – SDLC, Prototyp, Deployment-Pipeline
- [docs/Sprint4_Features_DesignPattern.md](docs/Sprint4_Features_DesignPattern.md) – Reflexion, Design Pattern (Observer)

### Prototyp
Lauffähiger PHP-Prototyp unter [prototype/](prototype/) (Observer Pattern für Verbrauchsüberwachung).

```
composer install
php -S localhost:8000 -t prototype/public
composer exec phpunit
```
