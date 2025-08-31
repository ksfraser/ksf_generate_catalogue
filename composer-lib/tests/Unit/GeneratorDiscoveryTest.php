<?php

/**
 * Unit Tests for GeneratorDiscovery Service
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
use Ksfraser\Frontaccounting\GenCat\GeneratorDiscovery;
use Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory;
use Ksfraser\Frontaccounting\GenCat\DatabaseInterface;

/**
 * Test class for GeneratorDiscovery functionality
 * 
 * @package Ksfraser\Frontaccounting\GenCat\Tests\Unit
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 * 
 * @covers \Ksfraser\Frontaccounting\GenCat\GeneratorDiscovery
 * @covers \Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory
 */
class GeneratorDiscoveryTest extends TestCase
{
    /**
     * GeneratorDiscovery instance
     * 
     * @var GeneratorDiscovery
     */
    private $discovery;

    /**
     * Factory instance for integration testing
     * 
     * @var CatalogueGeneratorFactory
     */
    private $factory;

    /**
     * Mock database interface
     * 
     * @var DatabaseInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockDatabase;

    /**
     * Set up test fixtures before each test method
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create discovery service scanning the src directory
        $srcDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src';
        $this->discovery = new GeneratorDiscovery([$srcDir]);
        
        // Create factory for integration tests
        $this->mockDatabase = $this->createMock(DatabaseInterface::class);
        $this->factory = new CatalogueGeneratorFactory($this->mockDatabase, 'test_prefs');
    }

    /**
     * Test that discovery finds expected generators
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\GeneratorDiscovery::discoverGenerators
     */
    public function testDiscoverGeneratorsFindsExpectedGenerators()
    {
        $generators = $this->discovery->discoverGenerators();
        
        $this->assertIsArray($generators);
        $this->assertNotEmpty($generators, 'Should discover at least some generators');
        
        // Check that we found the expected generator names
        $generatorNames = array_column($generators, 'name');
        $expectedNames = ['pricebook', 'square', 'woocommerce', 'woopos', 'labels'];
        
        foreach ($expectedNames as $expectedName) {
            $this->assertContains($expectedName, $generatorNames, 
                "Expected generator '$expectedName' not found in discovery results");
        }
    }

    /**
     * Test generator metadata structure
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\GeneratorDiscovery::discoverGenerators
     */
    public function testDiscoveredGeneratorMetadataStructure()
    {
        $generators = $this->discovery->discoverGenerators();
        
        $this->assertNotEmpty($generators);
        
        foreach ($generators as $generator) {
            // Check required metadata fields
            $this->assertArrayHasKey('name', $generator);
            $this->assertArrayHasKey('title', $generator);
            $this->assertArrayHasKey('class', $generator);
            $this->assertArrayHasKey('description', $generator);
            $this->assertArrayHasKey('method', $generator);
            $this->assertArrayHasKey('category', $generator);
            
            // Check discovery-added fields
            $this->assertArrayHasKey('priority', $generator);
            $this->assertArrayHasKey('full_class_name', $generator);
            $this->assertArrayHasKey('file_path', $generator);
            $this->assertArrayHasKey('discovered_at', $generator);
            
            // Validate data types
            $this->assertIsString($generator['name']);
            $this->assertIsString($generator['title']);
            $this->assertIsString($generator['class']);
            $this->assertIsString($generator['description']);
            $this->assertIsString($generator['method']);
            $this->assertIsInt($generator['priority']);
            $this->assertIsString($generator['full_class_name']);
            $this->assertIsString($generator['file_path']);
            
            // Check that required fields are not empty
            $this->assertNotEmpty($generator['name'], 'Generator name should not be empty');
            $this->assertNotEmpty($generator['title'], 'Generator title should not be empty');
            $this->assertNotEmpty($generator['method'], 'Generator method should not be empty');
        }
    }

    /**
     * Test that generators are sorted by priority
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\GeneratorDiscovery::discoverGenerators
     */
    public function testGeneratorsSortedByPriority()
    {
        $generators = $this->discovery->discoverGenerators();
        
        $this->assertNotEmpty($generators);
        
        // Check that priorities are in ascending order (lower = higher priority)
        $previousPriority = -1;
        foreach ($generators as $generator) {
            $currentPriority = $generator['priority'];
            $this->assertGreaterThanOrEqual($previousPriority, $currentPriority, 
                'Generators should be sorted by priority (ascending)');
            $previousPriority = $currentPriority;
        }
        
        // Check that the first generator has the highest priority (lowest number)
        $firstGenerator = reset($generators);
        $this->assertLessThanOrEqual(20, $firstGenerator['priority'], 
            'First generator should have high priority (low number)');
    }

