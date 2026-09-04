const KOMPONENTEN = [
    { id: "led", name: "Smarte LED", preis: 4.99, ersparnisProzent: 2, beschreibung: "Dimmbar und per App steuerbar" },
    { id: "bewegung", name: "Bewegungsmelder", preis: 7.99, ersparnisProzent: 3, beschreibung: "Erkennt Bewegung und löst Automationen aus" },
    { id: "temperatur", name: "Temperatursensor", preis: 6.99, ersparnisProzent: 5, beschreibung: "Misst die Temperatur in Echtzeit" },
    { id: "steckdose", name: "Smarte Steckdose", preis: 5.99, ersparnisProzent: 4, beschreibung: "Schaltet Geräte und misst deren Stromverbrauch" },
];

if (typeof module !== "undefined") {
    module.exports = KOMPONENTEN;
}
