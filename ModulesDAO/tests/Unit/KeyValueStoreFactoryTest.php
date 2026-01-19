<?php

declare(strict_types=1);

namespace Ksfraser\ModulesDAO\Tests\Unit;

use Ksfraser\ModulesDAO\Factory\KeyValueStoreFactory;
use Ksfraser\ModulesDAO\Stores\KeyValue\CsvFileStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\IniFileStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\JsonFileStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\PdoTableStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\XmlFileStore;
use PHPUnit\Framework\TestCase;
use PDO;
use RuntimeException;

final class KeyValueStoreFactoryTest extends TestCase
{
	public function testCreatesFileStoresAndAlias(): void
	{
		$factory = new KeyValueStoreFactory();

		self::assertInstanceOf(IniFileStore::class, $factory->create(['type' => 'ini_file', 'path' => 'x.ini']));
		self::assertInstanceOf(JsonFileStore::class, $factory->create(['type' => 'json_file', 'path' => 'x.json']));
		self::assertInstanceOf(CsvFileStore::class, $factory->create(['type' => 'csv_file', 'path' => 'x.csv']));
		self::assertInstanceOf(XmlFileStore::class, $factory->create(['type' => 'xml_file', 'path' => 'x.xml']));
		self::assertInstanceOf(XmlFileStore::class, $factory->create(['type' => 'sgml_file', 'path' => 'x.sgml']));
	}

	public function testUnknownTypeThrows(): void
	{
		$factory = new KeyValueStoreFactory();

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Unknown key/value store type');
		$factory->create(['type' => 'nope']);
	}

	public function testPdoTableRequiresDsnAndTable(): void
	{
		$factory = new KeyValueStoreFactory();

		$this->expectException(RuntimeException::class);
		$factory->create(['type' => 'pdo_table', 'dsn' => 'sqlite::memory:']);
	}

	public function testPdoTableStoreViaFactoryWorks(): void
	{
		$factory = new KeyValueStoreFactory();

		$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ksf_modulesdao_' . bin2hex(random_bytes(4)) . '.sqlite';
		$dsn = 'sqlite:' . $tmp;

		$pdo = new PDO($dsn);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->exec('CREATE TABLE prefs (pref_name TEXT PRIMARY KEY, pref_value TEXT)');

		$store = $factory->create([
			'type' => 'pdo_table',
			'dsn' => $dsn,
			'table' => 'prefs',
			'name_col' => 'pref_name',
			'value_col' => 'pref_value',
		]);

		self::assertInstanceOf(PdoTableStore::class, $store);
		$store->set('a', '1');
		self::assertSame('1', $store->get('a'));

		@unlink($tmp);
	}
}
