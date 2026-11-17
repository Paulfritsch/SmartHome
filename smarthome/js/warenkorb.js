function formatiereEuro(betrag) {
    return `${betrag.toLocaleString("de-DE", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`;
}

function mitVorzeichen(betrag) {
    return `${betrag > 0 ? "+" : betrag < 0 ? "−" : ""}${formatiereEuro(Math.abs(betrag))}`;
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

    const gruppen = new Map();
    paket.komponenten.forEach(komponente => {
        const gruppe = gruppen.get(komponente.id);
        if (gruppe) gruppe.anzahl += 1;
        else gruppen.set(komponente.id, { komponente, anzahl: 1 });
    });

    const basis = document.createElement("li");
    const basisName = document.createElement("span");
    const basisPreis = document.createElement("span");
    basisName.textContent = "Basis-Abo (Hub, App, Support, 2× LED, Steckdose, Bewegungsmelder)";
    basisPreis.className = "betrag";
    basisPreis.textContent = formatiereEuro(BASIS_GEBUEHR);
    basis.append(basisName, basisPreis);
    liste.appendChild(basis);

    gruppen.forEach(({ komponente, anzahl }) => {
        const eintrag = document.createElement("li");
        const name = document.createElement("span");
        const preis = document.createElement("span");
        name.textContent = anzahl > 1 ? `${komponente.name} ×${anzahl}` : komponente.name;
        preis.className = "betrag";
        preis.textContent = formatiereEuro(komponente.preis * anzahl);
        eintrag.append(name, preis);
        liste.appendChild(eintrag);
    });

    document.getElementById("energiekosten").textContent = formatiereEuro(energiekosten);
    document.getElementById("ersparnis").textContent = `+${formatiereEuro(ersparnis)}`;
    document.getElementById("abokosten").textContent = `−${formatiereEuro(paket.monatspreis)}`;
    document.getElementById("differenz").textContent = mitVorzeichen(differenz);
}

document.getElementById("payment-form").addEventListener("submit", ereignis => {
    ereignis.preventDefault();
    document.getElementById("bestellen-btn").disabled = true;
    document.getElementById("schritt-bezahlen").removeAttribute("aria-current");
    document.getElementById("schritt-fertig").setAttribute("aria-current", "step");
    document.getElementById("bestell-status").textContent = "Testbestellung abgeschlossen. Es wurden keine Daten versendet.";
});

renderWarenkorb();
