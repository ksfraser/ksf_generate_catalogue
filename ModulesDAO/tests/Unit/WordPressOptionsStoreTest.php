<?php

declare(strict_types=1);

namespace Ksfraser\ModulesDAO\Tests\Unit;

use Ksfraser\ModulesDAO\Stores\KeyValue\WordPressOptionsStore;
use PHPUnit\Framework\TestCase;

final class WordPressOptionsStoreTest extends TestCase
{
	public function testIsUnavailableWithoutWordPress(): void
	{
		$store = new WordPressOptionsStore('p_');
		self::assertFalse($store->isAvailable());
		self::assertFalse($store->has('a'));
		self::assertSame('d', $store->get('a', 'd'));
		self::assertSame([], $store->all());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCrudWhenWordPressFunctionsPresent(): void
	{
		require_once __DIR__ . '/GlobalStubs.php';
		$GLOBALS['__wp_options'] = [];

		$store = new WordPressOptionsStore('p_');
		self::assertTrue($store->isAvailable());

		self::assertFalse($store->has('a'));
		$store->set('a', '1');
		self::assertTrue($store->has('a'));
		self::assertSame('1', $store->get('a'));

		$store->delete('a');
		self::assertFalse($store->has('a'));

		// all() intentionally does not enumerate
		self::assertSame([], $store->all());
	}
}
