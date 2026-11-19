<?php

namespace App\Services;

use App\Models\Medicament;
use DomainException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class MedicamentStockImportService
{
    private const MAX_ROWS = 1000;

    public function readFile(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName('Import') ?? $spreadsheet->getActiveSheet();

        try {
            $headerRow = $this->findHeaderRow($sheet);
            $highestRow = min($sheet->getHighestDataRow(), $headerRow + self::MAX_ROWS);
            $rows = [];

            for ($rowNumber = $headerRow + 1; $rowNumber <= $highestRow; $rowNumber++) {
                $values = [];
                foreach (range('A', 'I') as $column) {
                    $values[$column] = $sheet->getCell($column . $rowNumber)->getValue();
                }

                // Le modèle préremplit les médicaments existants. Une ligne sans
                // quantité à ajouter est informative et ne doit rien modifier.
                if ($this->rowIsEmpty($values) || $values['C'] === null || trim((string) $values['C']) === '') {
                    continue;
                }

                $rows[] = [
                    'sourceRow' => $rowNumber,
                    'libelle' => trim((string) $values['A']),
                    'prixReference' => $this->nullableNumber($values['B']),
                    'quantite' => $this->nullableNumber($values['C']),
                    'prixAchat' => $this->nullableNumber($values['D']),
                    'quantiteMin' => $this->nullableNumber($values['E']),
                    'numeroLot' => $this->nullableText($values['F']),
                    'dateExpiration' => $this->nullableDate($values['G']),
                    'fournisseur' => $this->nullableText($values['H']),
                    'reference' => $this->nullableText($values['I']),
                ];
            }

            if ($sheet->getHighestDataRow() > $headerRow + self::MAX_ROWS) {
                throw new DomainException('Le fichier dépasse la limite de ' . self::MAX_ROWS . ' lignes.');
            }

            return $rows;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    public function importRows(array $rows, int $cabinetId, int $userId): array
    {
        $result = [
            'rows' => count($rows),
            'createdMedicaments' => 0,
            'existingMedicaments' => 0,
            'stockEntries' => 0,
            'errors' => [],
        ];
        $fingerprints = [];

        foreach ($rows as $index => $row) {
            $sourceRow = (int) ($row['sourceRow'] ?? $index + 1);

            try {
                $this->validateRow($row);
                $fingerprint = $this->fingerprint($row);
                if (isset($fingerprints[$fingerprint])) {
                    throw new DomainException(
                        'Ligne identique à la ligne ' . $fingerprints[$fingerprint] . '.'
                    );
                }
                $fingerprints[$fingerprint] = $sourceRow;

                $status = DB::transaction(function () use ($row, $cabinetId, $userId) {
                    $normalizedName = $this->normalizeName($row['libelle']);
                    $medicaments = Medicament::where('fkidtype', 1)->lockForUpdate()->get();
                    $medicament = $medicaments->first(function (Medicament $item) use ($normalizedName) {
                        return $this->normalizeName($item->LibelleMedic) === $normalizedName;
                    });

                    $created = false;
                    if (!$medicament) {
                        $medicament = Medicament::create([
                            'LibelleMedic' => $this->cleanName($row['libelle']),
                            'fkidtype' => 1,
                            'PrixRef' => (float) ($row['prixReference'] ?? 0),
                        ]);
                        $created = true;
                    }

                    $meta = [
                        'numeroLot' => $row['numeroLot'] ?? null,
                        'dateExpiration' => $row['dateExpiration'] ?? null,
                        'fournisseur' => $row['fournisseur'] ?? null,
                        'reference' => $row['reference'] ?? null,
                        'motif' => 'Import Excel de stock',
                        'notes' => 'Import depuis le modèle médicaments et stock',
                    ];
                    if ($row['quantiteMin'] !== null) {
                        $meta['quantiteMin'] = (float) $row['quantiteMin'];
                    }

                    app(StockService::class)->addEntry(
                        (int) $medicament->IDMedic,
                        $cabinetId,
                        (float) $row['quantite'],
                        (float) $row['prixAchat'],
                        $userId,
                        $meta
                    );

                    return $created ? 'created' : 'existing';
                });

                $result[$status === 'created' ? 'createdMedicaments' : 'existingMedicaments']++;
                $result['stockEntries']++;
            } catch (Throwable $exception) {
                if (!$exception instanceof DomainException) {
                    Log::error('Échec d\'une ligne d\'import médicaments/stock', [
                        'row' => $sourceRow,
                        'exception' => $exception,
                    ]);
                }
                $result['errors'][] = [
                    'row' => $sourceRow,
                    'message' => $exception instanceof DomainException
                        ? $exception->getMessage()
                        : 'Erreur technique pendant l\'import de cette ligne.',
                ];
            }
        }

        return $result;
    }

    public function normalizeName(string $name): string
    {
        return Str::lower(Str::ascii($this->cleanName($name)));
    }

    private function cleanName(string $name): string
    {
        return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    }

    private function findHeaderRow($sheet): int
    {
        for ($row = 1; $row <= min(10, $sheet->getHighestDataRow()); $row++) {
            $a = $this->normalizeName((string) $sheet->getCell('A' . $row)->getValue());
            $c = $this->normalizeName((string) $sheet->getCell('C' . $row)->getValue());
            $d = $this->normalizeName((string) $sheet->getCell('D' . $row)->getValue());

            if (str_contains($a, 'libelle') && str_contains($a, 'medicament')
                && str_contains($c, 'quantite') && str_contains($d, 'prix') && str_contains($d, 'achat')) {
                return $row;
            }
        }

        throw new DomainException('En-têtes invalides. Utilisez le modèle Excel fourni sans renommer ses colonnes.');
    }

    private function validateRow(array $row): void
    {
        if ($this->normalizeName((string) ($row['libelle'] ?? '')) === '') {
            throw new DomainException('Le libellé du médicament est obligatoire.');
        }
        if (!is_numeric($row['quantite'] ?? null) || (float) $row['quantite'] <= 0
            || floor((float) $row['quantite']) !== (float) $row['quantite']) {
            throw new DomainException('La quantité doit être un nombre entier supérieur à zéro.');
        }
        if (!is_numeric($row['prixAchat'] ?? null) || (float) $row['prixAchat'] < 0) {
            throw new DomainException('Le prix d\'achat doit être un nombre positif ou nul.');
        }
        if ($row['prixReference'] !== null && (!is_numeric($row['prixReference']) || (float) $row['prixReference'] < 0)) {
            throw new DomainException('Le prix de référence doit être un nombre positif ou nul.');
        }
        if ($row['quantiteMin'] !== null && (!is_numeric($row['quantiteMin']) || (float) $row['quantiteMin'] < 0
            || floor((float) $row['quantiteMin']) !== (float) $row['quantiteMin'])) {
            throw new DomainException('Le seuil minimum doit être un nombre entier positif ou nul.');
        }
        if ($row['dateExpiration'] !== null && strtotime((string) $row['dateExpiration']) === false) {
            throw new DomainException('La date d\'expiration est invalide. Utilisez le format AAAA-MM-JJ.');
        }
    }

    private function fingerprint(array $row): string
    {
        return hash('sha256', json_encode([
            $this->normalizeName((string) $row['libelle']),
            (float) $row['quantite'],
            (float) $row['prixAchat'],
            $row['numeroLot'] ?? null,
            $row['dateExpiration'] ?? null,
            $row['fournisseur'] ?? null,
            $row['reference'] ?? null,
        ]));
    }

    private function rowIsEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function nullableText($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableNumber($value)
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return trim((string) $value);
        }
        return (float) $value;
    }

    private function nullableDate($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return trim((string) $value);
        }
        return date('Y-m-d', $timestamp);
    }
}
