<?php

/**
 * Unit Tests for BaseCatalogueGenerator
 * 
 * @package   Ksfraser\Frontaccounting\GenCat\Tests\Unit
 * @author    KS Fraser <kevin@ksfraser.com>
 * @copyright 2025 KS Fraser
 * @license   GPL-3.0-or-later
 * @version   1.0.0
 * @since     1.0.0
 */

namespace Ksfraser\Frontaccounting\GenCat\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator;
use Ksfraser\Frontaccounting\GenCat\DatabaseInterface;

/**
 * Test class for BaseCatalogueGenerator
 * 
 * @package Ksfraser\Frontaccounting\GenCat\Tests\Unit
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 * 
 * @covers \Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator
 */
class BaseCatalogueGeneratorTest extends TestCase
{
    /**
     * Mock database interface
     * 
     * @var DatabaseInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockDatabase;

    /**
     * Test generator instance
     * 
     * @var TestCatalogueGenerator
     */
    private $generator;

    /**
     * Set up test fixtures before each test method
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDatabase = $this->createMock(DatabaseInterface::class);
        $this->generator = new TestCatalogueGenerator('test_prefs_table');
        $this->generator->setDatabase($this->mockDatabase);
    }

    /**
     * Test constructor sets default values correctly
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator::__construct
     * @covers \Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator::initializeDefaults
     */
    public function testConstructorSetsDefaults()
    {
        $this->assertEquals('Retail', $this->generator->getRetailType());
        $this->assertEquals('Sale', $this->generator->getSalePriceType());
        $this->assertStringContainsString('catalogue.csv', $this->generator->getFilename());
    }

    /**
     * Test setters and getters work correctly
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator::setRetailType
     * @covers \Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator::getRetailType
     * @covers \Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator::setSalePriceType
     * @covers \Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator::getSalePriceType
     */
    public function testSettersAndGetters()
    {
        $this->generator->setRetailType('CustomRetail');
        $this->assertEquals('CustomRetail', $this->generator->getRetailType());

        $this->generator->setSalePriceType('CustomSale');
        $this->assertEquals('CustomSale', $this->generator->getSalePriceType());
    }

    /**
     * Test database interface setter and getter
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator::setDatabase
     * @covers \Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator::getDatabase
     */
    public function testDatabaseInterface()
    {
        $mockDb = $this->createMock(DatabaseInterface::class);
        $this->generator->setDatabase($mockDb);
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getDatabase');
        $method->setAccessible(true);
        
        $this->assertSame($mockDb, $method->invoke($this->generator));
    }

    /**
     * Test filename generation with file count
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator::setFileName
     */
    public function testSetFileName()
    {
        // Set up the generator with specific values
        $reflection = new \ReflectionClass($this->generator);
        
        $fileBaseProperty = $reflection->getProperty('file_base');
        $fileBaseProperty->setAccessible(true);
        $fileBaseProperty->setValue($this->generator, 'test_file');
        
        $fileCountProperty = $reflection->getProperty('file_count');
        $fileCountProperty->setAccessible(true);
        $fileCountProperty->setValue($this->generator, 2);
        
        $fileExtProperty = $reflection->getProperty('file_ext');
        $fileExtProperty->setAccessible(true);
        $fileExtProperty->setValue($this->generator, 'csv');
        
        $this->generator->setFileName();
        $this->assertEquals('test_file_2.csv', $this->generator->getFilename());
    }

    /**
     * Test email file with valid configuration
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator::emailFile
     */
    public function testEmailFileWithValidConfig()
    {
        // Set up email configuration
        $this->generator->setEmailTo('test@example.com');
        $this->generator->setEmailFrom('sender@example.com');
        
        // Set b_email to false to test early return
        $this->generator->setB_email(false);
        
        $result = $this->generator->emailFile('Test Subject');
        $this->assertFalse($result);
    }

    /**
     * Test email file without mailto configuration
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\BaseCatalogueGenerator::emailFile
     */
    public function testEmailFileWithoutMailto()
    {
        $result = $this->generator->emailFile('Test Subject');
        $this->assertFalse($result);
    }
}

/**
 * Concrete test implementation of BaseCatalogueGenerator
 * 
 * @package Ksfraser\Frontaccounting\GenCat\Tests\Unit
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 */
class TestCatalogueGenerator extends BaseCatalogueGenerator
{
    /**
     * Test implementation of createFile method
     * 
     * @return int Always returns 1 for testing
     */
    public function createFile()
    {
        return 1;
    }
}
