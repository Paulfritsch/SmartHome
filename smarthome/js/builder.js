//Product

class SmartHomePaket {
    constructor() {
        this.komponenten = [];
    }

    addKomponente(komponente) {
        this.kompontenten.push(komponente);
    }

    removeKomponente(kompId) {
        const index = this.komponeten.findIndex(k => k.id ===kompId);
        if (index !== -1) this.komponenten.splice(index, 1);
    }

    get monatspreis() {
        return this.komponenten.reduce((summe, k) => summe + k.preis, 0);
    }
}

//Builder-Interface
class PaketBuilder {
    addKomponente(kompId) { throw new Error("Muss implementiert werden"); }
    build() {throw new Error("Muss implementiert werden");}
}

//Concrete Builder
class SmartHomePAketBuilder extends PaketBuilder {
    constructor() {
        super();
        this.paket = new SmartHomePaket();
    }

    addKomponente(kompId) {
        const komponente = KOMPONENTEN.find(k => k.id ===kompId);
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