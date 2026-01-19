<?php

declare(strict_types=1);

namespace Ksfraser\ModulesDAO\Tests\Unit;

use Ksfraser\ModulesDAO\Codec\ValueCodec;
use PHPUnit\Framework\TestCase;

final class ValueCodecTest extends TestCase
{
	public function testEncodeScalarsAndNull(): void
	{
		self::assertSame('1', ValueCodec::encode(true));
		self::assertSame('0', ValueCodec::encode(false));
		self::assertSame('', ValueCodec::encode(null));
		self::assertSame('123', ValueCodec::encode(123));
		self::assertSame('12.5', ValueCodec::encode(12.5));
		self::assertSame('hello', ValueCodec::encode('hello'));
	}

	public function testEncodeJsonAndDecodeJson(): void
	{
		$raw = ValueCodec::encode(['a' => 1, 'b' => 'x']);
		self::assertStringStartsWith('__json__:', $raw);
		self::assertSame(['a' => 1, 'b' => 'x'], ValueCodec::decode($raw));
	}

	public function testDecodeNullRawReturnsDefault(): void
	{
		self::assertSame('d', ValueCodec::decode(null, 'd'));
	}

	public function testDecodeInvalidJsonReturnsDefault(): void
	{
		self::assertSame(['fallback'], ValueCodec::decode('__json__:{not-json}', ['fallback']));
	}

	public function testDecodeJsonNullReturnsNull(): void
	{
		self::assertNull(ValueCodec::decode('__json__:null', 'fallback'));
	}

	public function testDecodeRawStringReturnsRaw(): void
	{
		self::assertSame('abc', ValueCodec::decode('abc', 'fallback'));
	}
}
