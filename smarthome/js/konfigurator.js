let aktuellerBuilder = new SmartHomePaketBuilder();

function formatiereEuro(betrag) {
    return betrag.toLocaleString("de-DE", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renderKomponenten() {
    const liste = document.getElementById("komponenten-liste");
    liste.innerHTML = "";

    KOMPONENTEN.forEach(komponente => {
        const eintrag = document.createElement("div");
        eintrag.className = "komponente";
        eintrag.innerHTML = `
            <div class="component-main">
                <span class="component-icon">${komponente.icon}</span>
                <div class="info">
                    <div class="name">${komponente.name}</div>
                    <div class="beschreibung">${komponente.beschreibung}</div>
                    <span class="saving-tag">bis zu ${komponente.ersparnisProzent} % Ersparnis</span>
                </div>
            </div>
            <div class="preis-info">
                <span class="preis">${formatiereEuro(komponente.preis)}&nbsp;€/Monat</span>
                <button class="add-btn" type="button" onclick="komponenteHinzufuegen('${komponente.id}')">Hinzufügen +</button>
            </div>`;
        liste.appendChild(eintrag);
    });
}

function komponenteHinzufuegen(kompId) {
    aktuellerBuilder.addKomponente(kompId);
    renderPaket();
}

function komponenteEntfernen(kompId) {
    aktuellerBuilder.paket.removeKomponente(kompId);
    renderPaket();
}

function ladeVorlage(typ) {
    aktuellerBuilder = new SmartHomePaketBuilder();
    const konfigurator = new PaketKonfigurator(aktuellerBuilder);

    if (typ === "starter") konfigurator.buildStarterPaket();
    if (typ === "komfort") konfigurator.buildKomfortPaket();
    renderPaket();
}

function paketZuruecksetzen() {
    aktuellerBuilder = new SmartHomePaketBuilder();
    renderPaket();
}

function ladeGespeichertesPaket() {
    try {
        const daten = JSON.parse(sessionStorage.getItem("meinPaket") || "[]");
        if (!Array.isArray(daten)) return;

        daten.forEach(komponente => {
            if (komponente && KOMPONENTEN.some(eintrag => eintrag.id === komponente.id)) {
                aktuellerBuilder.addKomponente(komponente.id);
            }
        });

        const energiekosten = Number(sessionStorage.getItem("energiekosten"));
        if (Number.isFinite(energiekosten) && energiekosten > 0) {
            document.getElementById("energiekosten").value = String(energiekosten);
        }
    } catch (fehler) {
        aktuellerBuilder = new SmartHomePaketBuilder();
    }
}

function aktualisiereBerechnung() {
    const paket = aktuellerBuilder.build();
    const energiekosten = Number(document.getElementById("energiekosten").value);
    const ausgabe = document.getElementById("ersparnis-ausgabe");

    if (!Number.isFinite(energiekosten) || energiekosten <= 0) {
        ausgabe.textContent = "Bitte gültige monatliche Energiekosten eingeben.";
        return;
    }

    const ersparnis = paket.berechneErsparnis(energiekosten);
    const differenz = ersparnis - paket.monatspreis;
    const ergebnis = differenz >= 0
        ? `${formatiereEuro(differenz)} € mehr Ersparnis als Abo-Kosten.`
        : `${formatiereEuro(Math.abs(differenz))} € mehr Abo-Kosten als geschätzte Ersparnis.`;

    ausgabe.textContent = `Geschätzte Ersparnis: ${formatiereEuro(ersparnis)} € pro Monat (${paket.ersparnisProzent} %). ${ergebnis}`;
}

function renderPaket() {
    const paket = aktuellerBuilder.build();
    const liste = document.getElementById("ausgewaehlte-liste");
    const preisEl = document.getElementById("gesamtpreis");
    const weiterBtn = document.getElementById("weiter-btn");

    liste.innerHTML = "";
    if (paket.komponenten.length === 0) {
        const leer = document.createElement("li");
        leer.className = "leer-hinweis";
        leer.textContent = "Noch keine Komponente ausgewählt.";
        liste.appendChild(leer);
    } else {
        const gruppen = new Map();
        paket.komponenten.forEach(komponente => {
            const gruppe = gruppen.get(komponente.id);
            if (gruppe) {
                gruppe.anzahl += 1;
            } else {
                gruppen.set(komponente.id, { komponente, anzahl: 1 });
            }
        });

        gruppen.forEach(({ komponente, anzahl }) => {
            const eintrag = document.createElement("li");
            const name = document.createElement("span");
            const entfernen = document.createElement("button");
            name.textContent = `${komponente.name} x${anzahl}`;
            entfernen.className = "remove-btn";
            entfernen.type = "button";
            entfernen.textContent = "entfernen";
            entfernen.addEventListener("click", () => komponenteEntfernen(komponente.id));
            eintrag.append(name, entfernen);
            liste.appendChild(eintrag);
        });
    }

    preisEl.innerHTML = `${formatiereEuro(paket.monatspreis)}&nbsp;€<span> /Monat</span>`;
    weiterBtn.disabled = paket.komponenten.length === 0;
    aktualisiereBerechnung();
}

function weiterZumWarenkorb() {
    const energiekosten = Number(document.getElementById("energiekosten").value);
    if (!Number.isFinite(energiekosten) || energiekosten <= 0) {
        document.getElementById("energiekosten").focus();
        aktualisiereBerechnung();
        return;
    }

    const paket = aktuellerBuilder.build();
    sessionStorage.setItem("meinPaket", JSON.stringify(paket.komponenten));
    sessionStorage.setItem("energiekosten", String(energiekosten));
    window.location.href = "warenkorb.html";
}

document.getElementById("energiekosten").addEventListener("input", aktualisiereBerechnung);
renderKomponenten();

const parameter = new URLSearchParams(window.location.search);
const vorlage = parameter.get("paket");
if (vorlage === "starter" || vorlage === "komfort") {
    ladeVorlage(vorlage);
} else {
    if (parameter.get("bearbeiten") === "1") ladeGespeichertesPaket();
    renderPaket();
}
