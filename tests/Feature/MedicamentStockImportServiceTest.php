<?php

namespace Tests\Feature;

use App\Models\Medicament;
use App\Models\StockMedicament;
use App\Services\MedicamentStockImportService;
use App\Services\MedicamentStockTemplateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MedicamentStockImportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'import_test',
            'database.connections.import_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('import_test');
        DB::setDefaultConnection('import_test');

        Schema::create('medicaments', function (Blueprint $table) {
            $table->increments('IDMedic');
            $table->string('LibelleMedic');
            $table->unsignedInteger('fkidtype');
            $table->decimal('PrixRef', 14, 3)->default(0);
            $table->boolean('estInterne')->default(false);
        });

        Schema::create('stock_medicaments', function (Blueprint $table) {
            $table->increments('idStock');
            $table->unsignedInteger('fkidMedicament');
            $table->unsignedInteger('fkidCabinet');
            $table->decimal('quantiteStock', 14, 3)->default(0);
            $table->decimal('quantiteMin', 14, 3)->default(0);
            $table->decimal('prixAchat', 14, 3)->default(0);
            $table->decimal('prixVente', 14, 3)->default(0);
            $table->dateTime('dateDerniereEntree')->nullable();
            $table->dateTime('dateDerniereSortie')->nullable();
            $table->unsignedTinyInteger('Masquer')->default(0);
            $table->unique(['fkidMedicament', 'fkidCabinet']);
        });

        Schema::create('lots_medicaments', function (Blueprint $table) {
            $table->increments('idLot');
            $table->unsignedInteger('fkidStock');
            $table->unsignedInteger('fkidMedicament');
            $table->string('numeroLot')->nullable();
            $table->decimal('quantiteInitiale', 14, 3);
            $table->decimal('quantiteRestante', 14, 3);
            $table->date('dateExpiration')->nullable();
            $table->dateTime('dateEntree')->nullable();
            $table->decimal('prixAchatUnitaire', 14, 3)->default(0);
            $table->string('fournisseur')->nullable();
            $table->string('referenceFacture')->nullable();
            $table->unsignedInteger('fkidUser');
            $table->unsignedTinyInteger('Masquer')->default(0);
        });

        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->increments('idMouvement');
            $table->unsignedInteger('fkidStock');
            $table->unsignedInteger('fkidMedicament');
            $table->unsignedInteger('fkidLot')->nullable();
            $table->string('typeMouvement');
            $table->decimal('quantite', 14, 3);
            $table->decimal('prixUnitaire', 14, 3)->default(0);
            $table->decimal('montantTotal', 14, 3)->default(0);
            $table->string('motif')->nullable();
            $table->unsignedInteger('fkidFacture')->nullable();
            $table->unsignedInteger('fkidDetailFacture')->nullable();
            $table->unsignedInteger('fkidPatient')->nullable();
            $table->unsignedInteger('fkidUser')->nullable();
            $table->dateTime('dateMouvement')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function test_import_reuses_normalized_name_and_creates_only_missing_medicine(): void
    {
        Medicament::create([
            'LibelleMedic' => 'Paracétamol',
            'fkidtype' => 1,
            'PrixRef' => 50,
        ]);

        $result = app(MedicamentStockImportService::class)->importRows([
            $this->row(5, '  PARACETAMOL  ', 5, 10, ['prixReference' => 999]),
            $this->row(6, 'Amoxicilline', 3, 20, ['prixReference' => 100]),
        ], 10, 7);

        $this->assertSame(1, $result['createdMedicaments']);
        $this->assertSame(1, $result['existingMedicaments']);
        $this->assertSame(2, $result['stockEntries']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(2, Medicament::count());
        $this->assertEquals(50.0, Medicament::where('LibelleMedic', 'Paracétamol')->value('PrixRef'));
        $this->assertEquals(100.0, Medicament::where('LibelleMedic', 'Amoxicilline')->value('PrixRef'));
        $this->assertEquals(8.0, StockMedicament::sum('quantiteStock'));
    }

    public function test_identical_excel_rows_are_rejected_without_duplicating_stock(): void
    {
        $row = $this->row(5, 'Ibuprofène', 4, 12, ['numeroLot' => 'LOT-01']);
        $duplicate = $row;
        $duplicate['sourceRow'] = 6;

        $result = app(MedicamentStockImportService::class)->importRows([$row, $duplicate], 10, 7);

        $this->assertSame(1, Medicament::count());
        $this->assertEquals(4.0, StockMedicament::firstOrFail()->quantiteStock);
        $this->assertSame(1, $result['stockEntries']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(6, $result['errors'][0]['row']);
    }

    public function test_two_lots_of_the_same_new_medicine_create_only_one_medicine(): void
    {
        $result = app(MedicamentStockImportService::class)->importRows([
            $this->row(5, 'Vitamine C', 4, 8, ['numeroLot' => 'VC-01']),
            $this->row(6, ' vitamine   c ', 3, 9, ['numeroLot' => 'VC-02']),
        ], 10, 7);

        $this->assertSame(1, $result['createdMedicaments']);
        $this->assertSame(1, $result['existingMedicaments']);
        $this->assertSame(1, Medicament::count());
        $this->assertEquals(7.0, StockMedicament::firstOrFail()->quantiteStock);
        $this->assertSame([], $result['errors']);
    }

    public function test_reader_accepts_the_model_headers_and_excel_date(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jasmins-import-' . bin2hex(random_bytes(6)) . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import');
        $sheet->fromArray([
            ["Libellé médicament *", 'Prix de référence', "Quantité à ajouter *", "Prix d'achat unitaire *", 'Seuil minimum', 'Numéro de lot', "Date d'expiration", 'Fournisseur', 'Référence facture'],
            ['Doliprane', 75, 6, 15, 2, 'D-01', 46388, 'Fournisseur A', 'FAC-01'],
        ], null, 'A4');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        try {
            $rows = app(MedicamentStockImportService::class)->readFile($path);
            $this->assertCount(1, $rows);
            $this->assertSame('Doliprane', $rows[0]['libelle']);
            $this->assertEquals(6.0, $rows[0]['quantite']);
            $this->assertSame('2027-01-01', $rows[0]['dateExpiration']);
        } finally {
            @unlink($path);
        }
    }

    public function test_prefilled_template_shows_current_stock_but_imports_only_rows_with_added_quantity(): void
    {
        $medicament = Medicament::create([
            'LibelleMedic' => 'Aspirine',
            'fkidtype' => 1,
            'PrixRef' => 45,
        ]);
        StockMedicament::create([
            'fkidMedicament' => $medicament->IDMedic,
            'fkidCabinet' => 10,
            'quantiteStock' => 17,
            'quantiteMin' => 3,
            'prixAchat' => 12,
            'prixVente' => 45,
            'Masquer' => 0,
        ]);

        $spreadsheet = app(MedicamentStockTemplateService::class)->buildForCabinet(10);
        $sheet = $spreadsheet->getSheetByName('Import');
        $this->assertSame('Aspirine', $sheet->getCell('A5')->getValue());
        $this->assertEquals(17.0, $sheet->getCell('J5')->getValue());
        $this->assertNull($sheet->getCell('C5')->getValue());

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jasmins-prefilled-' . bin2hex(random_bytes(6)) . '.xlsx';
        try {
            (new Xlsx($spreadsheet))->save($path);
            $reader = app(MedicamentStockImportService::class);
            $this->assertSame([], $reader->readFile($path));

            $sheet->setCellValue('C5', 2);
            (new Xlsx($spreadsheet))->save($path);
            $rows = $reader->readFile($path);
            $this->assertCount(1, $rows);
            $this->assertEquals(2.0, $rows[0]['quantite']);
            $this->assertEquals(12.0, $rows[0]['prixAchat']);
        } finally {
            $spreadsheet->disconnectWorksheets();
            @unlink($path);
        }
    }

    private function row(int $sourceRow, string $libelle, float $quantite, float $prixAchat, array $overrides = []): array
    {
        return array_merge([
            'sourceRow' => $sourceRow,
            'libelle' => $libelle,
            'prixReference' => null,
            'quantite' => $quantite,
            'prixAchat' => $prixAchat,
            'quantiteMin' => null,
            'numeroLot' => null,
            'dateExpiration' => null,
            'fournisseur' => null,
            'reference' => null,
        ], $overrides);
    }
}
