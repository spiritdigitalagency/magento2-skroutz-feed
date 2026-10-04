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
            'additional_imageurl' => ['https://x.gr/1.jpg', 'https://x.gr/2.jpg'],
            'ean' => null,
            'specifications' => ['Material' => 'Canvas'],
            'variations' => [['variationid' => '13', 'size' => '42', 'quantity' => '0']],
        ]);
        $writer->close();

        $xml = simplexml_load_file($file);
        unlink($file);
        $product = $xml->products->product;
        $this->assertSame('2026-10-04 13:30', (string)$xml->created_at);
        $this->assertSame('Shoe & Co Red', (string)$product->name);
        $this->assertCount(2, $product->additional_imageurl);
        $this->assertCount(0, $product->ean);
        $this->assertCount(0, $product->_sku);
        $this->assertSame('Canvas', (string)$product->specifications->spec);
        $this->assertSame('Material', (string)$product->specifications->spec['name']);
        $this->assertSame('42', (string)$product->variations->variation->size);
        $this->assertSame('0', (string)$product->variations->variation->quantity);
    }
}
