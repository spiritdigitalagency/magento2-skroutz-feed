<?php
/**
 * Copyright © Spirit Digital Agency. All rights reserved.
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Spirit\SkroutzFeed\Model;

use Magento\Framework\Exception\LocalizedException;

/**
 * Streams feed rows to an XML file, so memory does not grow with the catalog.
 *
 * Every text is cleaned to what Skroutz accepts: no HTML, no characters that are invalid in XML,
 * and no longer than the maximum length of its element.
 */
class Writer
{
    /** Elements holding URLs: never stripped or truncated */
    private const URLS = ['link', 'image', 'additional_image'];

    /** Elements that keep their line breaks */
    private const MULTILINE = ['description'];

    /**
     * @var \XMLWriter|null
     */
    private $xml;

    /**
     * @var array<string, int>
     */
    private $maxLengths;

    /**
     * @param int[] $maxLengths element name => maximum length, from the Skroutz specification
     */
    public function __construct(array $maxLengths = [])
    {
        $this->maxLengths = $maxLengths;
    }

    /**
     * Start a feed file.
     *
     * @param string $path absolute path
     * @param string $createdAt e.g. "2026-10-04 13:30"
     * @return void
     * @throws LocalizedException when the file cannot be created
     */
    public function open(string $path, string $createdAt): void
    {
        $xml = new \XMLWriter();
        if (!$xml->openUri($path)) {
            throw new LocalizedException(__('Cannot write the feed file %1.', $path));
        }
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('mywebstore');
        $xml->writeElement('created_at', $createdAt);
        $xml->startElement('products');
        $this->xml = $xml;
    }

    /**
     * Write one <product>.
     *
     * @param mixed[] $row element name => value
     * @return void
     */
    public function write(array $row): void
    {
        $this->xml()->startElement('product');
        $this->writeFields($row);
        $this->xml()->endElement();
    }

    /**
     * Write the buffered XML to the file.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->xml()->flush();
    }

    /**
     * Close the root elements and the file.
     *
     * @return void
     */
    public function close(): void
    {
        $this->xml()->endElement();
        $this->xml()->endElement();
        $this->xml()->endDocument();
        $this->xml()->flush();
        $this->xml = null;
    }

    /**
     * Close the file without finishing the document, after a failure.
     *
     * @return void
     */
    public function abort(): void
    {
        // Releasing the writer closes the file; what it holds is a temporary file deleted by the caller
        $this->xml = null;
    }

    /**
     * The open feed file.
     *
     * @return \XMLWriter
     * @throws LocalizedException when no file is open
     */
    private function xml(): \XMLWriter
    {
        if ($this->xml === null) {
            throw new LocalizedException(__('The feed file is not open.'));
        }

        return $this->xml;
    }

    /**
     * Clean a text for an element.
     *
     * @param string $name element name
     * @param mixed $value
     * @return string
     */
    public function clean(string $name, $value): string
    {
        $value = (string)$value;
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        if (!in_array($name, self::URLS, true)) {
            if (strpbrk($value, '<&{') !== false) {
                $value = preg_replace(
                    [
                        '#<(script|style)\b[^>]*>.*?</\1\s*>#is',
                        '#\{\{.*?\}\}#s',
                        '#<(br|/p|/div|/li|/h[1-6]|/tr)\b[^>]*>#i',
                    ],
                    ['', '', "\n"],
                    $value
                );
                // phpcs:ignore Magento2.Functions.DiscouragedFunction -- decoding to plain text, not output to HTML
                $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $value = in_array($name, self::MULTILINE, true)
                ? preg_replace(['/[^\S\n]+/u', '/\s*\n\s*/u'], [' ', "\n"], $value)
                : preg_replace('/\s+/u', ' ', $value);
        }
        $value = trim((string)preg_replace(
            '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
            '',
            (string)$value
        ));
        if (isset($this->maxLengths[$name]) && mb_strlen($value) > $this->maxLengths[$name]) {
            $value = rtrim(mb_substr($value, 0, $this->maxLengths[$name]));
        }

        return $value;
    }

    /**
     * Write the elements of a product or variation.
     *
     * @param mixed[] $row
     * @return void
     */
    private function writeFields(array $row): void
    {
        foreach ($row as $name => $value) {
            if ($name === '' || $name[0] === '_' || $value === null || $value === '' || $value === []) {
                continue;
            }
            if ($name === 'variations') {
                $this->xml()->startElement('variations');
                foreach ($value as $variation) {
                    $this->xml()->startElement('variation');
                    $this->writeFields($variation);
                    $this->xml()->endElement();
                }
                $this->xml()->endElement();
            } elseif ($name === 'specifications') {
                $this->xml()->startElement('specifications');
                foreach ($value as $label => $text) {
                    $this->xml()->startElement('spec');
                    $this->xml()->writeAttribute('name', $this->clean('spec_name', $label));
                    $this->writeText($this->clean('spec', $text));
                    $this->xml()->endElement();
                }
                $this->xml()->endElement();
            } else {
                foreach ((array)$value as $text) {
                    $text = $this->clean($name, $text);
                    if ($text !== '') {
                        $this->xml()->startElement($name);
                        $this->writeText($text);
                        $this->xml()->endElement();
                    }
                }
            }
        }
    }

    /**
     * Element text, in a CDATA section when it holds &, < or > (a link with parameters, "Black & White").
     *
     * @param string $text
     * @return void
     */
    private function writeText(string $text): void
    {
        if (strpbrk($text, '&<>') === false) {
            $this->xml()->text($text);
            return;
        }
        // "]]>" would end the section early: split it across two sections
        $this->xml()->writeCdata(str_replace(']]>', ']]]]><![CDATA[>', $text));
    }
}
