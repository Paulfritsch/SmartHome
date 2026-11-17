const test = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");

const webOrdner = path.resolve(__dirname, "../../smarthome");
const seiten = ["index.html", "konfigurator.html", "warenkorb.html"];

test("Alle lokalen HTML-, CSS- und JavaScript-Verweise sind vorhanden", () => {
    for (const seite of seiten) {
        const datei = path.join(webOrdner, seite);
        const inhalt = fs.readFileSync(datei, "utf8");
        const verweise = [...inhalt.matchAll(/(?:href|src)="([^"#]+\.(?:html|css|js)(?:\?[^"#]*)?)"/g)];

        for (const treffer of verweise) {
            const ziel = treffer[1].split("?")[0];
            assert.ok(fs.existsSync(path.resolve(path.dirname(datei), ziel)), `${seite}: ${ziel} fehlt`);
        }
    }
});

test("Die drei Seiten sind nicht leer", () => {
    for (const seite of seiten) {
        assert.ok(fs.statSync(path.join(webOrdner, seite)).size > 100, `${seite} ist leer`);
    }
});

// ---------- Weitere Pruefungen fuer Startseite, Schrift und Datenschutz ----------

global.KOMPONENTEN = require("../../smarthome/js/komponenten.js");
const { SmartHomePaketBuilder, PaketKonfigurator } = require("../../smarthome/js/builder.js");

const euro = zahl => zahl.toLocaleString("de-DE", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const lies = datei => fs.readFileSync(path.join(webOrdner, datei), "utf8");

test("Die Startseite nennt alle Komponenten mit dem Preis aus dem Code", () => {
    const startseite = lies("index.html");

    for (const komponente of KOMPONENTEN) {
        assert.ok(startseite.includes(komponente.name), `${komponente.name} fehlt auf der Startseite`);
        assert.ok(startseite.includes(`${euro(komponente.preis)} €`), `Preis von ${komponente.name} stimmt nicht`);
        assert.ok(
            startseite.includes(`bis zu ${String(komponente.ersparnisProzent).replace(".", ",")} %`),
            `Ersparnis von ${komponente.name} stimmt nicht`
        );
    }
});

test("Jedes Piktogramm der Komponenten ist auf Startseite und Konfigurator definiert", () => {
    for (const seite of ["index.html", "konfigurator.html"]) {
        const inhalt = lies(seite);
        for (const komponente of KOMPONENTEN) {
            assert.ok(inhalt.includes(`id="${komponente.piktogramm}"`), `${seite}: ${komponente.piktogramm} fehlt`);
        }
    }
});

test("Die Paketpreise auf der Startseite entsprechen den Paketen im Code", () => {
    const startseite = lies("index.html");
    const starter = new PaketKonfigurator(new SmartHomePaketBuilder()).buildStarterPaket();
    const komfort = new PaketKonfigurator(new SmartHomePaketBuilder()).buildKomfortPaket();

    assert.ok(startseite.includes(`${euro(starter.monatspreis)} €`), "Starter-Preis stimmt nicht");
    assert.ok(startseite.includes(`${euro(komfort.monatspreis)} €`), "Komfort-Preis stimmt nicht");
});

test("Jede Seite hat Sprache, Seitentitel, Viewport und Sprunglink zum Inhalt", () => {
    for (const seite of seiten) {
        const inhalt = lies(seite);
        assert.match(inhalt, /<html lang="de">/, `${seite}: lang fehlt`);
        assert.match(inhalt, /<title>[^<]{5,}<\/title>/, `${seite}: Titel fehlt`);
        assert.match(inhalt, /name="viewport"/, `${seite}: Viewport fehlt`);
        assert.match(inhalt, /href="#inhalt"/, `${seite}: Sprunglink fehlt`);
        assert.match(inhalt, /id="inhalt"/, `${seite}: Ziel des Sprunglinks fehlt`);
    }
});

test("Es werden keine externen Ressourcen geladen (Schrift ist lokal eingebunden)", () => {
    for (const seite of seiten) {
        assert.doesNotMatch(lies(seite), /(?:href|src)="https?:/, `${seite} laedt etwas von extern`);
    }
    const css = lies("css/styles.css");
    assert.doesNotMatch(css, /@import|url\(\s*["']?https?:/, "styles.css laedt etwas von extern");

    const schrift = css.match(/url\("\.\.\/(fonts\/[^"]+\.woff2)"\)/);
    assert.ok(schrift, "Keine lokale Schriftdatei in styles.css gefunden");
    assert.ok(fs.existsSync(path.join(webOrdner, schrift[1])), `${schrift[1]} fehlt`);
    assert.ok(fs.existsSync(path.join(webOrdner, "fonts/Archivo-OFL-Lizenz.txt")), "Lizenztext der Schrift fehlt");
});

test("Jede Komponente hat ein Piktogramm, das auf der Konfigurator-Seite definiert ist", () => {
    const konfigurator = lies("konfigurator.html");
    for (const komponente of KOMPONENTEN) {
        assert.match(komponente.piktogramm, /^ico-/, `${komponente.id}: Piktogramm fehlt`);
        assert.ok(konfigurator.includes(`id="${komponente.piktogramm}"`), `${komponente.piktogramm} ist nicht definiert`);
    }
});

test("Startseite und Konfigurator verlinken oben auf den Login", () => {
  for (const seite of ["index.html", "konfigurator.html"]) {
    const html = fs.readFileSync(path.join(__dirname, "../../smarthome", seite), "utf8");
    assert.match(html, /<a class="kopf-login" href="login\.php">Login<\/a>/, seite);
  }
});
