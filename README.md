![GitHub all releases](https://img.shields.io/github/downloads/spiritdigitalagency/magento2-skroutz-feed/total?style=for-the-badge)
![GitHub Release Date](https://img.shields.io/github/release-date/spiritdigitalagency/magento2-skroutz-feed?style=for-the-badge)
![Packagist Version](https://img.shields.io/packagist/v/spirit-digital-agency/magento2-skroutz-feed?style=for-the-badge)
![GitHub](https://img.shields.io/github/license/spiritdigitalagency/magento2-skroutz-feed?style=for-the-badge)

# Skroutz XML Feed Magento 2 Module

Generate the Skroutz XML feed of your Magento 2 store.

This module generates the [Skroutz XML feed](https://developer.skroutz.gr/products/xml_feed/) of every website of
a [Magento 2](https://magento.com/) store, ready to give to Skroutz and Skroutz Marketplace.

* One feed per website, each with its own settings, store view and stock (MSI salable quantities).
* Map every Skroutz field to any product attribute, or to a fixed value, from the admin.
* Configurable products the way Skroutz wants them, with one product per color and the sizes nested as
  `<variations>`. Each product type can have its own size attribute (shoe size, clothing size...).
* Availability text for in stock, backorder and out of stock products, each of which can also hide the product,
  with per product overrides.
* Specifications (`<specifications>`) from the attributes shown on the product page, or a selection.
* Generated on a schedule, by default every hour from 06:50 to 23:50, or on demand from the admin.
* A new feed only replaces the one online when it is complete and checked, so a failed run never leaves Skroutz
  a broken file.
* Built for large catalogs.
* A health report after every run, with the products missing EAN, MPN, manufacturer or image and example SKUs.
* Works with [Skroutz Analytics](https://github.com/spiritdigitalagency/magento2-skroutz-analytics). It shows what
  Analytics sends and warns when orders cannot be matched to feed products.
* Compatible with Magento Open Source and Adobe Commerce 2.4.2 to 2.4.9, installed with Composer 2
* Compatible with PHP 7.4, 8.1, 8.2, 8.3, 8.4 and 8.5, with the `xmlwriter` and `zlib` extensions of standard
  PHP builds. MSI is optional.

The module is available from
the [Github repo](https://github.com/spiritdigitalagency/magento2-skroutz-feed).

## Installation

### Install via composer (recommended)

We recommend you to install Spirit_SkroutzFeed module via composer. It is easy to install, update and maintain.

Run the following command in Magento 2 root folder.

#### Install

```
composer require spirit-digital-agency/magento2-skroutz-feed
php bin/magento setup:upgrade
php bin/magento cache:flush
```

#### Upgrade

```
composer update spirit-digital-agency/magento2-skroutz-feed
php bin/magento setup:upgrade
php bin/magento cache:flush
```

In production mode also run `php bin/magento setup:di:compile`.

### Manual

If you don't want to install via composer, you can use this way.

- Download [the latest version here](https://github.com/spiritdigitalagency/magento2-skroutz-feed/archive/master.zip)
- Extract `master.zip` file to `app/code/Spirit/SkroutzFeed`. You should create a folder path
  `app/code/Spirit/SkroutzFeed` if not exist.
- Go to Magento root folder and run upgrade command line to install `Spirit_SkroutzFeed`

```
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Magento cron must be running, as it generates the feeds.

## Setup

There are several configuration options for this extension which can be found
at `Stores > Configuration > Spirit > Skroutz`, at default or website scope. If Skroutz Analytics is installed,
its settings are on the same page.

### Skroutz XML Feed

1. Navigate to `Stores > Configuration > Spirit > Skroutz` and switch the scope to the website
2. In `XML Feed`, set `Enabled` to `yes` and choose the store view that gives the feed its language
3. In `XML Feed Field Mapping`, check the attribute of the manufacturer, MPN, EAN, color and size fields. A field
   can also take a fixed value, for example the manufacturer of a single brand shop
4. In `XML Feed Availability and Stock`, set the availability text Skroutz shows for each stock state
5. Save, then press `Generate now` in `XML Feed`. Within a minute the feed URL and its report appear there
6. Give Skroutz the feed URL in the Skroutz Merchants panel (the `.gz` one when the feed is over 10MB)

### Settings

| Group | Settings |
|---|---|
| XML Feed | Enable per website, store view (language, names, links), file name, schedule, Safety Check, feed URLs, last report, "Generate now" |
| XML Feed Field Mapping | Unique ID and Variation ID, the attribute (or fixed value) of every Skroutz field, color and size attributes, shipping cost, specifications |
| XML Feed Products and Variations | Exclude products without image (off by default), category filter (exclude or include only), one product per color |
| XML Feed Availability and Stock | Availability text (or "Hide from Skroutz") for in stock, backorder and out of stock products |

Each product also has a "Skroutz" group in its form, at website scope.

* **Exclude from Skroutz** leaves the product out of the feed.
* **Skroutz Availability (In Stock, On Backorder, Out of Stock)** overrides the availability of that stock state,
  "Hide from Skroutz" included. Children of configurable products inherit the values of their parent.

All of them can be set in bulk with `Catalog > Products > Actions > Update attributes`.

The feed is written to `pub/media/skroutz/<website code>.xml` and `.xml.gz`. The admin shows both URLs.

### What is listed

Simple and configurable products that are enabled, visible and in the website. Bundle, grouped, virtual and
downloadable products are not, as Skroutz cannot list them correctly. A simple product that is a child of a
configurable appears only as its variation, never on its own, so when the configurable is disabled or hidden its
children are not listed either.

| The configurable varies by | Feed |
|---|---|
| A size attribute and color (or other options) | one product per color, Unique ID `<parent>-<option id>`, sizes in `<variations>` |
| A size attribute only | one product with the parent's Unique ID, sizes in `<variations>` |
| No size attribute (color only, capacity...) | one product per child, with the child's Unique ID and the parent's details where the child has none |

The option id is the id of the attribute option (e.g. the color "Red"), not its label, so renaming a color in the
admin keeps the Unique ID. Each size in `<variations>` has the child's "Variation ID", by default the same
attribute as the Unique ID. A shop moving from another feed keeps the one that feed used for sizes (often the
child's SKU), so Skroutz sees no size as new. With "Do Not Nest a Single Size", "one size" items set up as
configurable products are written as plain products, without `<variations>`.

**Images.** A color takes the images of its children, and a child listed on its own takes its own images. Where
the child has none, the parent's are used, and the other way round for a configurable without images of its own.

Links of colors and sizes preselect the option on the product page (`product.html#93=50`). "Variant Name" sets
how color rows and listed children are named, for example `T-shirt Red M` or just `T-shirt`.

**Prices** are the final prices of the price index (special prices and catalog price rules included) for guests,
with VAT. Out of stock products, which Magento leaves out of the index when it hides them, fall back to their
price or active special price.

**Stock** is the salable quantity of the stock assigned to the website with MSI (stock index plus reservations of
unshipped orders), or the stock item without MSI. Both minus the "Out-of-Stock Threshold".

**Shipping cost** comes from an attribute, a fixed value, or a calculation with a cost up to a weight, a cost per
extra kilo, and free shipping above a price.

### Specifications

Skroutz takes extra characteristics as `<specifications><spec name="Label">value</spec></specifications>`.

* **Attributes shown on the product page** (default) are the attributes with "Visible on Catalog Pages on
  Storefront", the same ones the "More Information" tab shows. You manage them in
  `Stores > Attributes > Product`, and each product only gets those of its attribute set that have a value.
* **Attributes shown on the product page, except the selected** are the same, minus a list.
* **Only the selected attributes** are the ones you select.

Skroutz does not define the names. They are free key and value pairs, used to enrich its catalogue. The feed uses
the attribute labels of its store view, the names customers already see. Attributes mapped to a Skroutz field
(manufacturer, color, size...) are left out, as Skroutz ignores them in specifications. A developer can rename
specifications or add computed ones (see below).

### Generation

Magento cron generates the feeds of every enabled website at the "Schedule" setting, a cron expression in the time
zone of the store. The default, `50 6-23 * * *`, generates them at 06:50, 07:50 ... 23:50, so a fresh feed is ready
before every full hour from 07:00 to midnight. The jobs run in their own cron group (`spirit_skroutzfeed`), in a
separate process, so a long run never delays the other cron jobs. "Generate now" asks cron to start within a
minute. The command line does the same.

```
php bin/magento spirit:skroutz:feed [--website=base] [--force]
```

A new feed only replaces the one online when it is complete and checked.

1. The `.xml` and `.xml.gz` are written under temporary names next to the feed online.
2. Both are read back in full. They must be well-formed XML and hold every product written, so a full disk or a
   stopped process never reaches Skroutz.
3. **Safety Check.** A new feed with more than the set percentage fewer products than the one online (50% by
   default) is not published. `--force` publishes it anyway, for an expected drop.
4. Only then are both renamed over the feed online in one step, so Skroutz downloads either the old file or the
   new one, never a mix.

Any failure keeps the previous feed online, removes the temporary files and adds an admin notification, and the
report shows the error. Two websites can never write to the same file. `<created_at>` at the top of the feed is
the time of the generation, in the time zone of the store. While a long generation runs, Magento may log
`Could not acquire lock for cron job spirit_skroutzfeed_requests`. That is the next minute's check for
"Generate now" waiting, as intended.

## Skroutz Analytics

Skroutz matches each ordered item that Analytics reports to the feed product with the same Unique ID. The feed's
Unique ID is its own setting, the Magento product ID by default. The admin shows what Analytics sends under it,
and the feed section warns when the two cannot match.

* The feed and Analytics use a different Unique ID attribute.
* Analytics sends the variation ID while the feed lists configurables under the parent.
* Colors are listed as `<parent>-<color>`, an ID Analytics does not send yet.
* The feed name comes from another attribute than the product name.

## For developers

**Change the Unique ID.** Every ID of the feed comes from `Spirit\SkroutzFeed\Model\UniqueId`, with `get()` for
products, `getForVariant()` for colors and `getForVariation()` for sizes. Add a plugin.

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

**Rename or add specifications.** Rename them by attribute code in `di.xml`.

```xml
<type name="Spirit\SkroutzFeed\Model\Field\Specifications">
    <arguments>
        <argument name="labels" xsi:type="array">
            <item name="erp_material" xsi:type="string">Υλικό</item>
        </argument>
    </arguments>
</type>
```

Add specifications computed per product, or drop some, with a plugin.

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
by element name. A field implements `Spirit\SkroutzFeed\Model\Field\FieldInterface`.

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

Products carry the data loaded for them, `skroutz_stock` (qty, in_stock, managed, backorders),
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

Installed via composer

```
php bin/magento module:uninstall --remove-data Spirit_SkroutzFeed
```

Installed manually

```
php bin/magento module:uninstall --non-composer Spirit_SkroutzFeed
```

then delete `app/code/Spirit/SkroutzFeed` and run `php bin/magento setup:upgrade`. Both remove the
"Exclude from Skroutz" and "Skroutz Availability" attributes. Delete `pub/media/skroutz` by hand.

## Author

[Spirit Digital Agency](https://spiritdigital.agency/)

See [CHANGELOG.md](CHANGELOG.md) for the release history.
