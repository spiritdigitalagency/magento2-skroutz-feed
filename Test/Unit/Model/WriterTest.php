<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Spirit\SkroutzFeed\Model\Writer;

class WriterTest extends TestCase
{
    public function testCleanRemovesWhatSkroutzRejects(): void
    {
        $writer = new Writer(['name' => 10]);

        $this->assertSame(
            "Title\nFirst & second\nItem",
            $writer->clean(
                'description',
                "<style>.a{color:red}</style><h2>Title</h2><p>First &amp; second</p>{{widget type=\"x\"}}"
                . "<ul><li>Item</li></ul><script>alert(1)</script>"
            )
        );
        $this->assertSame('One line', $writer->clean('name', "One\n  line\x01"));
        $this->assertSame('0123456789', $writer->clean('name', '0123456789 too long'));
        $this->assertSame('https://x.gr/a?b=1&c=2', $writer->clean('link', 'https://x.gr/a?b=1&c=2'));
    }

    public function testWritesProductsVariationsAndSpecifications(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'skroutz');
        $writer = new Writer();
        $writer->open($file, '2026-10-04 13:30');
        $writer->write([
            '_sku' => 'internal',
            'id' => '12-50',
            'name' => 'Shoe & Co <b>Red</b>',
            'link' => 'https://x.gr/shoe.html#93=50&144=42',
            'additional_image' => ['https://x.gr/1.jpg', 'https://x.gr/2.jpg'],
            'ean' => null,
            'mpn' => 'A]]>B & C',
            'specifications' => ['Material' => 'Canvas & leather', 'Sole' => 'Rubber'],
            'variations' => [['variationid' => '13', 'size' => '42', 'quantity' => '0']],
        ]);
        $writer->close();

        $raw = (string)file_get_contents($file);
        $xml = simplexml_load_file($file);
        unlink($file);
        $this->assertNotFalse($xml, 'well-formed XML');
        $product = $xml->products->product;
        $this->assertStringContainsString('<name><![CDATA[Shoe & Co Red]]></name>', $raw);
        $this->assertStringContainsString('<link><![CDATA[https://x.gr/shoe.html#93=50&144=42]]></link>', $raw);
        $this->assertStringContainsString('<id>12-50</id>', $raw, 'no CDATA where nothing needs it');
        $this->assertSame('A]]>B & C', (string)$product->mpn, '"]]>" survives a CDATA section');
        $this->assertSame('Canvas & leather', (string)$product->specifications->spec[0]);
        $this->assertSame('2026-10-04 13:30', (string)$xml->created_at);
        $this->assertSame('Shoe & Co Red', (string)$product->name);
        $this->assertCount(2, $product->additional_image);
        $this->assertCount(0, $product->ean);
        $this->assertCount(0, $product->_sku);
        $this->assertSame('Material', (string)$product->specifications->spec['name']);
        $this->assertSame('42', (string)$product->variations->variation->size);
        $this->assertSame('0', (string)$product->variations->variation->quantity);
    }
}
