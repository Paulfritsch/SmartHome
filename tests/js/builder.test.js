const test = require("node:test");
const assert = require("node:assert/strict");

global.KOMPONENTEN = require("../../smarthome/js/komponenten.js");
const { SmartHomePaketBuilder, PaketKonfigurator } = require("../../smarthome/js/builder.js");

test("Komponenten können hinzugefügt und einzeln entfernt werden", () => {
    const builder = new SmartHomePaketBuilder();
    builder.addKomponente("led").addKomponente("led");

    assert.equal(builder.build().komponenten.length, 2);
    assert.equal(builder.build().monatspreis, 9.98);

    builder.build().removeKomponente("led");
    assert.equal(builder.build().komponenten.length, 1);
});

test("Starter-Paket enthält die erwarteten Komponenten und Preise", () => {
    const builder = new SmartHomePaketBuilder();
    const paket = new PaketKonfigurator(builder).buildStarterPaket();

    assert.deepEqual(paket.komponenten.map(komponente => komponente.id), ["led", "led", "bewegung"]);
    assert.equal(paket.monatspreis, 17.97);
    assert.equal(paket.ersparnisProzent, 7);
    assert.equal(paket.berechneErsparnis(120), 8.4);
});

test("Komfort-Paket enthält die erwarteten Komponenten und Preise", () => {
    const builder = new SmartHomePaketBuilder();
    const paket = new PaketKonfigurator(builder).buildKomfortPaket();

    assert.equal(paket.komponenten.length, 7);
    assert.equal(paket.monatspreis, 42.93);
    assert.equal(paket.ersparnisProzent, 19);
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
