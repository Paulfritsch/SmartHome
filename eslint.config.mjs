// Statische Analyse der Website-Skripte mit ESLint (läuft in der Pipeline, siehe .github/workflows/ci.yml)
// Lokal: npm install, danach npm run lint
import js from "@eslint/js";
import globals from "globals";

export default [
    js.configs.recommended,
    {
        // Die Skripte der Website sind klassische <script>-Dateien ohne Module.
        // Was eine Datei anlegt, nutzen die anderen als globale Namen (Reihenfolge siehe HTML-Seiten).
        files: ["smarthome/js/**/*.js"],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: "script",
            globals: {
                ...globals.browser,
                module: "readonly",
                KOMPONENTEN: "readonly",
                SmartHomePaketBuilder: "readonly",
                PaketKonfigurator: "readonly",
                MAX_ERSPARNIS_PROZENT: "readonly",
                LAUFZEIT_MONATE: "readonly",
                BASIS_GEBUEHR: "readonly",
                BASIS_ENTHALTEN: "readonly",
                BASIS_ERSPARNIS_PROZENT: "readonly",
                formatiereProzent: "readonly",
            },
        },
        rules: {
            // Globale Namen werden absichtlich in einer Datei angelegt und in einer anderen genutzt
            "no-unused-vars": ["error", { vars: "local", argsIgnorePattern: "^_" }],
            "no-redeclare": ["error", { builtinGlobals: false }],
            eqeqeq: "error",
            "prefer-const": "error",
            "no-var": "error",
        },
    },
    {
        files: ["tests/js/**/*.js"],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: "commonjs",
            // KOMPONENTEN legen die Tests selbst als globalen Namen an (wie im Browser)
            globals: { ...globals.node, KOMPONENTEN: "readonly" },
        },
    },
];
