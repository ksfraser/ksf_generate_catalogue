<?php

declare(strict_types=1);

namespace Ksfraser\ModulesDAO\Tests\Unit;

use Ksfraser\ModulesDAO\Stores\KeyValue\FrontAccountingSysPrefsStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FrontAccountingSysPrefsStoreTest extends TestCase
{
	public function testUnavailableBehaviors(): void
	{
		$store = new FrontAccountingSysPrefsStore();
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
	public function testUsesGetCompanyPrefAndSetCompanyPrefWhenPresent(): void
	{
		require_once __DIR__ . '/GlobalStubs.php';
		$GLOBALS['__fa_prefs'] = [];

		$store = new FrontAccountingSysPrefsStore();
		self::assertTrue($store->isAvailable());

		$store->set('a', '1');
		self::assertSame('1', $store->get('a'));
		self::assertTrue($store->has('a'));

		$store->delete('a');
		self::assertSame('', $store->get('a', ''));
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testUsesUpdateCompanyPrefsWhenSetCompanyPrefMissing(): void
	{
		require_once __DIR__ . '/../../../FAMock/php/FaUpdateOnlyStubs.php';

		$store = new FrontAccountingSysPrefsStore();
		self::assertTrue($store->isAvailable());

		$store->set('a', '1');
		self::assertSame(['a' => '1'], $store->all());
		self::assertSame('1', $store->get('a'));
	}
}
