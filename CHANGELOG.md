# Changelog

## 1.0.0

- Same features as the WooCommerce plugin.
- FeedTrix structured data (JSON-LD) on product pages:
  - extends the theme's Product node (AI description, attributes, tagged offer link) or adds its own node with the live price and availability;
  - adds a FAQPage node.
- Optional "Questions and answers" tab on product pages.
- /llms-full.txt (table of contents) and /llms-full/{n}.txt (100 products per file).
- Sales from AI: the visit source is stored in the ft_ai_source cookie, and paid orders from AI visits are reported to FeedTrix (no buyer data).
- FeedTrix can drop the local copies (controller=purge) and change the switches and cache times (controller=settings).
- The "Open FeedTrix panel" button opens the panel home page.
- New configuration page: overview, features and AI chat preview.
- The interface is in English and translated to Polish, German, Spanish, French and Italian.

## 0.1.1

- When the FeedTrix subscription is inactive, /llms.txt is no longer served and the local backup copy is removed.

## 0.1.0

- First release: store connection, /llms.txt, IndexNow key, product webhooks, AI bot statistics, public catalog for FeedTrix.
