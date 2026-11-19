<?php

namespace Tests\Feature;

use App\Models\LotMedicament;
use App\Models\MouvementStock;
use App\Models\StockMedicament;
use App\Services\StockService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'stock_test',
            'database.connections.stock_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('stock_test');
        DB::setDefaultConnection('stock_test');

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

    public function test_entry_keeps_latest_purchase_price_and_always_creates_a_lot(): void
    {
        $service = app(StockService::class);
        $service->addEntry(1, 10, 10, 100, 7);
        $stock = $service->addEntry(1, 10, 10, 200, 7);

        $this->assertEquals(20.0, $stock->quantiteStock);
        $this->assertEquals(200.0, $stock->prixAchat);
        $this->assertSame(2, LotMedicament::count());
        $this->assertSame(2, MouvementStock::entrees()->count());
        $this->assertEquals([100.0, 200.0], LotMedicament::orderBy('idLot')->pluck('prixAchatUnitaire')->all());
    }

    public function test_deduction_is_fefo_and_idempotent_per_invoice_detail(): void
    {
        $service = app(StockService::class);
        $service->addEntry(1, 10, 3, 50, 7, ['dateExpiration' => '2027-01-01']);
        $service->addEntry(1, 10, 4, 70, 7, ['dateExpiration' => '2028-01-01']);
        $stock = StockMedicament::firstOrFail();

        $deduit = $service->deductForInvoiceDetail($stock->idStock, 5, 101, [
            'factureId' => 20,
            'patientId' => 30,
            'userId' => 7,
        ]);
        $deduitDeuxFois = $service->deductForInvoiceDetail($stock->idStock, 5, 101, [
            'factureId' => 20,
            'patientId' => 30,
            'userId' => 7,
        ]);

        $lots = LotMedicament::orderBy('dateExpiration')->get();
        $this->assertEquals(5.0, $deduit);
        $this->assertEquals(0.0, $deduitDeuxFois);
        $this->assertEquals(2.0, $stock->fresh()->quantiteStock);
        $this->assertEquals(0.0, $lots[0]->quantiteRestante);
        $this->assertEquals(2.0, $lots[1]->quantiteRestante);
        $this->assertEquals(5.0, MouvementStock::sorties()->sum('quantite'));
    }

    public function test_insufficient_stock_is_rejected_without_partial_mutation(): void
    {
        $service = app(StockService::class);
        $stock = $service->addEntry(1, 10, 2, 50, 7);

        try {
            $service->deductForInvoiceDetail($stock->idStock, 3, 101, ['userId' => 7]);
            $this->fail('Une sortie supérieure au stock aurait dû être refusée.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Stock insuffisant', $exception->getMessage());
        }

        $this->assertEquals(2.0, $stock->fresh()->quantiteStock);
        $this->assertEquals(2.0, LotMedicament::firstOrFail()->quantiteRestante);
        $this->assertSame(0, MouvementStock::sorties()->count());
    }

    public function test_restore_only_replays_real_movements(): void
    {
        $service = app(StockService::class);
        $stock = $service->addEntry(1, 10, 10, 50, 7);

        // Une facture réservée/non payée n'a pas de sortie : aucune restauration.
        $this->assertEquals(0.0, $service->restoreForDetail(999, 10));
        $this->assertEquals(10.0, $stock->fresh()->quantiteStock);

        $service->deductForInvoiceDetail($stock->idStock, 3, 101, [
            'factureId' => 20,
            'userId' => 7,
        ]);
        $this->assertEquals(7.0, $stock->fresh()->quantiteStock);

        $this->assertEquals(3.0, $service->restoreForDetail(101, 10));
        $this->assertEquals(10.0, $stock->fresh()->quantiteStock);
        $this->assertEquals(10.0, LotMedicament::firstOrFail()->quantiteRestante);
        $this->assertSame(0, MouvementStock::sorties()->count());
    }

    public function test_positive_adjustment_replaces_quantity_without_average_cost(): void
    {
        $service = app(StockService::class);
        $stock = $service->addEntry(1, 10, 5, 100, 7);

        $ecart = $service->adjustStock($stock->idStock, 8, 250, 'Inventaire physique', 7);

        $this->assertEquals(3.0, $ecart);
        $this->assertEquals(8.0, $stock->fresh()->quantiteStock);
        $this->assertEquals(250.0, $stock->fresh()->prixAchat);
        $this->assertEquals(3.0, LotMedicament::orderByDesc('idLot')->firstOrFail()->quantiteRestante);
        $this->assertDatabaseHas('mouvements_stock', [
            'typeMouvement' => 'AJUSTEMENT',
            'quantite' => 3,
            'prixUnitaire' => 250,
            'motif' => 'Inventaire physique',
        ]);
    }

    public function test_negative_adjustment_supports_historical_stock_without_lots(): void
    {
        $stock = StockMedicament::create([
            'fkidMedicament' => 1,
            'fkidCabinet' => 10,
            'quantiteStock' => 9,
            'quantiteMin' => 0,
            'prixAchat' => 80,
            'prixVente' => 100,
            'Masquer' => 0,
        ]);

        $ecart = app(StockService::class)->adjustStock(
            $stock->idStock,
            4,
            0,
            'Correction stock historique',
            7
        );

        $this->assertEquals(-5.0, $ecart);
        $this->assertEquals(4.0, $stock->fresh()->quantiteStock);
        $this->assertSame(0, LotMedicament::count());
        $this->assertDatabaseHas('mouvements_stock', [
            'typeMouvement' => 'AJUSTEMENT',
            'fkidLot' => null,
            'quantite' => -5,
            'prixUnitaire' => 80,
        ]);
    }
}
