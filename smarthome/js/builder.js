const MAX_ERSPARNIS_PROZENT = 30;
const LAUFZEIT_MONATE = 24;

// Basis-Abo wie im PHP-Prototyp (prototype/src/Catalog.php): Grundgebuehr inkl. Hub, App, Support
// und diesen Komponenten. Alles, was man zusaetzlich waehlt, kommt oben drauf.
const BASIS_GEBUEHR = 5.99;
const BASIS_ENTHALTEN = ["led", "led", "steckdose", "bewegung"];

//Product

class SmartHomePaket {
    constructor() {
        this.komponenten = [];
    }

    addKomponente(komponente) {
        this.komponenten.push(komponente);
    }

    removeKomponente(kompId) {
        const index = this.komponenten.findIndex(k => k.id === kompId);
        if (index !== -1) this.komponenten.splice(index, 1);
    }

    get monatspreis() {
        const summe = this.komponenten.reduce((zwischenstand, k) => zwischenstand + k.preis, BASIS_GEBUEHR);
        return Math.round(summe * 100) / 100;
    }

    get ersparnisProzent() {
        const alle = [...BASIS_ENTHALTEN.map(id => KOMPONENTEN.find(k => k.id === id)), ...this.komponenten];
        const prozent = alle.reduce((summe, k) => summe + k.ersparnisProzent, 0);
        return Math.min(prozent, MAX_ERSPARNIS_PROZENT);
    }

    berechneErsparnis(monatlicheEnergiekosten) {
        if (!Number.isFinite(monatlicheEnergiekosten) || monatlicheEnergiekosten <= 0) return 0;
        return monatlicheEnergiekosten * this.ersparnisProzent / 100;
    }
}

//Builder-Interface
class PaketBuilder {
    addKomponente(kompId) { throw new Error("Muss implementiert werden"); }
    build() { throw new Error("Muss implementiert werden"); }
}

//Concrete Builder
class SmartHomePaketBuilder extends PaketBuilder {
    constructor() {
        super();
        this.paket = new SmartHomePaket();
    }

    addKomponente(kompId) {
        const komponente = KOMPONENTEN.find(k => k.id === kompId);
        if (!komponente) throw new Error(`Unbekannte Komponente: ${kompId}`);
        this.paket.addKomponente(komponente);
        return this;
    }

    build() {
        return this.paket;
    }
}

//Director
class PaketKonfigurator {
    constructor(builder) {
        this.builder = builder;
    }

    // Starter = nur das Basis-Abo
    buildStarterPaket() {
        return this.builder.build();
    }

    buildKomfortPaket() {
        return this.builder
            .addKomponente("led").addKomponente("led")
            .addKomponente("bewegung")
            .addKomponente("temperatur")
            .build();
    }
}

if (typeof module !== "undefined") {
    module.exports = { SmartHomePaket, PaketBuilder, SmartHomePaketBuilder, PaketKonfigurator, MAX_ERSPARNIS_PROZENT, LAUFZEIT_MONATE, BASIS_GEBUEHR, BASIS_ENTHALTEN };
}
