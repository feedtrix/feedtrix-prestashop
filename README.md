# FeedTrix for PrestaShop

The `feedtrix/` module for PrestaShop 8.0–9.x (PHP 7.2+). It works the same way as the WooCommerce plugin.

## What it does

- **Store connection** (Modules → FeedTrix → Configure): Store Key + API key from the FeedTrix panel (the account and
  store are created in the panel). After connecting, FeedTrix sends an email with a link to the panel.
- **/llms.txt**, **/llms-full.txt**, **/llms-full/{n}.txt** and **/{key}.txt** (IndexNow) through module routes. They
  require friendly URLs (Shop Parameters → Traffic & SEO).
  - Local copies are stored in `var/cache/…/feedtrix/`. Their lifetime is set by FeedTrix, 1 day by default.
  - FeedTrix removes them after a catalog change.
  - The last valid version of `/llms.txt` and the index stays as a backup in case of an API failure.
- **JSON-LD on the product page** (`actionOutputHTMLBefore`):
  - extends the theme's Product node with the AI description, attributes and an offer link tagged `utm_source=feedtrix`;
  - when the theme does not print one, adds its own node with the current price;
  - always adds FAQPage.
- **Questions and answers tab** (`displayProductExtraContent`). Off by default.
- **Sales from AI**:
  - a script before `</body>` stores the visit source in the `ft_ai_source` cookie for 30 days;
  - when an order is placed (`actionValidateOrder`), the source goes to the `feedtrix_order` table;
  - once paid (status with the "paid" flag), the module sends `order.created`.
- **Webhooks** for product changes: create, update, delete, stock and specific prices. Sent in batches after the
  request ends, with a price and availability snapshot.
- **AI bot statistics**: hourly counters sent every hour.
- **Public catalog** for FeedTrix (only active products visible in the store). No Webservice keys are needed.

## Store URLs

| URL | Description |
|-----|-------------|
| `/llms.txt`, `/llms-full.txt`, `/llms-full/{n}.txt` | content from FeedTrix (local copy, removed by FeedTrix after a change) |
| `/{key}.txt` | IndexNow key |
| `/index.php?fc=module&module=feedtrix&controller=products` | catalog (`page`, `per_page` ≤ 100, `id`) |
| `…&controller=status` | status for the connection test (FeedTrix HMAC signature) |
| `…&controller=purge` | POST from FeedTrix: remove local `/llms.txt` and JSON-LD copies (HMAC signature) |
| `…&controller=settings` | GET/POST from FeedTrix: switches and copy lifetimes (HMAC signature) |

The contract is documented at developer.feedtrix.com. The `.zip` package contains no keys: the store connects with the
keys entered in the module configuration. Release: a `vX.Y.Z` tag matching `$this->version`; the workflow builds
`feedtrix.zip`.

## To check

- [ ] PrestaShop 9 (tested on 8.2.9),
- [ ] multistore (settings saved in the current shop context),
- [ ] nginx servers without PrestaShop rules for `*.txt` (the file must reach `index.php`),
- [ ] products with combinations (price and stock computed for the base product).
