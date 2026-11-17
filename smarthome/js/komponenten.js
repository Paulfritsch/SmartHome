// Preise (pro Stueck und Monat bei 24 Monaten Laufzeit), Ersparnis und Stueckgrenze wie im PHP-Prototyp
// (prototype/src/Catalog.php). ersparnisProzent: geschaetzter Anteil der monatlichen Energiekosten pro Stueck.
// maxSinnvoll: ab dieser Stueckzahl bringt ein weiteres Stueck keine Ersparnis mehr.
const KOMPONENTEN = [
    { id: "led", piktogramm: "ico-led", name: "Smarte LED", preis: 0.50, ersparnisProzent: 0.4, maxSinnvoll: 4, beschreibung: "Dimmbar und per App steuerbar" },
    { id: "bewegung", piktogramm: "ico-bewegung", name: "Bewegungsmelder", preis: 1.00, ersparnisProzent: 1, maxSinnvoll: 3, beschreibung: "Erkennt Bewegung und löst Automationen aus" },
    { id: "temperatur", piktogramm: "ico-temperatur", name: "Temperatursensor", preis: 0.58, ersparnisProzent: 0.8, maxSinnvoll: 3, beschreibung: "Misst die Temperatur in Echtzeit" },
    { id: "steckdose", piktogramm: "ico-steckdose", name: "Smarte Steckdose", preis: 0.83, ersparnisProzent: 0.5, maxSinnvoll: 2, beschreibung: "Schaltet Geräte und misst deren Stromverbrauch" },
    { id: "fenster", piktogramm: "ico-fenster", name: "Tür-/Fenstersensor", preis: 0.67, ersparnisProzent: 1, maxSinnvoll: 4, beschreibung: "Meldet offene Fenster, die Heizung kann reagieren" },
    { id: "thermostat", piktogramm: "ico-thermostat", name: "Heizkörperthermostat", preis: 2.00, ersparnisProzent: 3, maxSinnvoll: 4, beschreibung: "Regelt die Heizung nach Zeitplan" },
];

if (typeof module !== "undefined") {
    module.exports = KOMPONENTEN;
}
