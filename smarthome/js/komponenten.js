const KOMPONENTEN = [
    { id: "led", piktogramm: "ico-led", name: "Smarte LED", preis: 1.19, ersparnisProzent: 2, beschreibung: "Dimmbar und per App steuerbar" },
    { id: "bewegung", piktogramm: "ico-bewegung", name: "Bewegungsmelder", preis: 1.79, ersparnisProzent: 3, beschreibung: "Erkennt Bewegung und löst Automationen aus" },
    { id: "temperatur", piktogramm: "ico-temperatur", name: "Temperatursensor", preis: 2.99, ersparnisProzent: 5, beschreibung: "Misst die Temperatur in Echtzeit" },
    { id: "steckdose", piktogramm: "ico-steckdose", name: "Smarte Steckdose", preis: 2.39, ersparnisProzent: 4, beschreibung: "Schaltet Geräte und misst deren Stromverbrauch" },
];

if (typeof module !== "undefined") {
    module.exports = KOMPONENTEN;
}
