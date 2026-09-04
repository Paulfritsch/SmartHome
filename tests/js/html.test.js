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
