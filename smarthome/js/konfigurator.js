let aktuellerBuilder = new SmartHomePaketBuilder();

function formatiereEuro(betrag) {
    return `${betrag.toLocaleString("de-DE", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`;
}

function gruppiere(komponenten) {
    const gruppen = new Map();
    komponenten.forEach(komponente => {
        const gruppe = gruppen.get(komponente.id);
        if (gruppe) {
            gruppe.anzahl += 1;
        } else {
            gruppen.set(komponente.id, { komponente, anzahl: 1 });
        }
    });
    return [...gruppen.values()];
}

function renderKomponenten() {
    const liste = document.getElementById("komponenten-liste");
    liste.replaceChildren();

    KOMPONENTEN.forEach(komponente => {
        const eintrag = document.createElement("li");
        eintrag.innerHTML = `
            <svg class="piktogramm" aria-hidden="true"><use href="#${komponente.piktogramm}"/></svg>
            <div class="posten-text">
                <h3>${komponente.name}</h3>
                <p>${komponente.beschreibung}.</p>
                <p class="spar">Spart geschätzt bis zu ${komponente.ersparnisProzent} % der Energiekosten.</p>
            </div>
            <div class="posten-rechts">
                <p class="preis">${formatiereEuro(komponente.preis)}<small>pro Monat</small></p>
                <button class="btn btn-blau btn-klein" type="button" data-komponente="${komponente.id}"
                    aria-label="${komponente.name} hinzufügen">Hinzufügen</button>
            </div>`;
        liste.appendChild(eintrag);
    });
}

function komponenteHinzufuegen(kompId) {
    aktuellerBuilder.addKomponente(kompId);
    renderPaket(kompId);
}

function komponenteEntfernen(kompId) {
    aktuellerBuilder.paket.removeKomponente(kompId);
    renderPaket(kompId);
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

function energiekostenAusEingabe() {
    const energiekosten = Number(document.getElementById("energiekosten").value);
    return Number.isFinite(energiekosten) && energiekosten > 0 ? energiekosten : null;
}

function aktualisiereBerechnung() {
    const paket = aktuellerBuilder.build();
    const energiekosten = energiekostenAusEingabe();
    const fehler = document.getElementById("rechnung-fehler");
    const rechnung = document.getElementById("rechnung");

    fehler.hidden = energiekosten !== null;
    rechnung.hidden = energiekosten === null;
    document.getElementById("energiekosten").setAttribute("aria-invalid", String(energiekosten === null));
    if (energiekosten === null) return;

    const ersparnis = paket.berechneErsparnis(energiekosten);
    const differenz = ersparnis - paket.monatspreis;

    document.getElementById("wert-ersparnis").textContent = `+${formatiereEuro(ersparnis)}`;
    document.getElementById("wert-abo").textContent = `−${formatiereEuro(paket.monatspreis)}`;
    document.getElementById("wert-differenz").textContent =
        `${differenz > 0 ? "+" : differenz < 0 ? "−" : ""}${formatiereEuro(Math.abs(differenz))}`;

    document.getElementById("skala-text").textContent =
        `${paket.ersparnisProzent} % von höchstens ${MAX_ERSPARNIS_PROZENT} %`;
    document.getElementById("skala-fuell").style.width =
        `${(paket.ersparnisProzent / MAX_ERSPARNIS_PROZENT) * 100}%`;

    let satz;
    if (paket.komponenten.length === 0) {
        satz = "Wähle eine Komponente, dann siehst du hier, was das Abo kostet und was es spart.";
    } else if (differenz >= 0) {
        satz = `Unterm Strich bleiben dir ${formatiereEuro(differenz)} im Monat.`;
    } else {
        const eigenanteil = -differenz;
        satz = `Unterm Strich zahlst du ${formatiereEuro(eigenanteil)} im Monat für Technik, die dir nach ${LAUFZEIT_MONATE} Monaten gehört. Das sind ${formatiereEuro(eigenanteil * LAUFZEIT_MONATE)} in ${LAUFZEIT_MONATE} Monaten.`;
    }
    document.getElementById("ergebnis-satz").textContent = satz;
}

function renderPaket(geaendertId = null) {
    const paket = aktuellerBuilder.build();
    const liste = document.getElementById("ausgewaehlte-liste");
    const weiterBtn = document.getElementById("weiter-btn");

    liste.replaceChildren();
    if (paket.komponenten.length === 0) {
        const leer = document.createElement("li");
        leer.className = "leer-hinweis";
        leer.textContent = "Noch nichts ausgewählt. Wähle links eine Komponente oder lade eine Vorlage.";
        liste.appendChild(leer);
    } else {
        gruppiere(paket.komponenten).forEach(({ komponente, anzahl }) => {
            const eintrag = document.createElement("li");
            const name = document.createElement("span");
            const betrag = document.createElement("span");
            const weniger = document.createElement("button");

            if (komponente.id === geaendertId) eintrag.className = "geaendert";
            name.textContent = anzahl > 1 ? `${komponente.name} ×${anzahl}` : komponente.name;
            betrag.className = "betrag";
            betrag.textContent = formatiereEuro(komponente.preis * anzahl);
            weniger.type = "button";
            weniger.textContent = "eins weniger";
            weniger.setAttribute("aria-label", `${komponente.name}: eins weniger`);
            weniger.addEventListener("click", () => komponenteEntfernen(komponente.id));
            eintrag.append(name, betrag, weniger);
            liste.appendChild(eintrag);
        });
    }

    document.getElementById("gesamtpreis").textContent = formatiereEuro(paket.monatspreis);
    weiterBtn.disabled = paket.komponenten.length === 0;
    aktualisiereBerechnung();
}

function weiterZumWarenkorb() {
    const energiekosten = energiekostenAusEingabe();
    if (energiekosten === null) {
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
document.getElementById("weiter-btn").addEventListener("click", weiterZumWarenkorb);

document.getElementById("komponenten-liste").addEventListener("click", ereignis => {
    const knopf = ereignis.target.closest("[data-komponente]");
    if (knopf) komponenteHinzufuegen(knopf.dataset.komponente);
});

document.querySelectorAll("[data-vorlage]").forEach(knopf => {
    knopf.addEventListener("click", () => {
        if (knopf.dataset.vorlage === "leer") paketZuruecksetzen();
        else ladeVorlage(knopf.dataset.vorlage);
    });
});

renderKomponenten();

const parameter = new URLSearchParams(window.location.search);
const vorlage = parameter.get("paket");
if (vorlage === "starter" || vorlage === "komfort") {
    ladeVorlage(vorlage);
} else {
    if (parameter.get("bearbeiten") === "1") ladeGespeichertesPaket();
    renderPaket();
}
