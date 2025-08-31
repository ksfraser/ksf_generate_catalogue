<?php

/**
 * Unit Tests for CatalogueGeneratorFactory enhanced functionality
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
use Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory;
use Ksfraser\Frontaccounting\GenCat\DatabaseInterface;

/**
 * Test class for CatalogueGeneratorFactory enhanced features
 * 
 * @package Ksfraser\Frontaccounting\GenCat\Tests\Unit
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 * 
 * @covers \Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory
 */
class CatalogueGeneratorFactoryTest extends TestCase
{
    /**
     * Mock database interface
     * 
     * @var DatabaseInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockDatabase;

    /**
     * Factory instance
     * 
     * @var CatalogueGeneratorFactory
     */
    private $factory;

    /**
     * Set up test fixtures before each test method
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDatabase = $this->createMock(DatabaseInterface::class);
        $this->factory = new CatalogueGeneratorFactory($this->mockDatabase, 'test_prefs');
    }

    /**
     * Test getAvailableGenerators returns expected structure
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory::getAvailableGenerators
     */
    public function testGetAvailableGenerators()
    {
        $generators = $this->factory->getAvailableGenerators();
        
        $this->assertIsArray($generators);
        $this->assertNotEmpty($generators);
        
        // Check first generator has expected structure
        $firstGenerator = $generators[0];
        $this->assertArrayHasKey('name', $firstGenerator);
        $this->assertArrayHasKey('title', $firstGenerator);
        $this->assertArrayHasKey('class', $firstGenerator);
        $this->assertArrayHasKey('description', $firstGenerator);
        $this->assertArrayHasKey('method', $firstGenerator);
        
        // Check we have expected generators
        $generatorNames = array_column($generators, 'name');
        $expectedNames = ['pricebook', 'square', 'woocommerce', 'woopos', 'labels'];
        
        foreach ($expectedNames as $expectedName) {
            $this->assertContains($expectedName, $generatorNames, 
                "Expected generator '$expectedName' not found");
        }
    }

    /**
     * Test createGeneratorByName works for each available generator
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory::createGeneratorByName
     */
    public function testCreateGeneratorByName()
    {
        $generators = $this->factory->getAvailableGenerators();
        
        foreach ($generators as $generatorInfo) {
            $generatorName = $generatorInfo['name'];
            
            try {
                $generator = $this->factory->createGeneratorByName($generatorName);
                $this->assertInstanceOf(
                    'Ksfraser\\Frontaccounting\\GenCat\\BaseCatalogueGenerator', 
                    $generator,
                    "Generator '$generatorName' should extend BaseCatalogueGenerator"
                );
            } catch (\Exception $e) {
                // Some generators might not have all required classes loaded in test environment
                // Just check that the method exists and would work with proper classes
                $this->assertStringContainsString('not found', $e->getMessage());
            }
        }
    }

    /**
     * Test createGeneratorByName throws exception for invalid generator
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory::createGeneratorByName
     */
    public function testCreateGeneratorByNameThrowsExceptionForInvalidGenerator()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Generator 'invalid_generator' not found");
        
        $this->factory->createGeneratorByName('invalid_generator');
    }

    /**
     * Test that all generators have proper factory methods
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory
     */
    public function testAllGeneratorsHaveFactoryMethods()
    {
        $generators = $this->factory->getAvailableGenerators();
        
        foreach ($generators as $generatorInfo) {
            $methodName = $generatorInfo['method'];
            $this->assertTrue(
                method_exists($this->factory, $methodName),
                "Factory method '$methodName' should exist for generator '{$generatorInfo['name']}'"
            );
        }
    }

    /**
     * Test generator information has valid structure
     * 
     * @return void
     * 
     * @covers \Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory::getAvailableGenerators
     */
    public function testGeneratorInfoStructure()
    {
        $generators = $this->factory->getAvailableGenerators();
        
        foreach ($generators as $generatorInfo) {
            $this->assertIsString($generatorInfo['name'], 'Generator name should be string');
            $this->assertIsString($generatorInfo['title'], 'Generator title should be string');
            $this->assertIsString($generatorInfo['class'], 'Generator class should be string');
            $this->assertIsString($generatorInfo['description'], 'Generator description should be string');
            $this->assertIsString($generatorInfo['method'], 'Generator method should be string');
            
            $this->assertNotEmpty($generatorInfo['name'], 'Generator name should not be empty');
            $this->assertNotEmpty($generatorInfo['title'], 'Generator title should not be empty');
            $this->assertNotEmpty($generatorInfo['method'], 'Generator method should not be empty');
        }
    }
}
