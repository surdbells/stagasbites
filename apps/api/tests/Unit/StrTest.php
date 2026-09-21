<?php

declare(strict_types=1);

namespace StagasBites\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StagasBites\Helper\Str;

final class StrTest extends TestCase
{
    public function testSlug(): void
    {
        self::assertSame('asun-spicy-roast-goat-meat', Str::slug('Asun (Spicy Roast Goat Meat)'));
        self::assertSame('snails-spicy-peppered', Str::slug('Snails: Spicy / Peppered'));
    }

    public function testMoney(): void
    {
        self::assertSame('$105.00', Str::money(10500));
        self::assertSame('$1,234.50', Str::money(123450, 'CAD'));
    }

    public function testEscapeNeutralisesMarkup(): void
    {
        self::assertSame('&lt;script&gt;&quot;x&quot;&#039;', Str::e('<script>"x"\''));
    }

    public function testTruncateStripsTagsAndAddsEllipsis(): void
    {
        self::assertSame('Hello world', Str::truncate('<b>Hello</b>   world', 50));
        self::assertSame('Hello…', Str::truncate('Hello world', 6));
    }
}
