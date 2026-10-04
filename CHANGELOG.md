# Changelog

## 1.0.0

First release, a rewrite of the unreleased development version.

* One feed per website, with its own settings and store view.
* Stock of the website: MSI salable quantities (stock index plus reservations), or the stock item without MSI.
* Every Skroutz field mappable to a product attribute or a fixed value, including the newer fields of the
  specification: wholesale price, season, size fit, outlet, author, expiration date, country of origin.
* Specifications from the attributes shown on the product page, or a selection.
* Configurable products: one product per color, sizes nested as variations, children listed one by one when they
  vary by other attributes. Several color and size attributes.
* Availability text, or hiding, for in stock, backorder and out of stock products, with per product overrides
  inherited by configurable children. "Exclude from Skroutz" attribute.
* Shipping cost from an attribute, a fixed value, or weight and price.
* Only simple and configurable products: bundles and grouped products are not listed.
* Large catalogs: keyset batches, one query per batch for stock, prices, categories, images and URLs, streaming
  XML, configurable children loaded in chunks.
* A feed is published only complete and checked: written under a temporary name, read back in full, compared
  with the feed online by the "Safety Check" (no publishing on a sudden drop in products, `--force` to override),
  then renamed over it. Any failure keeps the previous feed. A gzip copy, as Skroutz requires above 10MB.
* "Variation ID": the attribute of the size variations, so a shop moving from another feed keeps its IDs.
* Children of configurable products are listed only as variations, never on their own; images fall back from
  child to parent and back.
* Generation report per website, admin notification on failure, Skroutz Analytics Unique ID checks.
* Generation on a cron schedule, by default at :50 from 06:50 to 23:50, in its own cron group and process;
  "Generate now" from the admin; `bin/magento spirit:skroutz:feed`.
* Text holding &, < or > is written as CDATA, as in the Skroutz examples.
* Events, a pluginable Unique ID and di.xml extension points for developers.
* No dependency on other Spirit modules or on spatie/array-to-xml.
