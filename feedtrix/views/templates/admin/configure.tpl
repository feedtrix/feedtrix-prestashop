{*
 * FeedTrix module configuration (like the WooCommerce plugin page): before connecting – what FeedTrix is and the store keys,
 * after connecting – connection status, store features, AI chat preview; feature switches at the bottom.
 *
 * @author    FeedTrix
 * @copyright FeedTrix
 * @license   AFL-3.0
 *}
<style>
  .feedtrix-wrap{ --ftx-brand:#4f46e5; --ftx-brand-dark:#4338ca; --ftx-ink:#1d2327; --ftx-muted:#50575e; --ftx-line:#dcdcde; --ftx-soft:#f6f7ff; max-width:1040px; color:var(--ftx-ink) }
  .feedtrix-wrap h2{ margin:0 0 6px; font-size:18px; font-weight:600 }
  .feedtrix-title{ display:flex; align-items:center; gap:10px; margin:0 0 20px }
  .feedtrix-title img{ border-radius:8px }
  .feedtrix-title b{ font-size:20px }
  .feedtrix-tagline{ display:block; margin-top:2px; font-size:13px; color:#646970 }
  .feedtrix-card{ background:#fff; border:1px solid var(--ftx-line); border-radius:12px; padding:24px 28px; margin:0 0 20px }
  .feedtrix-card .help-block{ margin:0 0 16px; max-width:680px }
  .feedtrix-hero{ background:linear-gradient(135deg,#eef2ff 0%,#faf5ff 100%); border:1px solid #e0e7ff; border-radius:16px; padding:32px; margin-bottom:20px }
  .feedtrix-hero h2{ font-size:24px; line-height:1.3; margin:0 0 8px }
  .feedtrix-hero .lead{ font-size:15px; color:var(--ftx-muted); margin:0 0 28px; max-width:640px }
  .feedtrix-flow{ display:grid; grid-template-columns:1fr auto 1fr auto 1fr auto 1fr; gap:10px; margin-bottom:28px }
  .feedtrix-step{ background:#fff; border-radius:12px; padding:18px 14px; text-align:center; box-shadow:0 1px 3px rgba(30,27,75,.08) }
  .feedtrix-step .icon{ display:grid; place-items:center; width:48px; height:48px; margin:0 auto 10px; border-radius:12px; background:var(--ftx-soft); color:var(--ftx-brand) }
  .feedtrix-step strong{ display:block; font-size:14px; margin-bottom:4px }
  .feedtrix-step span{ display:block; font-size:12px; line-height:1.45; color:var(--ftx-muted) }
  .feedtrix-step.is-brand{ background:var(--ftx-brand); box-shadow:0 6px 18px rgba(79,70,229,.3) }
  .feedtrix-step.is-brand .icon{ background:rgba(255,255,255,.16); color:#fff }
  .feedtrix-step.is-brand strong, .feedtrix-step.is-brand span{ color:#fff }
  .feedtrix-arrow{ display:grid; place-items:center; color:#a5b4fc; font-size:22px }
  .feedtrix-benefits{ display:grid; grid-template-columns:repeat(3,1fr); gap:12px }
  .feedtrix-benefit{ background:rgba(255,255,255,.7); border-radius:10px; padding:14px 16px }
  .feedtrix-benefit strong{ display:block; margin-bottom:2px }
  .feedtrix-benefit span{ font-size:13px; color:var(--ftx-muted) }
  .feedtrix-status{ display:flex; align-items:center; gap:8px }
  .feedtrix-dot{ width:10px; height:10px; border-radius:50%; background:#00a32a; box-shadow:0 0 0 4px rgba(0,163,42,.15) }
  .feedtrix-dot.is-off{ background:#dba617; box-shadow:0 0 0 4px rgba(219,166,23,.18) }
  .feedtrix-facts{ display:grid; grid-template-columns:max-content 1fr; gap:8px 20px; margin:16px 0 }
  .feedtrix-facts dt{ color:var(--ftx-muted); font-weight:400 }
  .feedtrix-facts dd{ margin:0 }
  .feedtrix-actions{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:8px }
  .feedtrix-actions form{ margin:0 }
  .feedtrix-card .btn.feedtrix-btn, .feedtrix-card a.btn.feedtrix-btn{ background:var(--ftx-brand); border-color:var(--ftx-brand); color:#fff !important; font-weight:600 }
  .feedtrix-card .btn.feedtrix-btn:hover, .feedtrix-card .btn.feedtrix-btn:focus{ background:var(--ftx-brand-dark); border-color:var(--ftx-brand-dark); color:#fff !important; text-decoration:none }
  .feedtrix-keys{ display:grid; grid-template-columns:1fr 1fr; gap:14px; max-width:720px }
  .feedtrix-tiles{ display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:12px; margin:12px 0 0 }
  .feedtrix-tile{ display:grid; grid-template-columns:auto 1fr; align-items:start; gap:12px; padding:14px; border:1px solid #f0f0f1; border-radius:10px; background:#fcfcff }
  .feedtrix-tile .icon{ display:grid; place-items:center; width:40px; height:40px; border-radius:10px; background:var(--ftx-soft); color:var(--ftx-brand) }
  .feedtrix-tile strong{ display:block; margin-bottom:2px }
  .feedtrix-tile small{ display:block; font-size:12px; line-height:1.45; color:var(--ftx-muted) }
  .feedtrix-chip{ display:inline-block; margin-bottom:6px; font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px; background:#e7f7ec; color:#007a1f }
  .feedtrix-tile.is-off{ background:#fff }
  .feedtrix-tile.is-off .icon{ background:#f0f0f1; color:#8c8f94 }
  .feedtrix-tile.is-off .feedtrix-chip{ background:#f0f0f1; color:#646970 }
  .feedtrix-card .feedtrix-toggle{ display:flex !important; width:100%; align-items:center; justify-content:space-between; gap:24px; padding:14px 0; border-top:1px solid #f0f0f1; cursor:pointer; font-weight:400; margin:0; text-align:left }
  .feedtrix-card .feedtrix-toggle:first-of-type{ border-top:0 }
  .feedtrix-card .feedtrix-toggle-text{ display:block; flex:1; min-width:0 }
  .feedtrix-card .feedtrix-toggle-title{ display:block; margin-bottom:2px; font-size:14px; font-weight:600; color:#1d2327 }
  .feedtrix-card .feedtrix-toggle-desc{ display:block; font-size:13px; font-weight:400; line-height:1.45; color:var(--ftx-muted) }
  .feedtrix-card .feedtrix-switch{ display:block; position:relative; flex:none; width:40px; height:22px }
  .feedtrix-card .feedtrix-switch input{ position:absolute; top:0; left:0; z-index:1; opacity:0; margin:0; width:100%; height:100%; cursor:pointer }
  .feedtrix-card .feedtrix-knob{ display:block; position:absolute; top:0; left:0; right:0; bottom:0; border-radius:22px; background:#c3c4c7; transition:background .15s }
  .feedtrix-card .feedtrix-knob:after{ content:""; position:absolute; top:3px; left:3px; width:16px; height:16px; border-radius:50%; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.2); transition:transform .15s }
  .feedtrix-card .feedtrix-switch input:checked + .feedtrix-knob{ background:var(--ftx-brand) }
  .feedtrix-card .feedtrix-switch input:checked + .feedtrix-knob:after{ transform:translateX(18px) }
  .feedtrix-card .feedtrix-switch input:focus-visible + .feedtrix-knob{ outline:2px solid var(--ftx-brand); outline-offset:2px }
  .feedtrix-preview{ display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.2fr); gap:32px; align-items:center }
  .feedtrix-checks{ margin:16px 0 0; padding:0; list-style:none }
  .feedtrix-checks li{ position:relative; padding-left:26px; margin-bottom:8px }
  .feedtrix-checks li:before{ content:"✓"; position:absolute; left:0; top:0; display:grid; place-items:center; width:18px; height:18px; border-radius:50%; background:#e7f7ec; color:#007a1f; font-size:11px; font-weight:700 }
  .feedtrix-chat{ background:#f6f7fb; border:1px solid #e4e6f0; border-radius:14px; padding:0 16px 16px; box-shadow:0 10px 30px rgba(30,27,75,.08) }
  .feedtrix-chat-bar{ display:flex; align-items:center; gap:6px; padding:10px 0; margin-bottom:12px; border-bottom:1px solid #e4e6f0; color:var(--ftx-muted); font-size:12px }
  .feedtrix-chat-bar i{ width:9px; height:9px; border-radius:50%; background:#d0d3de }
  .feedtrix-chat-bar span{ margin-left:8px; font-weight:600 }
  .feedtrix-bubble{ max-width:88%; padding:10px 14px; border-radius:14px; font-size:13px; line-height:1.5; margin-bottom:10px }
  .feedtrix-bubble.is-user{ margin-left:auto; background:var(--ftx-brand); color:#fff; border-bottom-right-radius:4px }
  .feedtrix-bubble.is-ai{ background:#fff; border:1px solid #e4e6f0; border-bottom-left-radius:4px }
  .feedtrix-product{ display:grid; grid-template-columns:auto 1fr auto; align-items:center; gap:12px; margin-top:10px; padding:8px; border:1px solid #e4e6f0; border-radius:10px; background:#fcfcff }
  .feedtrix-product img, .feedtrix-product-img{ width:56px; height:56px; border-radius:8px; object-fit:cover; display:grid; place-items:center; background:var(--ftx-soft); color:var(--ftx-brand) }
  .feedtrix-product strong{ display:block }
  .feedtrix-product small{ color:var(--ftx-muted) }
  .feedtrix-product em{ font-style:normal; font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px; background:#e7f7ec; color:#007a1f }
  @media (max-width:960px){ .feedtrix-flow{ grid-template-columns:1fr 1fr } .feedtrix-arrow{ display:none } .feedtrix-benefits, .feedtrix-preview{ grid-template-columns:1fr } }
  @media (max-width:600px){ .feedtrix-hero{ padding:20px } .feedtrix-card{ padding:18px } .feedtrix-keys, .feedtrix-flow, .feedtrix-tiles{ grid-template-columns:1fr } .feedtrix-bubble{ max-width:100% } }
</style>

<div class="feedtrix-wrap">
  <div class="feedtrix-title">
    <img src="{$ftx_icon|escape:'html':'UTF-8'}" width="36" height="36" alt="">
    <span><b>FeedTrix</b> <span class="feedtrix-tagline">{l s="AI search optimization (AEO) for your store" mod='feedtrix'}</span></span>
  </div>

  {if !$ftx_friendly_urls}
    <div class="alert alert-warning">{l s="Turn on friendly URLs (Shop Parameters → Traffic & SEO) so that /llms.txt and the IndexNow key file work." mod='feedtrix'}</div>
  {/if}

  {if $ftx_connected}
    <section class="feedtrix-card">
      <h2 class="feedtrix-status"><span class="feedtrix-dot"></span>{l s="Your store is connected to FeedTrix" mod='feedtrix'}</h2>
      <dl class="feedtrix-facts">
        {if isset($ftx_account.email) && $ftx_account.email}
          <dt>{l s="Account:" mod='feedtrix'}</dt><dd><strong>{$ftx_account.email|escape:'html':'UTF-8'}</strong></dd>
        {/if}
        <dt>{l s="File for AI bots:" mod='feedtrix'}</dt>
        <dd><a href="{$ftx_llms_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$ftx_llms_url|escape:'html':'UTF-8'}</a></dd>
        <dt>{l s="Store Key" mod='feedtrix'}</dt><dd><code>{$ftx_store_key|escape:'html':'UTF-8'}</code></dd>
        <dt>{l s="API key" mod='feedtrix'}</dt><dd><code>{$ftx_api_key|escape:'html':'UTF-8'}</code></dd>
      </dl>
      {if isset($ftx_account.verified) && !$ftx_account.verified}
        <p class="help-block">✉ {l s="Confirm your email address using the link we sent you. Then choose a plan in the panel (14 days free)." mod='feedtrix'}</p>
      {/if}
      {if $ftx_bundled}<p class="help-block">{l s="This module was downloaded from the FeedTrix panel and already contains your store keys." mod='feedtrix'}</p>{/if}
      <div class="feedtrix-actions">
        {if $ftx_panel_url}
          <a class="btn feedtrix-btn" href="{$ftx_panel_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s="Open FeedTrix panel" mod='feedtrix'}</a>
        {/if}
        {if !$ftx_bundled}
          <form method="post" action="{$ftx_action|escape:'html':'UTF-8'}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{l s="Disconnect your store from FeedTrix?" mod='feedtrix'}">
            <button type="submit" name="feedtrixDisconnect" class="btn btn-link text-danger">{l s="Disconnect" mod='feedtrix'}</button>
          </form>
        {/if}
      </div>
    </section>

    <section class="feedtrix-card">
      <h2>{l s="Working in your store" mod='feedtrix'}</h2>
      <div class="feedtrix-tiles">
        {foreach $ftx_features as $feature}
          <div class="feedtrix-tile{if !$feature.on} is-off{/if}">
            <div class="icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{if $feature.icon == 'llms_txt'}<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>{/if}{if $feature.icon == 'bot_tracking'}<rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 8V4M9 13h.01M15 13h.01M9.5 16.5h5"/>{/if}{if $feature.icon == 'attribution'}<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>{/if}{if $feature.icon == 'json_ld'}<path d="M8 4c-2 0-3 1-3 3v2c0 1.5-1 2.5-2 3 1 .5 2 1.5 2 3v2c0 2 1 3 3 3M16 4c2 0 3 1 3 3v2c0 1.5 1 2.5 2 3-1 .5-2 1.5-2 3v2c0 2-1 3-3 3"/>{/if}{if $feature.icon == 'faq_tab'}<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5v.4M12 17h.01"/>{/if}</svg></div>
            <div>
              <span class="feedtrix-chip">{if $feature.on}{l s="On" mod='feedtrix'}{else}{l s="Off" mod='feedtrix'}{/if}</span>
              <strong>{$feature.title|escape:'html':'UTF-8'}</strong>
              <small>{$feature.description|escape:'html':'UTF-8'}</small>
            </div>
          </div>
        {/foreach}
      </div>
    </section>
  {else}
    <section class="feedtrix-hero">
      <h2>{l s="Get your store recommended by ChatGPT, Perplexity and Claude" mod='feedtrix'}</h2>
      <p class="lead">{l s="More and more customers ask AI assistants what to buy. FeedTrix prepares your catalog so that AI understands it, recommends your products and sends buyers to your store." mod='feedtrix'}</p>
      <div class="feedtrix-flow">
        <div class="feedtrix-step"><div class="icon"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M3 9h18"/><path d="M9 20v-6h6v6"/></svg></div><strong>{l s="Your store" mod='feedtrix'}</strong><span>{l s="PrestaShop products, prices and stock" mod='feedtrix'}</span></div>
        <div class="feedtrix-arrow" aria-hidden="true">→</div>
        <div class="feedtrix-step is-brand"><div class="icon"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l2.5 5.5L20 11l-5.5 2.5L12 19l-2.5-5.5L4 11l5.5-2.5z"/></svg></div><strong>FeedTrix</strong><span>{l s="Writes /llms.txt, structured data and AI descriptions" mod='feedtrix'}</span></div>
        <div class="feedtrix-arrow" aria-hidden="true">→</div>
        <div class="feedtrix-step"><div class="icon"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M8.5 11h7M8.5 14h4"/></svg></div><strong>{l s="AI assistants" mod='feedtrix'}</strong><span>ChatGPT, Perplexity, Claude, Gemini</span></div>
        <div class="feedtrix-arrow" aria-hidden="true">→</div>
        <div class="feedtrix-step"><div class="icon"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.6 11.2a1.5 1.5 0 0 0 1.5 1.1h8.4a1.5 1.5 0 0 0 1.5-1.1L21 7H6"/></svg></div><strong>{l s="New customers" mod='feedtrix'}</strong><span>{l s="AI recommends your products and links to your store" mod='feedtrix'}</span></div>
      </div>
      <div class="feedtrix-benefits">
        <div class="feedtrix-benefit"><strong>{l s="Visible in AI answers" mod='feedtrix'}</strong><span>{l s="/llms.txt and JSON-LD generated from your catalog, updated after every price or stock change." mod='feedtrix'}</span></div>
        <div class="feedtrix-benefit"><strong>{l s="AI bot statistics" mod='feedtrix'}</strong><span>{l s="See which AI bots visit your store and which pages they read." mod='feedtrix'}</span></div>
        <div class="feedtrix-benefit"><strong>{l s="Sales from AI" mod='feedtrix'}</strong><span>{l s="Orders from customers who came from AI assistants, in one report." mod='feedtrix'}</span></div>
      </div>
    </section>

    <section class="feedtrix-card">
      <h2 class="feedtrix-status"><span class="feedtrix-dot is-off"></span>{l s="Connect your store to FeedTrix" mod='feedtrix'}</h2>
      <p class="help-block">
        {l s="Add this store in the FeedTrix panel, then copy its keys from: Shops → your shop → Integration keys." mod='feedtrix'}
        <a href="{$ftx_signup_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s="No account yet? Sign up" mod='feedtrix'}</a>
      </p>
      <form method="post" action="{$ftx_action|escape:'html':'UTF-8'}">
        <div class="feedtrix-keys">
          <div class="form-group"><label for="ftx-store-key">{l s="Store Key" mod='feedtrix'}</label>
            <input class="form-control" type="text" id="ftx-store-key" name="store_key" required pattern="ftx_[a-f0-9]{literal}{24}{/literal}" placeholder="ftx_…" autocomplete="off" spellcheck="false" value="{if isset($ftx_old.store_key)}{$ftx_old.store_key|escape:'html':'UTF-8'}{/if}"></div>
          <div class="form-group"><label for="ftx-api-key">{l s="API key" mod='feedtrix'}</label>
            <input class="form-control" type="password" id="ftx-api-key" name="api_key" required autocomplete="off" spellcheck="false"></div>
        </div>
        <button type="submit" name="feedtrixKeys" class="btn feedtrix-btn">{l s="Connect store" mod='feedtrix'}</button>
      </form>
    </section>
  {/if}

  <section class="feedtrix-card feedtrix-preview">
    <div>
      <h2>{l s="What your customer sees" mod='feedtrix'}</h2>
      <p class="help-block">{l s="This is how an AI assistant can recommend your store once it knows your catalog." mod='feedtrix'}</p>
      <ul class="feedtrix-checks">
        <li>{l s="Up-to-date prices and availability" mod='feedtrix'}</li>
        <li>{l s="A direct link to the product in your store" mod='feedtrix'}</li>
        <li>{l s="The sale is attributed to AI in your report" mod='feedtrix'}</li>
      </ul>
    </div>
    <div class="feedtrix-chat" aria-hidden="true">
      <div class="feedtrix-chat-bar"><i></i><i></i><i></i><span>{l s="AI assistant" mod='feedtrix'}</span></div>
      <div class="feedtrix-bubble is-user">{l s="I'm looking for: %s. Where is the best place to buy it?" sprintf=[$ftx_preview.name] mod='feedtrix'}</div>
      <div class="feedtrix-bubble is-ai">
        {l s='I recommend %1$s from %2$s. It is in stock, and the product page has the full specification and current price.' sprintf=[$ftx_preview.name, $ftx_domain] mod='feedtrix'}
        <div class="feedtrix-product">
          {if $ftx_preview.image}
            <img src="{$ftx_preview.image|escape:'html':'UTF-8'}" alt="" width="56" height="56">
          {else}
            <span class="feedtrix-product-img"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M3 9h18"/></svg></span>
          {/if}
          <span>
            <strong>{$ftx_preview.name|escape:'html':'UTF-8'}</strong>
            <small>{$ftx_domain|escape:'html':'UTF-8'}{if $ftx_preview.price} · {$ftx_preview.price|escape:'html':'UTF-8'}{/if}</small>
          </span>
          <em>{l s="In stock" mod='feedtrix'}</em>
        </div>
      </div>
    </div>
  </section>

  <form method="post" action="{$ftx_action|escape:'html':'UTF-8'}" class="feedtrix-card">
    <h2>{l s="Features" mod='feedtrix'}</h2>
    {foreach $ftx_features as $key => $feature}
      <label class="feedtrix-toggle">
        <span class="feedtrix-toggle-text"><span class="feedtrix-toggle-title">{$feature.title|escape:'html':'UTF-8'}</span><span class="feedtrix-toggle-desc">{$feature.description|escape:'html':'UTF-8'}</span></span>
        <span class="feedtrix-switch"><input type="checkbox" name="{$key|escape:'html':'UTF-8'}" value="1" {if $feature.on}checked{/if}><span class="feedtrix-knob"></span></span>
      </label>
    {/foreach}
    <button type="submit" name="feedtrixSettings" style="margin-top:12px" class="btn feedtrix-btn">{l s="Save Changes" mod='feedtrix'}</button>
  </form>
</div>
