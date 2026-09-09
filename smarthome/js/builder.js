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
        const summe = this.komponenten.reduce((zwischenstand, k) => zwischenstand + k.preis, 0);
        return Math.round(summe * 100) / 100;
    }

    get ersparnisProzent() {
        const prozent = this.komponenten.reduce((summe, k) => summe + k.ersparnisProzent, 0);
        return Math.min(prozent, 30);
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

    buildStarterPaket() {
        return this.builder
            .addKomponente("led").addKomponente("led")
            .addKomponente("bewegung")
            .build();
    }

    buildKomfortPaket() {
        return this.builder
            .addKomponente("led").addKomponente("led").addKomponente("led").addKomponente("led")
            .addKomponente("bewegung").addKomponente("bewegung")
            .addKomponente("temperatur")
            .build();
    }
}

if (typeof module !== "undefined") {
    module.exports = { SmartHomePaket, PaketBuilder, SmartHomePaketBuilder, PaketKonfigurator };
}
