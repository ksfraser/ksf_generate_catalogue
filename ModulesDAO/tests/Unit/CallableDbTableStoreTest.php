<?php

declare(strict_types=1);

namespace Ksfraser\ModulesDAO\Tests\Unit;

use Ksfraser\ModulesDAO\Stores\KeyValue\CallableDbTableStore;
use PHPUnit\Framework\TestCase;

final class CallableDbTableStoreTest extends TestCase
{
	public function testCrudAndPrefixQuery(): void
	{
		$table = [];
		$resultSets = [];
		$resultPos = [];

		$query = function (string $sql) use (&$table, &$resultSets, &$resultPos) {
			if (stripos($sql, 'SELECT') === 0) {
				$rows = array_values($table);

				if (preg_match("/WHERE\s+[^=]+\s*=\s*'([^']*)'/", $sql, $m)) {
					$k = stripslashes($m[1]);
					$rows = array_values(array_filter($rows, fn($r) => (string)($r['pref_name'] ?? '') === $k));
				} elseif (preg_match("/LIKE\s*'([^']*)'/", $sql, $m)) {
					$like = stripslashes($m[1]);
					$prefix = rtrim($like, '%');
					$rows = array_values(array_filter($rows, fn($r) => strncmp((string)($r['pref_name'] ?? ''), $prefix, strlen($prefix)) === 0));
				}

				$resultSets[$sql] = $rows;
				$resultPos[$sql] = 0;
				return $sql;
			}

			if (stripos($sql, 'INSERT') === 0) {
				if (preg_match("/VALUES\s*\('([^']*)',\s*'([^']*)'\)/", $sql, $m)) {
					$table[] = ['pref_name' => stripslashes($m[1]), 'pref_value' => stripslashes($m[2])];
				}
				return true;
			}

			if (stripos($sql, 'UPDATE') === 0) {
				if (preg_match("/SET\s+[^=]+\s*=\s*'([^']*)'\s+WHERE\s+[^=]+\s*=\s*'([^']*)'/", $sql, $m)) {
					$v = stripslashes($m[1]);
					$k = stripslashes($m[2]);
					foreach ($table as &$row) {
						if (($row['pref_name'] ?? null) === $k) {
							$row['pref_value'] = $v;
						}
					}
					unset($row);
				}
				return true;
			}

			if (stripos($sql, 'DELETE') === 0) {
				if (preg_match("/WHERE\s+[^=]+\s*=\s*'([^']*)'/", $sql, $m)) {
					$k = stripslashes($m[1]);
					$table = array_values(array_filter($table, fn($r) => (string)($r['pref_name'] ?? '') !== $k));
				}
				return true;
			}

			return true;
		};

		$fetch = function ($res) use (&$resultSets, &$resultPos) {
			$sql = (string)$res;
			$pos = $resultPos[$sql] ?? 0;
			$rows = $resultSets[$sql] ?? [];
			if ($pos >= count($rows)) {
				return false;
			}
			$resultPos[$sql] = $pos + 1;
			return $rows[$pos];
		};

		$escape = fn(string $v) => addslashes($v);
		$tablePrefix = fn() => 't_';

		$store = new CallableDbTableStore($query, $fetch, $escape, $tablePrefix, 'prefs');

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

	public function testUnavailableStoreIsNoop(): void
	{
		$noop = fn() => null;
		$store = new CallableDbTableStore($noop, $noop, fn($v) => (string)$v, fn() => '', 'prefs', 'pref_name', 'pref_value', false);

		self::assertFalse($store->has('a'));
		self::assertSame('d', $store->get('a', 'd'));
		self::assertSame([], $store->all());

		$store->set('a', '1');
		$store->delete('a');
		self::assertSame('d', $store->get('a', 'd'));
	}
}