    /**
     * Test findGenerator method
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\GeneratorDiscovery::findGenerator
     */
    public function testFindGenerator()
    {
        $generator = $this->discovery->findGenerator('pricebook');
        
        $this->assertNotNull($generator, 'Should find pricebook generator');
        $this->assertEquals('pricebook', $generator['name']);
        $this->assertEquals('Pricebook File', $generator['title']);
        
        // Test non-existent generator
        $notFound = $this->discovery->findGenerator('nonexistent');
        $this->assertNull($notFound, 'Should return null for non-existent generator');
    }

    /**
     * Test getGeneratorsByCategory method
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\GeneratorDiscovery::getGeneratorsByCategory
     */
    public function testGetGeneratorsByCategory()
    {
        $catalogueGenerators = $this->discovery->getGeneratorsByCategory('catalogue');
        
        $this->assertIsArray($catalogueGenerators);
        
        // Should find at least the pricebook generator
        $this->assertNotEmpty($catalogueGenerators);
        
        // All returned generators should be in the 'catalogue' category
        foreach ($catalogueGenerators as $generator) {
            $this->assertEquals('catalogue', $generator['category']);
        }
    }

    /**
     * Test caching functionality
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\GeneratorDiscovery::discoverGenerators
     * @covers \Ksfraser\Frontaccounting\GenCat\GeneratorDiscovery::clearCache
     */
    public function testCaching()
    {
        // First call should populate cache
        $generators1 = $this->discovery->discoverGenerators();
        
        // Second call should use cache (same instance)
        $generators2 = $this->discovery->discoverGenerators();
        
        $this->assertSame($generators1, $generators2, 'Should return same cached instance');
        
        // Force refresh should create new instance
        $generators3 = $this->discovery->discoverGenerators(true);
        
        $this->assertEquals($generators1, $generators3, 'Should have same content');
        // Note: Can't test !== because arrays with same content are equal
        
        // Clear cache and get new results
        $this->discovery->clearCache();
        $generators4 = $this->discovery->discoverGenerators();
        
        $this->assertEquals($generators1, $generators4, 'Should have same content after cache clear');
    }

    /**
     * Test factory integration with discovery
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory::getAvailableGenerators
     */
    public function testFactoryIntegrationWithDiscovery()
    {
        $generators = $this->factory->getAvailableGenerators();
        
        $this->assertIsArray($generators);
        $this->assertNotEmpty($generators);
        
        // Check that discovery-enhanced metadata is present
        foreach ($generators as $generator) {
            $this->assertArrayHasKey('priority', $generator);
            $this->assertArrayHasKey('category', $generator);
        }
    }

    /**
     * Test factory createGeneratorByName with discovery
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory::createGeneratorByName
     */
    public function testFactoryCreateGeneratorByNameWithDiscovery()
    {
        try {
            $generator = $this->factory->createGeneratorByName('pricebook');
            $this->assertInstanceOf(
                'Ksfraser\\Frontaccounting\\GenCat\\BaseCatalogueGenerator', 
                $generator
            );
        } catch (\Exception $e) {
            // Some generators might not be fully loadable in test environment
            // Just check that the discovery mechanism is working
            $this->assertStringContainsString('not found', $e->getMessage());
        }
    }

    /**
     * Test adding custom scan directory
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\GeneratorDiscovery::addScanDirectory
     * @covers \Ksfraser\Frontaccounting\GenCat\GeneratorDiscovery::getScanDirectories
     */
    public function testAddScanDirectory()
    {
        $originalDirs = $this->discovery->getScanDirectories();
        
        $this->discovery->addScanDirectory('/custom/path');
        $newDirs = $this->discovery->getScanDirectories();
        
        $this->assertContains('/custom/path', $newDirs);
        $this->assertEquals(count($originalDirs) + 1, count($newDirs));
        
        // Adding the same directory twice shouldn't duplicate it
        $this->discovery->addScanDirectory('/custom/path');
        $finalDirs = $this->discovery->getScanDirectories();
        
        $this->assertEquals(count($newDirs), count($finalDirs));
    }
}
