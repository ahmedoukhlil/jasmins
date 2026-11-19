import fs from "node:fs/promises";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const outputDir = "outputs/import-medicaments-stock";
const publicDir = "public/templates";
const outputName = "modele_import_medicaments_stock.xlsx";
const font = "Arial";

await fs.mkdir(outputDir, { recursive: true });
await fs.mkdir(publicDir, { recursive: true });

const workbook = Workbook.create();
const sheet = workbook.worksheets.add("Import");
sheet.showGridLines = false;
sheet.tabColor = "#244394";

sheet.mergeCells("A1:J1");
sheet.getRange("A1").values = [["Import des médicaments et du stock"]];
sheet.getRange("A1:J1").format = {
  fill: "#244394",
  font: { name: font, size: 15, bold: true, color: "#FFFFFF" },
  verticalAlignment: "center",
};
sheet.getRange("A1:J1").format.rowHeight = 30;

sheet.mergeCells("A2:J2");
sheet.getRange("A2").values = [[
  "Le stock actuel est informatif. Renseignez seulement Quantité à ajouter pour les lignes à importer. Un médicament existant est réutilisé sans modifier son prix de référence.",
]];
sheet.getRange("A2:J2").format = {
  fill: "#E8EEF9",
  font: { name: font, size: 10, color: "#24324A", italic: true },
  wrapText: true,
  verticalAlignment: "center",
};
sheet.getRange("A2:I2").format.rowHeight = 36;

const headers = [[
  "Libellé médicament *",
  "Prix de référence (nouveau)",
  "Quantité à ajouter *",
  "Prix d'achat unitaire *",
  "Seuil minimum",
  "Numéro de lot",
  "Date d'expiration",
  "Fournisseur",
  "Référence facture",
  "Stock actuel (informatif)",
]];
sheet.getRange("A4:J4").values = headers;
sheet.getRange("A4:J4").format = {
  fill: "#244394",
  font: { name: font, size: 10, bold: true, color: "#FFFFFF" },
  horizontalAlignment: "center",
  verticalAlignment: "center",
  wrapText: true,
  borders: { preset: "inside", style: "thin", color: "#FFFFFF" },
};
sheet.getRange("A4:J4").format.rowHeight = 34;

const inputRange = sheet.getRange("A5:J1004");
inputRange.format = {
  font: { name: font, size: 10, color: "#1F2937" },
  verticalAlignment: "center",
  borders: {
    insideHorizontal: { style: "thin", color: "#E5E7EB" },
    bottom: { style: "thin", color: "#CBD5E1" },
  },
};
sheet.getRange("A5:D1004").format.fill = "#FFF8D8";
sheet.getRange("E5:I1004").format.fill = "#FFFFFF";
sheet.getRange("J5:J1004").format.fill = "#F1F5F9";
sheet.getRange("B5:E1004").format.numberFormat = "#,##0.00";
sheet.getRange("C5:C1004").format.numberFormat = "#,##0";
sheet.getRange("E5:E1004").format.numberFormat = "#,##0";
sheet.getRange("G5:G1004").format.numberFormat = "yyyy-mm-dd";
sheet.getRange("J5:J1004").format.numberFormat = "#,##0";

sheet.dataValidations.add({
  range: "B5:B1004",
  rule: { type: "decimal", operator: "greaterThanOrEqual", formula1: 0 },
});
sheet.dataValidations.add({
  range: "C5:C1004",
  rule: { type: "whole", operator: "greaterThanOrEqual", formula1: 1 },
});
sheet.dataValidations.add({
  range: "D5:D1004",
  rule: { type: "decimal", operator: "greaterThanOrEqual", formula1: 0 },
});
sheet.dataValidations.add({
  range: "E5:E1004",
  rule: { type: "whole", operator: "greaterThanOrEqual", formula1: 0 },
});
sheet.dataValidations.add({
  range: "G5:G1004",
  rule: { type: "date", operator: "between", formula1: "2000-01-01", formula2: "2100-12-31" },
});

const widths = {
  A: 29,
  B: 18,
  C: 18,
  D: 21,
  E: 16,
  F: 18,
  G: 19,
  H: 24,
  I: 22,
  J: 21,
};
for (const [column, width] of Object.entries(widths)) {
  sheet.getRange(`${column}:${column}`).format.columnWidth = width;
}

sheet.freezePanes.freezeRows(4);
const table = sheet.tables.add("A4:J1004", true, "ImportMedicamentsStock");
table.style = "TableStyleMedium2";
table.showBandedColumns = false;
table.showFilterButton = true;

workbook.recalculate();

const inspection = await workbook.inspect({
  kind: "table",
  range: "Import!A1:J8",
  include: "values,formulas",
  tableMaxRows: 8,
  tableMaxCols: 10,
});
await fs.writeFile(`${outputDir}/inspection.ndjson`, inspection.ndjson, "utf8");

const errors = await workbook.inspect({
  kind: "match",
  searchTerm: "#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A|#NUM!|#NULL!|#SPILL!|#CALC!",
  options: { useRegex: true, maxResults: 100 },
  summary: "final formula error scan",
});
await fs.writeFile(`${outputDir}/errors.ndjson`, errors.ndjson, "utf8");

const preview = await workbook.render({
  sheetName: "Import",
  range: "A1:J12",
  scale: 1.3,
  format: "png",
});
await fs.writeFile(`${outputDir}/preview.png`, new Uint8Array(await preview.arrayBuffer()));

const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(`${outputDir}/${outputName}`);
await output.save(`${publicDir}/${outputName}`);
