<?php

declare(strict_types=1);

namespace Ksfraser\ModulesDAO\Tests\Unit;

use Ksfraser\ModulesDAO\Stores\KeyValue\FrontAccountingDbTableStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FrontAccountingDbTableStoreTest extends TestCase
{
	public function testUnavailableThrowsOnSetAndReturnsDefaults(): void
	{
		$store = new FrontAccountingDbTableStore('prefs');
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
	public function testCrudWithStubbedFaDb(): void
	{
		require_once __DIR__ . '/../../../FAMock/php/FaDbStubs.php';

		$store = new FrontAccountingDbTableStore('prefs');
		self::assertTrue($store->isAvailable());

		self::assertSame([], $store->all());
		$store->set('p.a', '1');
		$store->set('p.b', '2');
		$store->set('q.c', '3');

		self::assertTrue($store->has('p.a'));
		self::assertSame('2', $store->get('p.b'));
		self::assertSame(['p.a' => '1', 'p.b' => '2'], $store->all('p.'));

		$store->set('p.a', '9');
		self::assertSame('9', $store->get('p.a'));

		$store->delete('p.a');
		self::assertFalse($store->has('p.a'));
	}
}
