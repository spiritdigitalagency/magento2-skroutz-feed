# Skroutz XML Feed for Magento 2

Generates the [Skroutz XML feed](https://developer.skroutz.gr/products/xml_feed/) of your Magento 2 store.

* One feed per website, each with its own settings, store view and stock (MSI salable quantities).
* Map every Skroutz field to any product attribute, or to a fixed value, from the admin.
* Configurable products the way Skroutz wants them: one product per color, sizes nested as `<variations>`.
  Each product type can have its own size attribute (shoe size, clothing size...).
* Availability text for in stock, backorder and out of stock products, each one can also hide the product,
  with per product overrides.
* Specifications (`<specifications>`) from the attributes shown on the product page, or a selection.
* Built for large catalogs: memory stays flat, one query per batch for stock, prices, categories, images and URLs.
* A health report after every run: products missing EAN, MPN, manufacturer, image..., with example SKUs.
* Works with [Skroutz Analytics](https://github.com/spiritdigitalagency/magento2-skroutz-analytics): shows what
  Analytics sends and warns when orders cannot be matched to feed products.
* Compatible with Magento Open Source and Adobe Commerce 2.4.0 – 2.4.9, PHP 7.4 – 8.5, with the `xmlwriter`
  and `zlib` extensions of standard PHP builds. MSI is optional.
* A feed online is only ever replaced by a complete, checked one.

## Installation

```
composer require spirit-digital-agency/magento2-skroutz-feed
php bin/magento setup:upgrade
php bin/magento cache:flush
```

In production mode also run `setup:di:compile` and `setup:static-content:deploy`.

## Configuration

**Stores > Configuration > Spirit > Skroutz**, at default or website scope. If Skroutz Analytics is installed, its
settings are on the same page.

| Group | Settings |
|---|---|
| XML Feed | Enable per website, store view (language, names, links), file name, schedule, Safety Check, feed URLs, last report, "Generate now" |
| Field Mapping | Unique ID and Variation ID, the attribute (or fixed value) of every Skroutz field, color and size attributes, shipping cost, specifications |
| Products and Variations | Exclude products without image (off by default), category filter (exclude or include only), one product per color |
| Availability and Stock | Availability text (or "Hide from Skroutz") for in stock, backorder and out of stock products |

Per product, in the "Skroutz" group of the product form, at website scope:

* **Exclude from Skroutz**: the product is not listed.
* **Skroutz Availability: In Stock / On Backorder / Out of Stock**: overrides the availability of that stock
  state, "Hide from Skroutz" included. Children of configurable products inherit the values of their parent.

All of them can be set in bulk with **Catalog > Products > Actions > Update attributes**.

The feed is written to `pub/media/skroutz/<website code>.xml` and `.xml.gz`. Give Skroutz the `.gz` URL when the
feed is larger than 10MB. The admin shows both URLs.

### What is listed

Simple and configurable products that are enabled, visible and in the website. Bundle, grouped, virtual and
downloadable products are not: Skroutz cannot list them correctly. A simple product that is a child of a
configurable appears only as its variation, never on its own: when the configurable is disabled or hidden, its
children are not listed either.

| The configurable varies by | Feed |
|---|---|
| A size attribute and color (or other options) | one product per color, Unique ID `<parent>-<option id>`, sizes in `<variations>` |
| A size attribute only | one product with the parent's Unique ID, sizes in `<variations>` |
| No size attribute (color only, capacity...) | one product per child, with the child's Unique ID and the parent's details where the child has none |

**Images**: a color takes the images of its children; a child listed on its own, its own images. Where the child
has none, the parent's are used, and the other way round for a configurable without images of its own.

The option id is the id of the attribute option (e.g. the color "Red"), not its label: renaming a color in the
admin keeps the Unique ID. Each size in `<variations>` has the child's "Variation ID", by default the same
attribute as the Unique ID; a shop moving from another feed keeps the one that feed used for sizes (often the
child's SKU), so Skroutz sees no size as new. With "Do Not Nest a Single Size", "one size" items set up as configurable products are
written as plain products, without `<variations>`.

Links of colors and sizes preselect the option on the product page (`product.html#93=50`). "Variant Name" sets
how color rows and listed children are named: `T-shirt Red M`, `T-shirt Color: Red, Size: M` or just `T-shirt`.

**Prices** are the final prices of the price index (special prices and catalog price rules included) for guests,
with VAT. Out of stock products, which Magento leaves out of the index when it hides them, fall back to their
price or active special price.

**Stock**: with MSI, the salable quantity of the stock assigned to the website (stock index plus reservations of
unshipped orders). Without MSI, the stock item. Both minus the "Out-of-Stock Threshold".

**Shipping cost**: an attribute, a fixed value, or calculated: a cost up to a weight, a cost per extra kilo, free
above a price.

### Specifications

Skroutz takes extra characteristics as `<specifications><spec name="Label">value</spec></specifications>`.

* **Attributes shown on the product page** (default): the attributes with "Visible on Catalog Pages on Storefront",
  the same ones the "More Information" tab shows. You manage them in Stores > Attributes > Product, and each
  product only gets those of its attribute set that have a value.
* **Attributes shown on the product page, except the selected**: the same, minus a list.
* **Only the selected attributes**: a list of any attributes.

Skroutz does not define the names: they are free key-value pairs, used to enrich its catalogue. The feed uses the
attribute labels of its store view, the names customers already see. Attributes mapped to a Skroutz field
(manufacturer, color, size...) are left out, as Skroutz ignores them in specifications. A developer can rename
specifications or add computed ones (see below).

### Generation

Magento cron generates the feeds of every enabled website at the "Schedule" setting, a cron expression in the time
zone of the store. The default, `50 6-23 * * *`, generates them at 06:50, 07:50 ... 23:50, so a fresh feed is ready
before every full hour from 07:00 to midnight. The jobs run in their own cron group (`spirit_skroutzfeed`), in a
separate process, so a long run never delays the other cron jobs. "Generate now" asks cron to start within a
minute. From the command line:

```
php bin/magento spirit:skroutz:feed [--website=base] [--force]
```

The feed online is only ever replaced by a complete, checked one:

1. The `.xml` and `.xml.gz` are written under temporary names next to the feed online.
2. Both are read back in full: they must be well-formed XML holding every product written. A full disk or a
   killed process fails here, or never gets this far.
3. **Safety Check**: a new feed with more than the set percentage fewer products than the one online (50% by
   default) is not published. `--force` publishes it anyway, for an expected drop.
4. Only then are both renamed over the feed online, which is atomic: Skroutz downloads either the old file or the
   new one, never a mix.

Any failure keeps the previous feed online, removes the temporary files and adds an admin notification; the
report shows the error. Two websites can never write to the same file. `<created_at>` at the top of the feed is
the time of the generation, in the store's time zone. While a long generation runs, Magento may log
`Could not acquire lock for cron job: spirit_skroutzfeed_requests`: that is the next minute's check for
"Generate now" waiting, as intended.

## Skroutz Analytics

Skroutz matches each ordered item that Analytics reports to the feed product with the same Unique ID. The feed's
Unique ID is its own setting, the Magento product ID by default. The admin shows what Analytics sends under it,
and the feed section warns when the two cannot match:

* a different Unique ID attribute in the feed and in Analytics;
* Analytics sending the variation ID while the feed lists configurables under the parent;
* colors listed as `<parent>-<color>`, an ID Analytics does not send yet;
* a feed name taken from another attribute than the product name.

## For developers

**Change the Unique ID.** Every ID of the feed comes from `Spirit\SkroutzFeed\Model\UniqueId`: `get()` for
products, `getForVariant()` for colors and `getForVariation()` for sizes. Add a plugin:

```xml
<type name="Spirit\SkroutzFeed\Model\UniqueId">
    <plugin name="vendor_skroutz_id" type="Vendor\Module\Plugin\SkroutzId"/>
</type>
```

```php
class SkroutzId
{
    public function afterGet(\Spirit\SkroutzFeed\Model\UniqueId $subject, string $id, $product): string
    {
        return 'M' . $id;
    }
}
```

Skroutz Analytics must then send the same ID with orders.

**Rename or add specifications.** Rename by attribute code in `di.xml`:

```xml
<type name="Spirit\SkroutzFeed\Model\Field\Specifications">
    <arguments>
        <argument name="labels" xsi:type="array">
            <item name="erp_material" xsi:type="string">Υλικό</item>
        </argument>
    </arguments>
</type>
```

Add specifications computed per product, or drop some, with a plugin:

```php
class SkroutzSpecs
{
    public function afterGetValue(\Spirit\SkroutzFeed\Model\Field\Specifications $subject, $specs, $product)
    {
        $specs = $specs ?? [];
        $specs['Συμβατότητα'] = implode(', ', $this->compatibility->forProduct($product));
        return $specs ?: null;
    }
}
```

**Add or change a field.** Feed elements are registered on `Spirit\SkroutzFeed\Model\RowBuilder` in `di.xml`, keyed
by element name. A field implements `Spirit\SkroutzFeed\Model\Field\FieldInterface`:

```xml
<type name="Spirit\SkroutzFeed\Model\RowBuilder">
    <arguments>
        <argument name="fields" xsi:type="array">
            <item name="shipping" xsi:type="object">Vendor\Module\Feed\Shipping</item>
        </argument>
    </arguments>
</type>
```

```php
class Shipping implements \Spirit\SkroutzFeed\Model\Field\FieldInterface
{
    public function getValue(\Magento\Catalog\Model\Product $product)
    {
        return $product->getWeight() > 2 ? '4.50' : '2.90'; // null leaves the element out
    }
}
```

Products carry the data loaded for them: `skroutz_stock` (qty, in_stock, managed, backorders),
`skroutz_category_ids`, `skroutz_gallery`, `skroutz_parent` (children of configurables), `request_path`,
`final_price` and `tax_class_id`.

**Events.**

| Event | Data | Use |
|---|---|---|
| `spirit_skroutzfeed_collection` | `collection`, `store` | Filter products or select more attributes, before each batch is loaded |
| `spirit_skroutzfeed_row` | `transport` (`row`, `skip`), `product`, `store` | Change any value of a feed product, or `setSkip(true)` |
| `spirit_skroutzfeed_generate_after` | `website`, `store`, `report` | Upload the feed somewhere, notify someone |

**Other extension points.**

* `Spirit\SkroutzFeed\Model\ProductLoader` argument `productTypes` (default `simple`, `configurable`).
* `Spirit\SkroutzFeed\Model\Generator` arguments `batchSize` (500) and `maxChildren` (2000 configurable children in
  memory at once).
* `Spirit\SkroutzFeed\Model\Writer` argument `maxLengths`.
* `Spirit\SkroutzFeed\Model\Stock::load()`, `Spirit\SkroutzFeed\Model\Stock::getState()`.

## Uninstall

```
php bin/magento module:uninstall Spirit_SkroutzFeed
```

Removes the "Exclude from Skroutz" and "Skroutz Availability" attributes. Delete `pub/media/skroutz` by hand.

## Support

support@spiritdigital.agency
