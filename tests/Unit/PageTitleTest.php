<?php

namespace Tests\Unit;

use App\Support\PageTitle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PageTitleTest extends TestCase
{
    #[DataProvider('legacyTitleProvider')]
    public function test_it_normalizes_legacy_title_formats(string $rawTitle, string $expectedTitle): void
    {
        $this->assertSame($expectedTitle, PageTitle::format($rawTitle));
    }

    public static function legacyTitleProvider(): array
    {
        return [
            'legacy pipe suffix' => ['GGWP Boost | FAQ', 'FAQ | GGWP-Boost'],
            'legacy dash suffix' => ['GGWP Boost - Contact Us', 'Contact Us | GGWP-Boost'],
            'legacy lowercase brand' => ['ggwp | Signup', 'Signup | GGWP-Boost'],
            'brand already trailing' => ['Reviews | GGWP-Boost', 'Reviews | GGWP-Boost'],
        ];
    }

    public function test_it_falls_back_to_the_brand_when_no_label_exists(): void
    {
        $this->assertSame('GGWP-Boost', PageTitle::format(null));
    }
}
