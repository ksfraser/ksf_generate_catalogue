<?php

declare(strict_types=1);

namespace Ksfraser\ModulesDAO\Tests\Unit;

use Ksfraser\ModulesDAO\Stores\KeyValue\PdoTableStore;
use PHPUnit\Framework\TestCase;
use PDO;

final class PdoTableStoreTest extends TestCase
{
	private function makeStore(): array
	{
		$pdo = new PDO('sqlite::memory:');
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->exec('CREATE TABLE prefs (pref_name TEXT PRIMARY KEY, pref_value TEXT)');
		return [$pdo, new PdoTableStore($pdo, 'prefs')];
	}

	public function testCrudAndAllPrefix(): void
	{
		[$pdo, $store] = $this->makeStore();

		self::assertFalse($store->has('a'));
		self::assertSame('d', $store->get('a', 'd'));

		$store->set('p.a', '1');
		$store->set('p.b', '2');
		$store->set('q.c', '3');

		self::assertTrue($store->has('p.a'));
		self::assertSame('2', $store->get('p.b'));
		self::assertSame(['p.a' => '1', 'p.b' => '2'], $store->all('p.'));

		// Update path
		$store->set('p.a', '9');
		self::assertSame('9', $store->get('p.a'));

		$store->delete('p.a');
		self::assertFalse($store->has('p.a'));
	}
}
