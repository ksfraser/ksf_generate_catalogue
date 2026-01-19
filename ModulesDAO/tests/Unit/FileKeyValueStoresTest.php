<?php

declare(strict_types=1);

namespace Ksfraser\ModulesDAO\Tests\Unit;

use Ksfraser\ModulesDAO\Stores\KeyValue\CsvFileStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\IniFileStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\JsonFileStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\XmlFileStore;
use Ksfraser\ModulesDAO\Stores\KeyValue\YamlFileStore;
use PHPUnit\Framework\TestCase;

final class FileKeyValueStoresTest extends TestCase
{
	/** @var string[] */
	private array $paths = [];

	private function tempPath(string $name): string
	{
		$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ksf_modulesdao_' . bin2hex(random_bytes(4));
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

	public function testIniStoreRoundTripAndPrefix(): void
	{
		$path = $this->tempPath('prefs.ini');
		$store = new IniFileStore($path, 'prefs');

		self::assertTrue($store->isAvailable());
		self::assertFalse($store->has('a'));
		self::assertSame('d', $store->get('a', 'd'));

		$store->set('a', '1');
		$store->set('p.x', 'X');
		self::assertTrue($store->has('a'));
		self::assertSame('1', $store->get('a'));
		self::assertSame(['p.x' => 'X'], $store->all('p.'));

		$store->delete('a');
		self::assertFalse($store->has('a'));
	}

	public function testJsonStoreDecodeInvalidIsEmpty(): void
	{
		$path = $this->tempPath('prefs.json');
		file_put_contents($path, '{not-json');

		$store = new JsonFileStore($path);
		self::assertSame([], $store->all());
	}

	public function testJsonStoreRoundTrip(): void
	{
		$path = $this->tempPath('prefs.json');
		$store = new JsonFileStore($path);

		$store->set('a', '1');
		$store->set('b', '2');
		self::assertSame('1', $store->get('a'));
		self::assertSame(['a' => '1', 'b' => '2'], $store->all());
	}

	public function testCsvStoreHandlesHeaderAndNoHeader(): void
	{
		$path = $this->tempPath('prefs.csv');

		// No header variant
		file_put_contents($path, "k1,v1\n");
		$store = new CsvFileStore($path);
		self::assertSame(['k1' => 'v1'], $store->all());

		// Now exercise encode path (writes header)
		$store->set('k2', 'v2');
		self::assertSame('v2', $store->get('k2'));
		self::assertSame(['k1' => 'v1', 'k2' => 'v2'], $store->all());
	}

	public function testXmlStoreEmptyFileIsEmpty(): void
	{
		$path = $this->tempPath('prefs.xml');
		file_put_contents($path, "\n\n");

		$store = new XmlFileStore($path);
		self::assertSame([], $store->all());
	}

	public function testXmlStoreRoundTripAndSkipsMissingKeys(): void
	{
		$path = $this->tempPath('prefs.xml');
		$store = new XmlFileStore($path);
		$store->set('a', '1');

		// Add a malformed pref node without key attribute to cover skip branch.
		file_put_contents($path, "<prefs><pref>no-key</pref><pref key=\"a\">1</pref></prefs>");
		self::assertSame(['a' => '1'], $store->all());
	}

	public function testYamlStoreRoundTripWhenExtensionAvailable(): void
	{
		$path = $this->tempPath('prefs.yaml');
		$store = new YamlFileStore($path);

		if (!$store->isAvailable()) {
			self::assertSame([], $store->all());
			return;
		}

		$store->set('a', '1');
		self::assertSame('1', $store->get('a'));
	}
}
