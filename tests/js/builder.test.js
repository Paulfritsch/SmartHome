const test = require("node:test");
const assert = require("node:assert/strict");

global.KOMPONENTEN = require("../../smarthome/js/komponenten.js");
const { SmartHomePaketBuilder, PaketKonfigurator, MAX_ERSPARNIS_PROZENT } = require("../../smarthome/js/builder.js");

test("Komponenten können hinzugefügt und einzeln entfernt werden, unbekannte werden abgelehnt", () => {
    const builder = new SmartHomePaketBuilder();
    builder.addKomponente("led").addKomponente("led");

    assert.equal(builder.build().komponenten.length, 2);
    assert.equal(builder.build().monatspreis, 6.99); // 5,99 € Basis + 2 × 0,50 €

    builder.build().removeKomponente("led");
    assert.equal(builder.build().komponenten.length, 1);

    assert.throws(() => builder.addKomponente("unbekannt"), /Unbekannte Komponente/);
});

test("Fertigpakete enthalten die erwarteten Komponenten und Preise und decken sich bei 250 € Energiekosten", () => {
    const starter = new PaketKonfigurator(new SmartHomePaketBuilder()).buildStarterPaket();
    assert.deepEqual(starter.komponenten, []); // nur das Basis-Abo
    assert.equal(starter.monatspreis, 5.99);
    assert.equal(starter.ersparnisProzent, 3.7); // Verbrauchsanzeige durch Hub und App
    assert.equal(Math.round(starter.berechneErsparnis(120) * 100) / 100, 4.44);

    const komfort = new PaketKonfigurator(new SmartHomePaketBuilder()).buildKomfortPaket();
    assert.equal(komfort.komponenten.length, 4); // zusätzlich zum Basis-Abo
    assert.equal(komfort.monatspreis, 8.57);
    assert.equal(komfort.ersparnisProzent, 6.3); // 3,7 + 2 LED (0,8) + Bewegungsmelder (1) + Temperatursensor (0,8)

    for (const paket of [starter, komfort]) {
        assert.ok(paket.berechneErsparnis(250) > paket.monatspreis);
    }
});

test("Stücke über der sinnvollen Anzahl bringen keine weitere Ersparnis", () => {
    const builder = new SmartHomePaketBuilder();
    for (let i = 0; i < 9; i++) builder.addKomponente("thermostat");

    assert.equal(builder.build().ersparnisProzent, 15.7); // 3,7 + 4 × 3
    assert.equal(builder.build().berechneErsparnis(100), 15.7);
});

test("Der Komponentenkatalog ist plausibel: Pflichtwerte, Obergrenze und Rentabilität", () => {
    for (const k of KOMPONENTEN) {
        assert.ok(k.preis > 0 && k.ersparnisProzent > 0 && k.maxSinnvoll >= 1, k.id);
    }

    const builder = new SmartHomePaketBuilder();
    for (const k of KOMPONENTEN) for (let i = 0; i < k.maxSinnvoll; i++) builder.addKomponente(k.id);
    assert.ok(builder.build().ersparnisProzent < MAX_ERSPARNIS_PROZENT);

    // Thermostat, Fenster- und Temperatursensor rechnen sich ab etwa 75 € Energiekosten
    for (const id of ["thermostat", "fenster", "temperatur"]) {
        const k = KOMPONENTEN.find(komponente => komponente.id === id);
        const schwelle = k.preis / (k.ersparnisProzent / 100);
        assert.ok(schwelle <= 75, `${k.id}: ${schwelle.toFixed(1)} €`);
    }
});
