function formatiereEuro(betrag) {
    return `${betrag.toLocaleString("de-DE", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}\u00A0€`;
}

function mitVorzeichen(betrag) {
    return `${betrag > 0 ? "+" : betrag < 0 ? "−" : ""}${formatiereEuro(Math.abs(betrag))}`;
}

// Übersicht der Website: zeigt die Auswahl aus dem Konfigurator und führt zur Registrierung.

// Gespeicherte Auswahl laden; unbekannte Komponenten werden ignoriert
function ladePaket() {
    let gespeicherteKomponenten = [];

    try {
        const daten = JSON.parse(sessionStorage.getItem("meinPaket") || "[]");
        if (Array.isArray(daten)) gespeicherteKomponenten = daten;
    } catch {
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

// Welches Paket der Registrierung (register.php) entspricht: "starter", "komfort" oder null (eigene Auswahl)
function passendesModell(komponenten) {
    const ids = komponenten.map(komponente => komponente.id).sort().join(",");
    if (ids === "") return "starter";
    const komfort = new PaketKonfigurator(new SmartHomePaketBuilder()).buildKomfortPaket();
    return ids === komfort.komponenten.map(komponente => komponente.id).sort().join(",") ? "komfort" : null;
}

// Der Button führt zur Registrierung des PHP-Prototyps und übernimmt Paket und Energiekosten als Vorauswahl
function verlinkeRegistrierung() {
    const paket = ladePaket();
    const modell = passendesModell(paket.komponenten);
    const energiekosten = Number(sessionStorage.getItem("energiekosten")) || 0;
    const parameter = new URLSearchParams();
    if (modell) parameter.set("paket", modell);
    if (energiekosten > 0) parameter.set("kosten", String(energiekosten));
    const abfrage = parameter.toString();
    document.getElementById("bestellen-btn").href = abfrage ? `register.php?${abfrage}` : "register.php";
    document.getElementById("paket-hinweis").textContent = modell
        ? `Bei der Registrierung ist das Paket ${modell === "starter" ? "Starter" : "Komfort"} schon ausgewählt.`
        : "Bei der Registrierung wählst du Starter oder Komfort. Deine weiteren Komponenten buchst du danach im Dashboard dazu.";
}

renderWarenkorb();
verlinkeRegistrierung();
