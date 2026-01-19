<?php

declare(strict_types=1);

namespace Ksfraser\ModulesDAO\Tests\Unit;

use Ksfraser\ModulesDAO\Stores\KeyValue\SuiteCrmAdministrationStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SuiteCrmAdministrationStoreTest extends TestCase
{
	public function testUnavailableSuiteCrmBehaviors(): void
	{
		$store = new SuiteCrmAdministrationStore('ksf');
		self::assertFalse($store->isAvailable());
		self::assertFalse($store->has('a'));
		self::assertSame('d', $store->get('a', 'd'));
		self::assertSame([], $store->all());

		$this->expectException(RuntimeException::class);
		$store->set('a', '1');
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCrudAndDeleteFallback(): void
	{
		require_once __DIR__ . '/GlobalStubs.php';
		$GLOBALS['__suite_settings'] = [];
		$GLOBALS['__suite_throw_delete'] = false;

		$store = new SuiteCrmAdministrationStore('ksf');
		self::assertTrue($store->isAvailable());

		self::assertFalse($store->has('a'));
		$store->set('a', '1');
		self::assertTrue($store->has('a'));
		self::assertSame('1', $store->get('a'));
		self::assertSame(['a' => '1'], $store->all());

		// Primary delete path
		$store->delete('a');
		self::assertFalse($store->has('a'));

		// Fallback delete path (simulate deleteSetting failure; should saveSetting empty)
		$store->set('b', '2');
		$GLOBALS['__suite_throw_delete'] = true;
		$store->delete('b');
		self::assertSame('', $store->get('b'));
	}
}
