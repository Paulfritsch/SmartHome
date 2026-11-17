const test = require("node:test");
const assert = require("node:assert/strict");

global.KOMPONENTEN = require("../../smarthome/js/komponenten.js");
const { SmartHomePaketBuilder, PaketKonfigurator } = require("../../smarthome/js/builder.js");

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
    assert.equal(paket.ersparnisProzent, 11); // 2 LED, Steckdose, Bewegungsmelder im Basis-Abo
    assert.equal(paket.berechneErsparnis(120), 13.2);
});

test("Komfort-Paket enthält die erwarteten Komponenten und Preise", () => {
    const builder = new SmartHomePaketBuilder();
    const paket = new PaketKonfigurator(builder).buildKomfortPaket();

    assert.equal(paket.komponenten.length, 4); // zusaetzlich zum Basis-Abo
    assert.equal(paket.monatspreis, 8.57);
    assert.equal(paket.ersparnisProzent, 23);
});

test("Ersparnis wird bei 30 Prozent gedeckelt", () => {
    const builder = new SmartHomePaketBuilder();
    for (let i = 0; i < 7; i++) builder.addKomponente("temperatur");

    assert.equal(builder.build().ersparnisProzent, 30);
    assert.equal(builder.build().berechneErsparnis(100), 30);
});

test("Unbekannte Komponenten werden abgelehnt", () => {
    const builder = new SmartHomePaketBuilder();
    assert.throws(() => builder.addKomponente("unbekannt"), /Unbekannte Komponente/);
});

test("bei 120 € Energiekosten sparen beide Fertigpakete mehr, als sie kosten", () => {
  for (const bauen of ["buildStarterPaket", "buildKomfortPaket"]) {
    const paket = new PaketKonfigurator(new SmartHomePaketBuilder())[bauen]();
    assert.ok(paket.berechneErsparnis(120) > paket.monatspreis, bauen);
  }
});

test("jede Komponente rechnet sich ab etwa 60 € Energiekosten im Monat", () => {
  for (const k of KOMPONENTEN) {
    const schwelle = k.preis / (k.ersparnisProzent / 100);
    assert.ok(schwelle <= 62, `${k.id}: ${schwelle.toFixed(1)} €`);
  }
});
