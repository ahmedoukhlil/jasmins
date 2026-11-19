<?php

namespace App\Services;

use App\Models\Medicament;
use App\Models\StockMedicament;
use DomainException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class MedicamentStockTemplateService
{
    private const MAX_ROWS = 1000;

    public function buildForCabinet(int $cabinetId): Spreadsheet
    {
        $templatePath = public_path('templates/modele_import_medicaments_stock.xlsx');
        if (!is_file($templatePath)) {
            throw new DomainException('Le modèle Excel est introuvable.');
        }

        // Le fichier est copié dans le répertoire temporaire avant lecture pour
        // rester compatible avec les hébergements qui restreignent public/.
        $temporaryPath = tempnam(sys_get_temp_dir(), 'jasmins-stock-template-');
        $templateContents = file_get_contents($templatePath);
        if ($temporaryPath === false || $templateContents === false
            || file_put_contents($temporaryPath, $templateContents) === false) {
            if ($temporaryPath !== false) {
                @unlink($temporaryPath);
            }
            throw new DomainException('Le modèle Excel ne peut pas être préparé.');
        }

        try {
            $spreadsheet = IOFactory::load($temporaryPath);
        } finally {
            @unlink($temporaryPath);
        }

        $medicaments = Medicament::medicaments()->orderBy('LibelleMedic')->get();
        if ($medicaments->count() > self::MAX_ROWS) {
            $spreadsheet->disconnectWorksheets();
            throw new DomainException('Le modèle est limité à ' . self::MAX_ROWS . ' médicaments.');
        }

        $stocks = StockMedicament::where('fkidCabinet', $cabinetId)
            ->where('Masquer', 0)
            ->get()
            ->keyBy('fkidMedicament');
        $sheet = $spreadsheet->getSheetByName('Import') ?? $spreadsheet->getActiveSheet();

        foreach ($medicaments as $index => $medicament) {
            $row = 5 + $index;
            $stock = $stocks->get($medicament->IDMedic);

            $sheet->setCellValue('A' . $row, $medicament->LibelleMedic);
            $sheet->setCellValue('B' . $row, (float) ($medicament->PrixRef ?? 0));
            $sheet->setCellValue('C' . $row, null);
            $sheet->setCellValue('D' . $row, (float) ($stock->prixAchat ?? 0));
            $sheet->setCellValue('E' . $row, (float) ($stock->quantiteMin ?? 0));
            $sheet->setCellValue('J' . $row, (float) ($stock->quantiteStock ?? 0));
        }

        return $spreadsheet;
    }
}
