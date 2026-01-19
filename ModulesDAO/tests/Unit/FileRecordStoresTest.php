<?php

declare(strict_types=1);

namespace Ksfraser\ModulesDAO\Tests\Unit;

use Ksfraser\ModulesDAO\Stores\Record\AbstractFileRecordStore;
use Ksfraser\ModulesDAO\Stores\Record\CsvRecordStore;
use Ksfraser\ModulesDAO\Stores\Record\XmlRecordStore;
use PHPUnit\Framework\TestCase;

final class FileRecordStoresTest extends TestCase
{
	/** @var string[] */
	private array $paths = [];

	private function tempPath(string $name): string
	{
		$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ksf_modulesdao_records_' . bin2hex(random_bytes(4));
		@mkdir($dir, 0775, true);
		$path = $dir . DIRECTORY_SEPARATOR . $name;
		$this->paths[] = $path;
		return $path;
	}

	protected function tearDown(): void
	{
		foreach (array_reverse($this->paths) as $path) {
			@unlink($path);
			$dir = dirname($path);
			@rmdir($dir);
		}
		$this->paths = [];
	}

	public function testAbstractFileRecordStoreReadWriteHelpers(): void
	{
		$path = $this->tempPath('store.txt');

		$store = new class($path) extends AbstractFileRecordStore {
			public function readPublic(): string { return $this->readFileContents(); }
			public function writePublic(string $c): void { $this->writeFileContents($c); }
			public function find(string $id): ?array { return null; }
			public function findAll(array $filters = []): array { return []; }
			public function insert(array $record): string { return ''; }
			public function update(string $id, array $record): void {}
			public function delete(string $id): void {}
		};

		self::assertTrue($store->isAvailable());
		self::assertSame('', $store->readPublic());

		$store->writePublic("hello");
		self::assertSame('hello', $store->readPublic());
	}

	public function testCsvRecordStoreFindAndFindAllFilters(): void
	{
		$path = $this->tempPath('rows.csv');
		file_put_contents($path, "id,name\n1,Alice\n2,Bob\n3,Bob\n");

		$store = new CsvRecordStore($path, 'id');
		self::assertSame(['id' => '2', 'name' => 'Bob'], $store->find('2'));
		self::assertNull($store->find('nope'));

		$all = $store->findAll();
		self::assertCount(3, $all);

		$filtered = $store->findAll(['name' => 'Bob']);
		self::assertCount(2, $filtered);

		// Scaffold methods: execute for coverage
		self::assertSame('', $store->insert(['id' => '4', 'name' => 'Z']));
		$store->update('1', ['name' => 'A']);
		$store->delete('1');
	}

	public function testXmlRecordStoreScaffoldMethods(): void
	{
		$path = $this->tempPath('rows.xml');
		$store = new XmlRecordStore($path);

		self::assertTrue($store->isAvailable());
		self::assertNull($store->find('1'));
		self::assertSame([], $store->findAll());
		self::assertSame('', $store->insert(['a' => 'b']));
		$store->update('1', ['a' => 'b']);
		$store->delete('1');
	}
}
