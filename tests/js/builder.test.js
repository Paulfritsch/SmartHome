const test = require("node:test");
const assert = require("node:assert/strict");

global.KOMPONENTEN = require("../../smarthome/js/komponenten.js");
const { SmartHomePaketBuilder, PaketKonfigurator, MAX_ERSPARNIS_PROZENT } = require("../../smarthome/js/builder.js");

test("Komponenten können hinzugefügt und einzeln entfernt werden", () => {
    const builder = new SmartHomePaketBuilder();
    builder.addKomponente("led").addKomponente("led");

    assert.equal(builder.build().komponenten.length, 2);
    assert.equal(builder.build().monatspreis, 6.99); // 5,99 € Basis + 2 × 0,50 €

    builder.build().removeKomponente("led");
    assert.equal(builder.build().komponenten.length, 1);
});

test("Starter-Paket enthält die erwarteten Komponenten und Preise", () => {
    const builder = new SmartHomePaketBuilder();
    const paket = new PaketKonfigurator(builder).buildStarterPaket();

    assert.deepEqual(paket.komponenten, []); // nur das Basis-Abo
    assert.equal(paket.monatspreis, 5.99);
    assert.equal(paket.ersparnisProzent, 3.7); // Verbrauchsanzeige durch Hub und App
    assert.equal(Math.round(paket.berechneErsparnis(120) * 100) / 100, 4.44);
});

test("Komfort-Paket enthält die erwarteten Komponenten und Preise", () => {
    const builder = new SmartHomePaketBuilder();
    const paket = new PaketKonfigurator(builder).buildKomfortPaket();

    assert.equal(paket.komponenten.length, 4); // zusaetzlich zum Basis-Abo
    assert.equal(paket.monatspreis, 8.57);
    assert.equal(paket.ersparnisProzent, 6.3); // 3,7 + 2 LED (0,8) + Bewegungsmelder (1) + Temperatursensor (0,8)
});

test("Stücke über der sinnvollen Anzahl bringen keine weitere Ersparnis", () => {
    const builder = new SmartHomePaketBuilder();
    for (let i = 0; i < 9; i++) builder.addKomponente("thermostat");

    assert.equal(builder.build().ersparnisProzent, 15.7); // 3,7 + 4 × 3
    assert.equal(builder.build().berechneErsparnis(100), 15.7);
});

test("Alle Komponenten in sinnvoller Menge bleiben unter der Obergrenze von 30 Prozent", () => {
    const builder = new SmartHomePaketBuilder();
    for (const k of KOMPONENTEN) for (let i = 0; i < k.maxSinnvoll; i++) builder.addKomponente(k.id);

    assert.ok(builder.build().ersparnisProzent < MAX_ERSPARNIS_PROZENT);
});

test("Unbekannte Komponenten werden abgelehnt", () => {
    const builder = new SmartHomePaketBuilder();
    assert.throws(() => builder.addKomponente("unbekannt"), /Unbekannte Komponente/);
});

test("bei 250 € Energiekosten deckt die Ersparnis beide Fertigpakete", () => {
  for (const bauen of ["buildStarterPaket", "buildKomfortPaket"]) {
    const paket = new PaketKonfigurator(new SmartHomePaketBuilder())[bauen]();
    assert.ok(paket.berechneErsparnis(250) > paket.monatspreis, bauen);
  }
});

test("Thermostat, Fenster- und Temperatursensor rechnen sich ab etwa 75 € Energiekosten", () => {
  for (const id of ["thermostat", "fenster", "temperatur"]) {
    const k = KOMPONENTEN.find(komponente => komponente.id === id);
    const schwelle = k.preis / (k.ersparnisProzent / 100);
    assert.ok(schwelle <= 75, `${k.id}: ${schwelle.toFixed(1)} €`);
  }
});

test("Jede Komponente hat Preis, Ersparnis und Stückgrenze", () => {
  for (const k of KOMPONENTEN) {
    assert.ok(k.preis > 0 && k.ersparnisProzent > 0 && k.maxSinnvoll >= 1, k.id);
  }
});
