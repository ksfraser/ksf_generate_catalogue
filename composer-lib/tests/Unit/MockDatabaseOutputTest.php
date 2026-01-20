<?php

namespace Ksfraser\Frontaccounting\GenCat\Tests\Unit;

use Ksfraser\Frontaccounting\GenCat\LabelsFile;
use Ksfraser\Frontaccounting\GenCat\WoocommerceStockPriceExport;
use Ksfraser\Frontaccounting\GenCat\Tests\Support\InMemoryWriteFile;
use Ksfraser\FAMock\MockDatabase;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class MockDatabaseOutputTest extends TestCase
{
    public function testWoocommerceStockPriceExportRunsWithMockDb(): void
    {
        $db = new MockDatabase([
            [
                'stock_id' => 'SKU1',
                'Index' => '101',
                'Quantity on Hand' => 5,
                'Price' => 9.99,
            ],
            [
                'stock_id' => 'SKU2',
                'Index' => '202',
                'Quantity on Hand' => 0,
                'Price' => 12.5,
            ],
        ]);

        $writer = new InMemoryWriteFile();

        $gen = new WoocommerceStockPriceExport('test_prefs_table');
        $gen->setDatabase($db);
        $gen->setWriteFile($writer);

        $rowcount = $gen->createFile();

        $this->assertSame(2, $rowcount);
        $this->assertSame([
            '"stock_id","Index","Quantity on Hand","Price"',
            '"SKU1","101","5","9.99"',
            '"SKU2","202","0","12.5"',
        ], $writer->getLines());
    }

    public function testLabelsFileRunsWithMockDbAndRepeatsByInstock(): void
    {
        $db = new MockDatabase([
            [
                'stock_id' => 'ABC',
                'description' => 'My Product',
                'instock' => 2,
                'category' => 'Widgets',
                'price' => 3.5,
            ],
        ]);

        $writer = new InMemoryWriteFile();

        $gen = new LabelsFile('test_prefs_table');
        $gen->setDatabase($db);
        $gen->setWriteFile($writer);

        $rowcount = $gen->createFile();

        $this->assertSame(2, $rowcount);
        $this->assertSame([
            '"stock_id", "Title", "barcode", "category", "price"',
            '"ABC","My Product","ABC","Widgets","3.50"',
            '"ABC","My Product","ABC","Widgets","3.50"',
        ], $writer->getLines());
    }
}
