function formatiereEuro(betrag) {
    return `${betrag.toLocaleString("de-DE", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`;
}

function ladePaket() {
    let gespeicherteKomponenten = [];

    try {
        const daten = JSON.parse(sessionStorage.getItem("meinPaket") || "[]");
        if (Array.isArray(daten)) gespeicherteKomponenten = daten;
    } catch (fehler) {
        gespeicherteKomponenten = [];
    }

    const builder = new SmartHomePaketBuilder();
    gespeicherteKomponenten.forEach(komponente => {
        if (komponente && KOMPONENTEN.some(eintrag => eintrag.id === komponente.id)) {
            builder.addKomponente(komponente.id);
        }
    });

    return builder.build();
}

function renderWarenkorb() {
    const paket = ladePaket();
    const energiekosten = Number(sessionStorage.getItem("energiekosten")) || 0;
    const ersparnis = paket.berechneErsparnis(energiekosten);
    const differenz = ersparnis - paket.monatspreis;
    const liste = document.getElementById("warenkorb-liste");

    paket.komponenten.forEach(komponente => {
        const eintrag = document.createElement("li");
        const name = document.createElement("span");
        const preis = document.createElement("span");
        name.textContent = komponente.name;
        preis.textContent = formatiereEuro(komponente.preis);
        eintrag.append(name, preis);
        liste.appendChild(eintrag);
    });

    const istLeer = paket.komponenten.length === 0;
    document.getElementById("leer-hinweis").hidden = !istLeer;
    document.getElementById("bestellen-btn").disabled = istLeer;
    document.getElementById("energiekosten").textContent = formatiereEuro(energiekosten);
    document.getElementById("ersparnis").textContent = formatiereEuro(ersparnis);
    document.getElementById("abokosten").textContent = formatiereEuro(paket.monatspreis);
    document.getElementById("differenz").textContent = `${differenz >= 0 ? "+" : ""}${formatiereEuro(differenz)}`;
}

document.getElementById("bestellen-btn").addEventListener("click", ereignis => {
    ereignis.currentTarget.disabled = true;
    document.getElementById("bestell-status").textContent = "Testbestellung abgeschlossen. Es wurden keine Daten versendet.";
});

renderWarenkorb();
