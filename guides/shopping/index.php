<?php

declare(strict_types=1);

$page_title = "Diablo 1 & Hellfire Comprehensive Shopping Guide | UV's Compendium";
$page_description = "Learn how to target powerful equipment from Griswold, Wirt, and Adria using qlvl ranges, shopper stats, value anchors, and DevilutionX shop mechanics.";
$base_path = '../../';
$current_page = 'comprehensive-shopping-guide';
$page_styles = ['css/in-page-navigation.css', 'guides/css/styles.css'];
$page_scripts = ['js/in-page-navigation.js'];

require_once dirname(__DIR__, 2) . '/includes/public_header.php';
?>

<nav class="guide-breadcrumbs" aria-label="Breadcrumb">
  <ol class="guide-breadcrumb-list">
    <li><a href="<?= site_url() ?>">Home</a></li>
    <li><a href="<?= site_url('guides/') ?>">Guides</a></li>
    <li aria-current="page">Comprehensive Shopping Guide</li>
  </ol>
</nav>

<div class="guide-layout">
  <aside class="in-page-nav-column" aria-label="Page sections">
    <details class="in-page-nav-panel" open>
      <summary class="in-page-nav-summary">On this page</summary>
      <nav
        class="in-page-nav"
        aria-label="Table of contents"
        data-in-page-nav
        data-in-page-nav-target="guide-content"
        data-in-page-nav-selector="h2, h3"
        data-in-page-nav-collapse-width="1120">
        <p class="in-page-nav-placeholder">Sections appear here automatically.</p>
      </nav>
    </details>
  </aside>

  <article class="guide-entry">
    <header class="guide-header">
      <p class="eyebrow">Diablo I &amp; Hellfire Strategy Guide</p>
      <h1 class="guide-title">Diablo 1 &amp; Hellfire Comprehensive Shopping Guide</h1>
      <p class="guide-deck">A practical guide to targeting powerful equipment through vendor rules, ideal shopper levels, stat filtering, value anchors, and DevilutionX shop-breaking mechanics.</p>

      <dl class="guide-meta">
        <div class="guide-meta-item">
          <dt>Written by</dt>
          <dd>Ultraviolet</dd>
        </div>
        <div class="guide-meta-item">
          <dt>Published</dt>
          <dd><time datetime="2026-08-11">August 11, 2026</time></dd>
        </div>
        <div class="guide-meta-item">
          <dt>Updated</dt>
          <dd><time datetime="2026-08-11">August 11, 2026</time></dd>
        </div>
        <div class="guide-meta-item">
          <dt>Applies to</dt>
          <dd>Diablo 1 / Hellfire 1.01 / DevilutionX</dd>
        </div>
      </dl>
    </header>

    <figure class="guide-figure guide-featured-figure">
      <img src="../../images/shopping-guide-header.png" alt="">
      <figcaption class="guide-figcaption">Griswold's Premium Items Menu</figcaption>
    </figure>

    <div id="guide-content" class="guide-content">
      <p class="guide-lead">Shopping is one of the best ways to acquire powerful equipment in <strong>Diablo 1</strong>, and Hellfire adds enough extra mechanics that shopping can become a form of targeted item farming in its own right.</p>
      <p>A good dungeon drop may take thousands of monster kills to find. Shops repeatedly generate equipment according to rules based on character level, vendor, item qlvl, affix qlvl, character stats, item value, and, in Hellfire, even the equipment you are already carrying.</p>
      <p>Once you understand those rules, you can deliberately narrow the pool and greatly improve your chances of finding specific gear.</p>
      <p>This guide focuses primarily on <strong>Hellfire and DevilutionX Hellfire</strong>, with Diablo differences noted where they matter.</p>

      <aside class="guide-callout guide-callout-note" aria-label="Guide summary">
        <p class="guide-callout-title">At a glance</p>
        <p>Use Griswold for repeated equipment rolls, Wirt for exceptional affixes and advanced targets, and Adria for books and charged staves. The best odds come from matching the target to the right shopper level, usable shop slots, class, stats, and price anchors.</p>
      </aside>

      <dl class="guide-quick-facts">
        <div class="guide-quick-fact">
          <dt class="guide-quick-fact-label">Primary Focus</dt>
          <dd><strong>Hellfire / DevilutionX</strong></dd>
        </div>
        <div class="guide-quick-fact">
          <dt class="guide-quick-fact-label">Griswold Premium Slots</dt>
          <dd><strong>15 in Hellfire</strong></dd>
        </div>
        <div class="guide-quick-fact">
          <dt class="guide-quick-fact-label">Core Strategy</dt>
          <dd><strong>Narrow the item pool</strong></dd>
        </div>
      </dl>
      <p>The three equipment vendors are:</p>
      <ul class="guide-list">
        <li><strong>Griswold</strong> for weapons, armor, helms, shields, and uncharged staves in Hellfire</li>
        <li><strong>Wirt</strong> for a single potentially exceptional magical item</li>
        <li><strong>Adria</strong> for books and staves with spell charges</li>
      </ul>
      <p>Pepin sells potions and, once available, elixirs, but he is not relevant to equipment shopping.</p>
      <section id="why-shop-instead-of-only-farming" class="guide-section">
        <h2>Why Shop Instead of Only Farming?</h2>
        <p>Dungeon farming and shopping work together.</p>
        <p>Dungeon runs remain important for:</p>
        <ul class="guide-list">
          <li>Unique items</li>
          <li>Jewelry in multiplayer</li>
          <li>High-level magical drops</li>
          <li>Equipment unavailable from a particular merchant</li>
          <li>Gold to pay for expensive shop purchases</li>
        </ul>
        <p>Shopping is especially useful when:</p>
        <ul class="guide-list">
          <li>You know the exact affixes you want</li>
          <li>The target can be generated by Griswold or Wirt</li>
          <li>You want a specific base item</li>
          <li>You want to target a narrow affix combination</li>
          <li>You want an affix that cannot normally drop in the dungeon</li>
          <li>You can manipulate the shop to eliminate large amounts of unwanted equipment</li>
        </ul>
        <p>Some excellent endgame equipment is far more practical to obtain through shopping than by waiting for the dungeon to randomly generate it.</p>
        <p>The important part is figuring out <strong>which vendor, shopper, character level, shop slots, and filtering setup give you the best odds</strong>.</p>
      </section>
      <section id="understanding-qlvl-and-item-creation-level" class="guide-section">
        <h2>Understanding qlvl and Item Creation Level</h2>
        <p>The most important number for practical shopping is <strong>qlvl</strong>, or quality level.</p>
        <p>Base items, prefixes, suffixes, spells, and unique items all have qlvls.</p>
        <p>Jarulf's Guide also uses the term <strong>ilvl</strong>, meaning <em>item creation level</em>. This is not the same concept as Diablo II's persistent hidden item level attached to an individual item.</p>
        <p>In Diablo 1 and Hellfire shopping, it is better to think of ilvl as an intermediate number used while generating the shop inventory:</p>
        <pre class="guide-mechanics"><code>Character level + vendor/slot rules
→ item creation level
→ allowed qlvl ranges</code></pre>
        <p>For most shopping, you do not need to calculate the item creation level manually. What matters is the resulting range of base-item and affix qlvls available in each shop slot.</p>
        <p>Our <a class="guide-tool-link" href="<?= site_url('calculators/shop-qlvl/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Shop Qlvl Calculator (opens in a new tab)"><strong>Hellfire Shop Qlvl Calculator</strong></a> handles this for you.</p>
        <dl class="guide-definition-list">
          <div>
            <dt>qlvl</dt>
            <dd>The quality level assigned to base items, affixes, spells, and unique items.</dd>
          </div>
          <div>
            <dt>clvl</dt>
            <dd>The shopping character's current level.</dd>
          </div>
          <div>
            <dt>ilvl</dt>
            <dd>The temporary item creation level used to determine the qlvl ranges available to a shop slot.</dd>
          </div>
        </dl>
        <section id="why-the-correct-shopping-level-matters">
          <h3>Why the Correct Shopping Level Matters</h3>
          <p>Higher character level is not always better.</p>
          <aside class="guide-callout guide-callout-warning" aria-label="Shopping level warning">
            <p class="guide-callout-title">Do not outlevel a narrow target</p>
            <p>Once a shop slot's minimum qlvl rises past a low-level base item or affix, that target disappears from the slot entirely.</p>
          </aside>
          <p>Some items have a limited shopping window. If your character level gets too high, the minimum qlvl allowed by the shop can rise enough that a low-level base item or low-level affix stops appearing entirely.</p>
          <p>Examples include:</p>
          <ul class="guide-list">
            <li>Jester's Dagger/Club/Small Axe of Peril</li>
            <li>Topaz Shields of Blocking</li>
            <li>Merciless Long War Bow of Swiftness</li>
          </ul>
          <p>For narrow targets, the goal is not simply to reach a level where the item becomes possible.</p>
          <p>The goal is to find the character level where the <strong>largest useful number of shop slots</strong> can generate it.</p>
          <p>Many targets have an ideal shopping level or a small range of ideal levels.</p>
        </section>
      </section>
      <section id="using-the-shopping-tools" class="guide-section">
        <h2>Using the Shopping Tools</h2>
        <p>There are a couple of good ways to research a target.</p>
        <section id="premium-item-checker-first">
          <h3>Premium Item Checker First</h3>
          <p>If you already have an item combination in mind, the <a class="guide-tool-link" href="<?= site_url('calculators/premium-item-checker/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Premium Item Checker (opens in a new tab)"><strong>Hellfire Premium Item Checker</strong></a> is usually the easiest place to start.</p>
          <p>Use it to determine things such as:</p>
          <ul class="guide-list">
            <li>Whether the prefix can occur on the base item</li>
            <li>Whether the suffix can occur on the base item</li>
            <li>Whether the prefix and suffix can occur together</li>
            <li>Whether Griswold can sell the item</li>
            <li>Whether Wirt can sell the item</li>
            <li>Whether the item can be found in the dungeon</li>
            <li>The qlvl of the components</li>
          </ul>
          <p>Once you know the item is valid, use the <a class="guide-tool-link" href="<?= site_url('calculators/shop-qlvl/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Shop Qlvl Calculator (opens in a new tab)"><strong>Hellfire Shop Qlvl Calculator</strong></a> to check different shopper levels and see which Griswold slots can actually generate it.</p>
          <p>This is important for narrow-range items. A target may technically be available across several character levels, while one specific level gives you far more usable slots than the others.</p>
        </section>
        <section id="jarulf--shop-qlvl-calculator">
          <h3>Jarulf + Shop Qlvl Calculator</h3>
          <p>If you prefer doing the research more manually, use <strong>Jarulf's Guide</strong> to look up:</p>
          <ul class="guide-list">
            <li>Base-item qlvl</li>
            <li>Prefix qlvl</li>
            <li>Suffix qlvl</li>
            <li>Affix values</li>
            <li>Valid item types</li>
            <li>Shop restrictions</li>
            <li>Item requirements</li>
            <li>Price information</li>
          </ul>
          <p>Then use the <a class="guide-tool-link" href="<?= site_url('calculators/shop-qlvl/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Shop Qlvl Calculator (opens in a new tab)"><strong>Hellfire Shop Qlvl Calculator</strong></a> to determine where those qlvls fit into Griswold, Wirt, or Adria's available ranges.</p>
          <p>In practice, all three resources work together well.</p>
          <p>I often use the <a class="guide-tool-link" href="<?= site_url('calculators/premium-item-checker/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Premium Item Checker (opens in a new tab)">Premium Item Checker</a> to confirm that something is possible, then use the <a class="guide-tool-link" href="<?= site_url('calculators/shop-qlvl/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Shop Qlvl Calculator (opens in a new tab)">Shop Qlvl Calculator</a> to compare different character levels and determine exactly which shop slots I should be checking.</p>
        </section>
      </section>
      <section id="griswold" class="guide-section">
        <h2>Griswold</h2>
        <p>Griswold is the main equipment merchant and generally the easiest vendor to farm.</p>
        <p>He sells:</p>
        <ul class="guide-list">
          <li>Basic nonmagical equipment</li>
          <li>Premium magical equipment</li>
        </ul>
        <p>The premium inventory is what matters for serious targeted shopping.</p>
        <p>Diablo gives Griswold <strong>6 premium slots</strong>.</p>
        <p>Hellfire gives him <strong>15 premium slots</strong>, making Griswold much more useful as an equipment source.</p>
        <section id="griswolds-hellfire-premium-slots">
          <h3>Griswold's Hellfire Premium Slots</h3>
          <p>Hellfire's low-level premium inventory has a few special cases because the inventory is progressively built as the character levels.</p>
          <p>Once the character reaches <strong>clvl 6+</strong>, the effective item creation levels settle into this pattern:</p>
          <section class="guide-table-wrap" aria-label="Griswold premium slot item creation levels" tabindex="0">
            <table class="guide-table">
              <caption>Hellfire Griswold premium slots at clvl 6 and above</caption>
              <thead>
                <tr>
                  <th>Slots</th>
                  <th>Item Creation Level</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>1–3</td>
                  <td>clvl - 2</td>
                </tr>
                <tr>
                  <td>4–6</td>
                  <td>clvl - 1</td>
                </tr>
                <tr>
                  <td>7–9</td>
                  <td>clvl</td>
                </tr>
                <tr>
                  <td>10–12</td>
                  <td>clvl + 1</td>
                </tr>
                <tr>
                  <td>13–14</td>
                  <td>clvl + 2</td>
                </tr>
                <tr>
                  <td>15</td>
                  <td>clvl + 3</td>
                </tr>
              </tbody>
            </table>
          </section>
          <p>The maximum item creation level is 30.</p>
          <p>For each premium slot:</p>
          <ul class="guide-list">
            <li><strong>Base-item qlvl:</strong> ilvl / 4 through ilvl</li>
            <li><strong>Affix qlvl:</strong> ilvl / 2 through ilvl</li>
          </ul>
          <p>Values are rounded down.</p>
          <p>Lower character levels have slightly different slot progression, so use the <a class="guide-tool-link" href="<?= site_url('calculators/shop-qlvl/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Shop Qlvl Calculator (opens in a new tab)"><strong>Hellfire Shop Qlvl Calculator</strong></a> rather than trying to memorize all of the early-level exceptions.</p>
        </section>
        <section id="shop-slots-matter">
          <h3>Shop Slots Matter</h3>
          <p>A target may be available:</p>
          <ul class="guide-list">
            <li>In all 15 slots</li>
            <li>Only in the first few slots</li>
            <li>Only in the later slots</li>
            <li>Across some middle range</li>
          </ul>
          <p>This can dramatically change shopping efficiency.</p>
          <p>For a narrow target, don't only ask:</p>
          <p><strong>"What level can this item appear?"</strong></p>
          <p>Also ask:</p>
          <p><strong>"Which level gives me the most useful Griswold slots?"</strong></p>
          <p>Getting a narrow item into slots 1–3 can be especially valuable because those slots generate the lowest-qlvl equipment in the shop.</p>
        </section>
        <section id="fresh-inventory-vs-replacement-items">
          <h3>Fresh Inventory vs. Replacement Items</h3>
          <p>One low-level Hellfire wrinkle is that the initial premium inventory and an item generated immediately after buying something are not always equivalent.</p>
          <p>This matters most for very narrow low-level targets.</p>
          <p>For something such as Jester's Dagger/Club of Peril, repeatedly restarting and checking a fresh Griswold inventory is more useful than assuming that buying the item in one of the first slots will give you another equivalent roll in its replacement slot.</p>
        </section>
      </section>
      <section id="choosing-a-shopping-character" class="guide-section">
        <h2>Choosing a Shopping Character</h2>
        <p>Character level is only part of Hellfire shopping.</p>
        <p>The shopper's <strong>class and current stats can also eliminate unwanted base items</strong>.</p>
        <p>This is one of the biggest reasons Sorcerers are commonly used as dedicated shopping characters.</p>
        <section id="the-shopcerer">
          <h3>The Shopcerer</h3>
          <p>The Sorcerer's main advantage is not Teleport or Town Portal.</p>
          <p>It is his extremely low maximum Strength:</p>
          <p><strong>45 Strength</strong></p>
          <p>Hellfire allows the shop to generate items with requirements up to roughly <strong>120% of the greater of your natural maximum stat or current stat</strong>.</p>
          <p>A Sorcerer held at 45 Strength therefore allows items requiring up to:</p>
          <pre class="guide-mechanics"><code>45 Strength × 1.2 = 54 Strength maximum requirement</code></pre>
          <p>while blocking items with higher Strength requirements.</p>
          <p>That can remove a huge amount of unwanted armor and weapon bases from the shop pool.</p>
          <section id="controlling-strength">
            <h4>Controlling Strength</h4>
            <p>Equipment bonuses count toward your current Strength.</p>
            <p>This matters when using the <strong>Veil of Steel</strong>, since it adds +15 Strength.</p>
            <p>A normal max-Strength Sorcerer wearing the Veil would go from:</p>
            <pre class="guide-mechanics"><code>45 base Strength + 15 Veil of Steel = 60 current Strength</code></pre>
            <p>which allows much heavier equipment to enter the shop pool.</p>
            <p>There are a couple of ways around this.</p>
            <p>One is to build a dedicated Shopcerer with no more than <strong>30 base Strength</strong>.</p>
            <p>With Veil of Steel:</p>
            <pre class="guide-mechanics"><code>30 base Strength + 15 Veil of Steel = 45 current Strength</code></pre>
            <p>so you keep the same 54-Strength shop cutoff.</p>
            <p>Another option is to keep cursed equipment <strong>of Trouble</strong>, which reduces all attributes.</p>
            <p>Jewelry or one-handed weapons of Trouble can counteract unwanted Strength bonuses from shopping equipment such as Veil of Steel.</p>
            <p>A carefully built Shopcerer can therefore combine:</p>
            <ul class="guide-list">
              <li>Low Strength for base-item filtering</li>
              <li>Veil of Steel for helm value filtering</li>
              <li>Expensive price anchors in other categories</li>
            </ul>
            <p>without accidentally widening the equipment pool.</p>
          </section>
        </section>
        <section id="sometimes-you-want-more-strength">
          <h3>Sometimes You Want More Strength</h3>
          <p>Low Strength is not always desirable.</p>
          <p>If you're targeting something such as a Tower Shield, you need enough Strength for the shop to allow the base item.</p>
          <p>A <strong>Tower Shield requires 60 Strength</strong>, so setting a Sorcerer's Strength to exactly <strong>50</strong> works nicely:</p>
          <pre class="guide-mechanics"><code>50 Strength × 1.2 = 60 Strength maximum requirement</code></pre>
          <p>That allows Tower Shields while still excluding equipment requiring more than 60 Strength.</p>
          <p>The same principle works at both Griswold and Wirt.</p>
        </section>
        <section id="barbarian-shopping">
          <h3>Barbarian Shopping</h3>
          <p>The Barbarian can also be useful for certain targets.</p>
          <p>He is not useful when you want to eliminate high-Strength equipment, but that becomes an advantage when the item you want has a high Strength requirement anyway.</p>
          <p>His low Dexterity can also help eliminate certain bows.</p>
          <p>Barbarians are particularly useful for armor or heavy-weapon shopping where restricting Strength would only remove the equipment you're looking for.</p>
        </section>
        <section id="back-up-shopping-characters">
          <h3>Back Up Shopping Characters</h3>
          <p>Instead of worrying about accidentally leveling a shopper past an important breakpoint, I prefer making backup copies of shopping characters at useful levels.</p>
          <p>That lets you keep things such as:</p>
          <ul class="guide-list">
            <li>Level 9 Jester shopper</li>
            <li>Level 12 Blocking-shield shopper</li>
            <li>Level 20–22 shield shoppers</li>
            <li>Level 30 endgame shoppers</li>
          </ul>
          <p>and simply use the appropriate copy for whatever you're currently hunting.</p>
          <aside class="guide-callout guide-callout-tip" aria-label="Shopping character backup tip">
            <p class="guide-callout-title">UV's Tip</p>
            <p>Keep separate backup shoppers at the important breakpoints. That preserves optimized level 9, 12, 20–22, and 30 setups even if one character gains experience.</p>
          </aside>
        </section>
      </section>
      <section id="hellfires-80-value-rule" class="guide-section">
        <h2>Hellfire's 80% Value Rule</h2>
        <p>Hellfire adds one of the most useful shopping mechanics in the game.</p>
        <p>When Griswold generates premium equipment, he tries to make the item worth more than <strong>80% of the most expensive item of the same type that you already possess</strong>.</p>
        <p>This is checked separately for:</p>
        <ul class="guide-list">
          <li>Armor</li>
          <li>Axes</li>
          <li>Bows</li>
          <li>Clubs</li>
          <li>Helms</li>
          <li>Shields</li>
          <li>Staves</li>
          <li>Swords</li>
          <li>Rings</li>
          <li>Amulets</li>
        </ul>
        <p>The item can be equipped or simply carried in your inventory.</p>
        <p>This means equipment can remain useful even when you would never fight with it.</p>
        <p>It becomes <strong>shopping gear</strong>.</p>
        <aside class="guide-callout guide-callout-note" aria-label="Hellfire value rule summary">
          <p class="guide-callout-title">The 80% rule</p>
          <p>For each item category, Griswold tries to generate premium equipment worth more than 80% of the most valuable matching item you carry or equip.</p>
        </aside>
        <section id="building-better-anchors">
          <h3>Building Better Anchors</h3>
          <p>You do not need ideal shopping anchors immediately.</p>
          <p>You can gradually work your way upward.</p>
          <ol class="guide-list guide-list-ordered">
            <li>Carry the most expensive item you currently own in a category.</li>
            <li>Let the 80% rule bias the shop toward more expensive equipment.</li>
            <li>Buy or find a more expensive item.</li>
            <li>Replace your old anchor.</li>
            <li>Repeat.</li>
          </ol>
          <p>Over time, you can build a dedicated shopping kit that eliminates a large amount of lower-value junk before it ever reaches the shop inventory.</p>
          <p>Slot levels tell you which items are eligible, but a shop inventory is generated from a deterministic sequence of random rolls. Tightening one restriction can change the whole package of offers, especially when a new game generates many items before you reach the vendor.</p>
          <p>If hours of resets produce uniformly poor inventories, try a less expensive anchor rather than assuming a higher price always helps. Reconsider low-Strength filtering too: removing it can open a different mix of rolls without changing the item you are targeting.</p>
        </section>
        <section id="armor-anchor-demonspike-coat">
          <h3>Armor Anchor: Demonspike Coat</h3>
          <p><strong>Demonspike Coat</strong> is one of the strongest possible shopping anchors because its value is over 250,000 gold.</p>
          <p>Its 80% threshold is therefore higher than Hellfire's normal 200,000-gold shop limit.</p>
          <p>Under the normal rules, no newly generated armor can simultaneously:</p>
          <ul class="guide-list">
            <li>Be worth more than 80% of Demonspike Coat</li>
            <li>Stay under the normal shop price ceiling</li>
          </ul>
          <p>That forces Griswold's armor generation into its retry/fallback behavior.</p>
          <p>Even if you never wear Demonspike Coat, it can be extremely valuable on a shopping character.</p>
        </section>
        <section id="helm-anchor-veil-of-steel">
          <h3>Helm Anchor: Veil of Steel</h3>
          <p>The <strong>Veil of Steel</strong> is an excellent helm anchor.</p>
          <p>Its high value eliminates most cheap helmet combinations from satisfying the 80% requirement.</p>
          <p>It is also much easier to obtain consistently than many theoretical high-value magical helms because it is a quest reward.</p>
          <p>Just remember that its +15 Strength can interfere with Shopcerer filtering.</p>
        </section>
        <section id="shield-anchors">
          <h3>Shield Anchors</h3>
          <p>One of the most valuable possible shield anchors is an:</p>
          <p><strong>Emerald Gothic Shield of the Tiger</strong></p>
          <p>This is more valuable than an Emerald Gothic Shield of Ages.</p>
          <p>The reason you sometimes see <strong>Emerald Gothic Shield of Ages</strong> discussed in relation to Wirt is not because Ages is expensive. Ages is simply the only suffix Wirt can generate alongside Emerald on a Gothic Shield under the normal affix-generation rules.</p>
          <p>An Emerald Gothic Shield of the Tiger is a much better price anchor, but it cannot normally be shopped. Emerald's qlvl pushes it beyond normal Griswold generation, so this kind of shield generally has to come from sufficiently high-level dungeon drops.</p>
          <p>Even an Emerald Gothic Shield without the ideal suffix can make an excellent shopping anchor.</p>
        </section>
        <section id="weapon-anchors">
          <h3>Weapon Anchors</h3>
          <p>For weapon categories, the most useful anchors are usually expensive combinations such as King's or Emerald weapons of the Heavens.</p>
          <p>Examples include:</p>
          <ul class="guide-list">
            <li>King's/Emerald Great Sword of the Heavens</li>
            <li>King's/Emerald Great Axe of the Heavens</li>
            <li>King's/Emerald Maul of the Heavens</li>
            <li>Emerald Long War Bow of the Heavens</li>
          </ul>
          <p>Larger base weapons are generally preferable when the goal is simply maximizing price.</p>
        </section>
        <section id="staff-anchors">
          <h3>Staff Anchors</h3>
          <p>Very expensive staves can exceed 250,000 gold.</p>
          <p>A King's Staff carrying expensive spell charges such as Blood Star is one possible route.</p>
          <p>There are several staff combinations capable of becoming extremely valuable, although the best ones usually have to be found in the dungeon or obtained through Wirt.</p>
        </section>
        <section id="item-value-vs-resale-value">
          <h3>Item Value vs. Resale Value</h3>
          <p>When Griswold buys equipment from you, he normally offers <strong>one quarter of its actual item value</strong>.</p>
          <p>So an item worth:</p>
          <p><strong>126,375 gold</strong></p>
          <p>will show a resale value around:</p>
          <p><strong>31,593 gold</strong></p>
          <p>That makes resale price a convenient way to estimate an anchor's real value.</p>
          <p>Alternatively, enter the item's exact stats into the <a class="guide-tool-link" href="<?= site_url('calculators/hellfire-item-price/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Item Price Calculator (opens in a new tab)"><strong>Hellfire Item Price Calculator</strong></a>.</p>
          <p>The calculator is especially useful when planning around theoretical equipment you do not own yet.</p>
        </section>
      </section>
      <section id="shop-price-limits" class="guide-section">
        <h2>Shop Price Limits</h2>
        <p>A magical item can satisfy all of the qlvl and affix rules and still fail to appear because its price is too high.</p>
        <p>Hellfire normally caps town-generated items at <strong>200,000 gold</strong> in underlying item value.</p>
        <p>Wirt applies his own pricing modifier afterward, which is why his displayed prices differ.</p>
        <p>The <a class="guide-tool-link" href="<?= site_url('calculators/hellfire-item-price/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Item Price Calculator (opens in a new tab)"><strong>Hellfire Item Price Calculator</strong></a> reports when a theoretical item exceeds the normal shop limit.</p>
        <p>That doesn't necessarily make the item absolutely unobtainable in DevilutionX, but it means you are no longer doing normal shopping.</p>
        <p>You are trying to break the shop's normal generation rules.</p>
      </section>
      <section id="shop-breaking-in-devilutionx" class="guide-section">
        <h2>Shop Breaking in DevilutionX</h2>
        <p>DevilutionX's shop generators do not retry forever.</p>
        <p>If the game keeps rejecting generated items because they violate the normal filters, it eventually gives up.</p>
        <p>That creates a small loophole for equipment that should normally fail because of price, requirements, or other restrictions.</p>
        <aside class="guide-callout guide-callout-warning" aria-label="Shop breaking warning">
          <p class="guide-callout-title">Advanced DevilutionX technique</p>
          <p>Shop breaking depends on exhausting the generator's retries. It is dramatically slower than normal targeted shopping and should be reserved for items the ordinary rules reject.</p>
        </aside>
        <section id="griswold-1">
          <h3>Griswold</h3>
          <p>Hellfire Griswold gets <strong>150 attempts</strong> to find an item that satisfies his normal filters.</p>
          <p>If those attempts fail, the final fallback can violate conditions that would normally cause the item to be rejected.</p>
        </section>
        <section id="wirt">
          <h3>Wirt</h3>
          <p>Wirt has two important retry thresholds.</p>
          <p>After <strong>200 failed attempts</strong>, his normal class-based item-type restrictions are relaxed.</p>
          <p>The other filters continue longer.</p>
          <p>At <strong>250 attempts</strong>, Wirt can finally fall through the broader restrictions involving things such as:</p>
          <ul class="guide-list">
            <li>Item price</li>
            <li>Stat requirements</li>
            <li>The 80% value rule</li>
          </ul>
          <p>This is the mechanic behind extreme targets such as <strong>Godly Plate of the Whale</strong>.</p>
          <p>You are deliberately trying to make Wirt reject enough items that the generator finally accepts something that would normally be filtered out.</p>
          <p>This is vastly slower than shopping for equipment that fits the normal rules, but it makes a handful of otherwise unavailable items technically obtainable.</p>
        </section>
      </section>
      <section id="important-shopping-targets" class="guide-section">
        <h2>Important Shopping Targets</h2>
        <p>The following are some useful targets and example shopping setups.</p>
        <p>They are not the only good equipment in the game, but they show how character level, stats, slots, price anchors, and vendor rules can all be combined.</p>
      </section>
      <section id="armor" class="guide-section">
        <h2>Armor</h2>
        <section id="awesome-full-plate-mail-of-harmony">
          <h3>Awesome Full Plate Mail of Harmony</h3>
          <p>For most characters capable of wearing it, one of the best general-purpose magical armors is:</p>
          <p><strong>Awesome Full Plate Mail of Harmony</strong></p>
          <p>Awesome provides high Armor Class while Harmony provides fastest hit recovery.</p>
          <p>A plain Awesome Full Plate Mail can be perfectly good while developing a character, but Harmony is generally the suffix to shoot for long-term.</p>
          <p>Other desirable or situational suffixes include:</p>
          <ul class="guide-list">
            <li><strong>of Stars</strong></li>
            <li><strong>of Sorcery</strong></li>
            <li><strong>of the Lion</strong></li>
            <li><strong>of the Mammoth</strong></li>
            <li><strong>of the Whale</strong></li>
            <li><strong>of Osmosis</strong></li>
          </ul>
          <p>These all have uses, but they generally do not replace Harmony as the standard target.</p>
          <p>Less desirable but still potentially useful suffixes include:</p>
          <ul class="guide-list">
            <li><strong>of Deflection</strong></li>
            <li><strong>of Giants</strong></li>
            <li><strong>of Precision</strong></li>
            <li><strong>of Vigor</strong></li>
          </ul>
          <section id="recommended-awesome-fpm-shopping-setup">
            <h4>Recommended Awesome FPM Shopping Setup</h4>
            <p>Use a:</p>
            <p><strong>Level 30 Barbarian with no more than 55 Dexterity</strong></p>
            <p>Carry the most expensive practical equipment you can in every category.</p>
            <p>For armor, use an anchor worth approximately:</p>
            <p><strong>198,400 gold</strong><br />
              <strong>Resale: approximately 49,600</strong>
            </p>
            <p>This eliminates most cheaper armor while still leaving the main targets available.</p>
            <p>With this setup, <strong>Awesome Full Plate Mail of Harmony can appear in any Griswold premium slot</strong>.</p>
            <p>It also leaves other useful combinations available, including things such as:</p>
            <ul class="guide-list">
              <li>Awesome Full Plate Mail of the Tiger</li>
              <li>Awesome Full Plate Mail of the Lion</li>
            </ul>
            <p>If you also want <strong>Awesome Full Plate Mail of Sorcery</strong> to remain eligible, lower the armor anchor to around:</p>
            <p><strong>184,275 gold</strong><br />
              <strong>Resale: approximately 46,068</strong>
            </p>
          </section>
          <section id="awesome-full-plate-mail-of-stars">
            <h4>Awesome Full Plate Mail of Stars</h4>
            <p>Awesome Full Plate Mail of Stars always exceeds the normal 200,000-gold shop limit.</p>
            <p>It could theoretically be forced through shop fallback behavior, but it is not really worth going through that much trouble for.</p>
            <p>A <strong>Saintly Full Plate Mail of Stars</strong>, however, is normally shoppable and is a perfectly valid alternative.</p>
            <p>It can be especially useful if your jewelry is not yet great and you're willing to get fastest hit recovery from another slot such as:</p>
            <ul class="guide-list">
              <li>Jewelry of Harmony</li>
              <li>A helm of Harmony</li>
            </ul>
          </section>
        </section>
        <section id="mammoth-whale-and-osmosis-armor">
          <h3>Mammoth, Whale, and Osmosis Armor</h3>
          <p>High-life armor such as:</p>
          <ul class="guide-list">
            <li>Godly/Holy/Awesome Plate of the Mammoth</li>
            <li>Godly/Holy/Awesome Plate of the Whale</li>
          </ul>
          <p>can become too expensive for ordinary shopping.</p>
          <p><strong>of Osmosis</strong> is also a high-level Wirt-only suffix and can produce similarly difficult Full Plate Mail combinations.</p>
          <p>These are more niche than Awesome FPM of Harmony and generally require Wirt rather than ordinary Griswold shopping.</p>
        </section>
        <section id="godly-plate-of-the-whale">
          <h3>Godly Plate of the Whale</h3>
          <p><strong>Godly Plate of the Whale</strong>, usually shortened to GPoW, is basically the holy grail of Hellfire shopping.</p>
          <p>It is not something you normally roll at Wirt.</p>
          <p>The strategy is to deliberately make Wirt fail his generation checks enough times that his fallback behavior has a chance to let the item through.</p>
          <figure class="guide-figure guide-gpow-figure">
            <a href="<?= site_url('images/godly-plate-of-the-whale.png') ?>?v=<?= asset_version('images/godly-plate-of-the-whale.png') ?>" target="_blank" rel="noopener noreferrer" aria-label="Open the full-size Godly Plate of the Whale screenshot (opens in a new tab)">
              <img class="guide-gpow-image" src="<?= site_url('images/godly-plate-of-the-whale.png') ?>?v=<?= asset_version('images/godly-plate-of-the-whale.png') ?>" alt="Wirt offering Godly Plate of the Whale with 200% armor and 97 extra hit points" width="1920" height="1080" loading="lazy">
            </a>
            <figcaption class="guide-figcaption">Wirt offered this Godly Plate of the Whale with +200% armor and +97 hit points despite the normal shop filters. Select the image to read the full-size item text.</figcaption>
          </figure>
          <section id="gpow-shopping-setup">
            <h4>GPoW Shopping Setup</h4>
            <p>A practical setup is:</p>
            <ul class="guide-list">
              <li>Sorcerer</li>
              <li>At least clvl 30</li>
              <li>No more than 45 Strength</li>
              <li>The most expensive practical equipment of every item type carried or equipped</li>
            </ul>
            <p>The low Sorcerer Strength eliminates large amounts of unwanted equipment while the expensive anchors make Wirt reject as much cheap gear as possible.</p>
            <p>The goal is to force Wirt into the 250-attempt fallback as often as possible.</p>
            <p>Even with the shop optimized, the odds are extremely low.</p>
            <p>Very few players have ever forced one.</p>
          </section>
        </section>
      </section>
      <section id="shields" class="guide-section">
        <h2>Shields</h2>
        <section id="obsidian-tower-shield-of-the-tiger">
          <h3>Obsidian Tower Shield of the Tiger</h3>
          <p>For Warriors, who already have natural Fast Block, one of the best normal Griswold targets is:</p>
          <p><strong>Obsidian Tower Shield of the Tiger</strong></p>
          <p>A perfect example would have:</p>
          <ul class="guide-list">
            <li>+40% Resist All</li>
            <li>+50 Life</li>
            <li>20 base Armor Class</li>
          </ul>
          <p><strong>Obsidian Tower Shield of the Wolf</strong> is also very good.</p>
          <section id="resistance-roll">
            <h4>Resistance Roll</h4>
            <p>I would generally avoid buying one with less than about <strong>35% Resist All</strong>.</p>
            <p>The reason is that a stronger Obsidian roll makes it much easier to reach maximum resistances with only one other major resistance item.</p>
            <p>Ideally, you want the roll as close to 40% as possible.</p>
          </section>
          <section id="recommended-shopping-setup">
            <h4>Recommended Shopping Setup</h4>
            <p>Use a Sorcerer with approximately <strong>50 Strength</strong>.</p>
            <p>That allows the 60-Strength Tower Shield requirement while continuing to exclude heavier equipment.</p>
            <p>A shield anchor around:</p>
            <p><strong>55,612 gold</strong><br />
              <strong>Resale: approximately 13,903</strong>
            </p>
            <p>is useful for eliminating many lower-value shields and tends to filter out weaker Obsidian rolls, although a shield with weaker resistances and a very high Life roll can occasionally still be expensive enough to pass.</p>
            <p>If you're specifically hunting a perfect Obsidian Tower Shield of the Tiger, an anchor around:</p>
            <p><strong>76,312 gold</strong><br />
              <strong>Resale: approximately 19,078</strong>
            </p>
            <p>can eliminate anything cheaper than the perfect target.</p>
            <p>The combination becomes available from Griswold around <strong>clvl 22+</strong>.</p>
          </section>
        </section>
        <section id="emerald-tower-shield-of-the-tiger">
          <h3>Emerald Tower Shield of the Tiger</h3>
          <p>An even stronger resistance version is:</p>
          <p><strong>Emerald Tower Shield of the Tiger</strong></p>
          <p>This can be targeted from Wirt around:</p>
          <p><strong>clvl 20–21</strong></p>
          <p>A Sorcerer with exactly <strong>50 Strength</strong> is again a very good shopper because it allows the 60-Strength Tower Shield while excluding anything requiring more Strength.</p>
          <p>The perfect shield would have:</p>
          <ul class="guide-list">
            <li>+50% Resist All</li>
            <li>+50 Life</li>
            <li>20 base Armor Class</li>
          </ul>
          <p>This is one of the best examples of using both the shopper's stats and Wirt's affix access to target an item that Griswold cannot normally provide.</p>
        </section>
        <section id="resistance-shields-of-blocking">
          <h3>Resistance Shields of Blocking</h3>
          <p>Characters without natural Fast Block often want a magical shield carrying <strong>of Blocking</strong>.</p>
          <p>Useful resistance prefixes include:</p>
          <ul class="guide-list">
            <li>Topaz</li>
            <li>Pearl</li>
            <li>Crimson</li>
            <li>Azure</li>
          </ul>
          <p>A good general shopping setup is:</p>
          <p><strong>Level 12 Sorcerer with no more than 45 Strength</strong></p>
          <p>Check Griswold's <strong>first six premium slots</strong>.</p>
          <p>The low Strength helps remove unwanted heavier equipment, although price anchoring is already very effective at this level if you have sufficiently expensive shopping gear.</p>
        </section>
      </section>
      <section id="kings-swords" class="guide-section">
        <h2>King's Swords</h2>
        <p>For conventional sword users, common high-end targets include:</p>
        <ul class="guide-list">
          <li><strong>King's Bastard Sword of Haste</strong></li>
          <li><strong>King's Bastard Sword of Speed</strong></li>
          <li><strong>King's Bastard Sword of Vampires</strong></li>
          <li><strong>King's Bastard Sword of Blood</strong></li>
        </ul>
        <p>A Broad Sword is also perfectly usable if the rolls are good, but Bastard Sword is generally preferred.</p>
        <p>Haste is usually the first choice for raw attack speed.</p>
        <p>Speed is extremely close and remains an excellent weapon.</p>
        <p>Vampires and Blood trade attack speed for mana or life recovery.</p>
        <section id="recommended-shopping-setup-1">
          <h3>Recommended Shopping Setup</h3>
          <p>Use a:</p>
          <p><strong>Level 30 Sorcerer with no more than 45 Strength</strong></p>
          <p>These King's Sword combinations can appear in <strong>any of Griswold's 15 premium slots</strong>.</p>
          <p>Carry a sword worth around:</p>
          <p><strong>97,625 gold</strong><br />
            <strong>Resale: approximately 24,406</strong>
          </p>
          <p>and use the most expensive practical anchors you own in the other equipment categories.</p>
          <p>This filters out large amounts of unrelated cheap equipment without widening the high-Strength base pool.</p>
        </section>
      </section>
      <section id="kings-great-axes" class="guide-section">
        <h2>King's Great Axes</h2>
        <p>For Barbarians, common endgame magical axe targets include:</p>
        <ul class="guide-list">
          <li><strong>King's Great Axe of Haste</strong></li>
          <li><strong>King's Great Axe of Speed</strong></li>
          <li><strong>King's Great Axe of Blood</strong></li>
          <li><strong>King's Great Axe of Vampires</strong></li>
        </ul>
        <section id="recommended-shopping-setup-2">
          <h3>Recommended Shopping Setup</h3>
          <p>Use either:</p>
          <ul class="guide-list">
            <li><strong>Level 30 Barbarian</strong></li>
            <li><strong>Level 30 Sorcerer with at least 67 Strength</strong></li>
          </ul>
          <p>The combination can appear in <strong>any Griswold premium slot</strong>.</p>
          <p>A strong axe-category anchor is worth around:</p>
          <p><strong>177,000 gold</strong><br />
            <strong>Resale: approximately 44,250</strong>
          </p>
          <p>Carry the most expensive equipment you can in the other categories as well.</p>
        </section>
      </section>
      <section id="kings-war-staff" class="guide-section">
        <h2>King's War Staff</h2>
        <p>For the Monk, the main magical staff target is straightforward:</p>
        <ul class="guide-list">
          <li><strong>King's War Staff of Haste</strong></li>
          <li><strong>King's War Staff of Speed</strong></li>
        </ul>
        <p>The Monk attacks very quickly with staves, can block while using them, and gets his adjacent quarter-damage attack with staves.</p>
        <p>Blood and Vampires are not available on staves, leaving Haste/Speed as the obvious offensive suffixes.</p>
        <section id="recommended-shopping-setup-3">
          <h3>Recommended Shopping Setup</h3>
          <p>Use a:</p>
          <p><strong>Level 30 Sorcerer with 25–45 Strength</strong></p>
          <p>Twenty-five Strength is enough for the War Staff while remaining below the Sorcerer's natural 45-Strength maximum.</p>
          <p>The target can appear in <strong>all 15 Griswold premium slots</strong>.</p>
          <p>A good staff anchor is worth around:</p>
          <p><strong>126,375 gold</strong><br />
            <strong>Resale: approximately 31,593</strong>
          </p>
        </section>
      </section>
      <section id="jesters-weapons-of-peril" class="guide-section">
        <h2>Jester's Weapons of Peril</h2>
        <p>Hellfire adds one of the stranger physical weapon combinations in the game:</p>
        <ul class="guide-list">
          <li><strong>Jester's Dagger of Peril</strong></li>
          <li><strong>Jester's Club of Peril</strong></li>
          <li><strong>Jester's Small Axe of Peril</strong></li>
        </ul>
        <p>Jester's causes highly variable outgoing damage, while Peril doubles damage dealt and also causes damage to the user.</p>
        <p>Because the returned Peril damage is based on weapon damage, low base weapon damage is actually desirable.</p>
        <p>That makes Dagger, Club, and Small Axe ideal rather than inferior versions of larger weapons.</p>
        <section id="recommended-shopping-setup-4">
          <h3>Recommended Shopping Setup</h3>
          <p>Use a:</p>
          <p><strong>Level 9 Sorcerer</strong></p>
          <p>On a fresh Griswold premium inventory:</p>
          <ul class="guide-list">
            <li><strong>Jester's Dagger of Peril:</strong> slots 1–3</li>
            <li><strong>Jester's Club of Peril:</strong> slots 1–3</li>
            <li><strong>Jester's Small Axe of Peril:</strong> slots 1–14</li>
          </ul>
          <p>The Small Axe cannot appear in slot 15 because that slot's minimum base qlvl is already too high at level 9.</p>
          <p>This is about as optimized as the target gets.</p>
          <p>Dagger and Club have qlvl 1 bases, while Jester's requires qlvl 7 and Peril requires qlvl 5. At clvl 9, Griswold's first three slots reach exactly the range necessary to combine those low-level bases with the higher-level Jester prefix.</p>
          <p>The Small Axe has a qlvl 2 base, allowing it to survive through nearly the entire shop.</p>
          <p>This is a good example of an item where leveling the shopper higher would actually make the desired low-qlvl bases disappear.</p>
        </section>
      </section>
      <section id="rogue-bows" class="guide-section">
        <h2>Rogue Bows</h2>
        <p>Bow shopping differs substantially between Diablo and Hellfire.</p>
        <section id="diablo-massive-long-war-bow-of-swiftness">
          <h3>Diablo: Massive Long War Bow of Swiftness</h3>
          <p>One of the classic Diablo Rogue weapons is:</p>
          <p><strong>Massive Long War Bow of Swiftness</strong></p>
          <section id="diablo-shopping">
            <h4>Diablo Shopping</h4>
            <p>Use approximately:</p>
            <p><strong>clvl 21</strong></p>
            <p>and check Griswold's <strong>first four premium slots</strong>.</p>
          </section>
          <section id="hellfire-shopping">
            <h4>Hellfire Shopping</h4>
            <p>In Hellfire, the same bow is best targeted around:</p>
            <p><strong>clvl 22</strong></p>
            <p>and can appear in Griswold's <strong>first six premium slots</strong>.</p>
            <p>The bow itself is not particularly useful as an endgame Hellfire weapon because Swiftness no longer increases the Rogue's firing animation. In Hellfire it increases arrow travel speed instead.</p>
            <p>However, DevilutionX Hellfire characters can be converted to Diablo by changing a copied save file from:</p>
            <pre class="guide-mechanics"><code>Hellfire save: .hsv
Diablo save:  .sv</code></pre>
            <p>So it is possible to shop the bow under Hellfire's more generous 15-slot Griswold system and then transfer a copy of the character to Diablo.</p>
            <aside class="guide-callout guide-callout-warning" aria-label="Save conversion warning">
              <p class="guide-callout-title">Convert a copy</p>
              <p>Use a copied character file for the extension change rather than modifying your only Hellfire save.</p>
            </aside>
          </section>
        </section>
      </section>
      <section id="hellfire-rogue-bows" class="guide-section">
        <h2>Hellfire Rogue Bows</h2>
        <p>Hellfire's Oils change what makes a good bow.</p>
        <section id="why-short-war-bow">
          <h3>Why Short War Bow?</h3>
          <p>For a heavily developed magical bow, <strong>Short War Bow</strong> is usually preferable to Long War Bow because of its higher minimum damage.</p>
          <p>Oil of Sharpness can increase maximum damage until the difference between minimum and maximum damage reaches 30.</p>
          <p>That means:</p>
          <pre class="guide-mechanics"><code>Short War Bow: 4–34
Long War Bow:  1–31</code></pre>
          <p>after fully developing their maximum damage.</p>
          <p>The Short War Bow therefore ends up with significantly better total physical damage.</p>
        </section>
        <section id="high-end-prefixes">
          <h3>High-End Prefixes</h3>
          <p>Strong Hellfire bow prefixes include:</p>
          <ul class="guide-list">
            <li><strong>Merciless</strong></li>
            <li><strong>Emerald</strong></li>
            <li><strong>Strange</strong></li>
            <li><strong>Obsidian</strong></li>
          </ul>
          <p>Merciless is the main raw-damage option.</p>
          <p>Emerald is the best Resist All option.</p>
          <p>Obsidian is weaker than Emerald but remains perfectly serviceable.</p>
          <p>Strange can be useful when additional chance to hit is needed.</p>
          <p>Reasonable earlier damage prefixes include:</p>
          <ul class="guide-list">
            <li>Ruthless</li>
            <li>Savage</li>
            <li>Massive</li>
          </ul>
          <p>You do not need to wait for a perfect Merciless bow before using a good damage bow.</p>
        </section>
        <section id="useful-suffixes">
          <h3>Useful Suffixes</h3>
          <p>A premier damage-oriented target is:</p>
          <p><strong>Merciless Short War Bow of the Heavens</strong></p>
          <p>A resistance-oriented alternative is:</p>
          <p><strong>Emerald Short War Bow of the Heavens</strong></p>
          <p>Other useful suffixes include:</p>
          <ul class="guide-list">
            <li><strong>of Burning</strong></li>
            <li><strong>of Thunder</strong></li>
          </ul>
          <p>for elemental damage.</p>
          <p>Temporary bows can also be useful with:</p>
          <ul class="guide-list">
            <li>of Stars</li>
            <li>of Perfection</li>
            <li>of Precision</li>
            <li>of Sorcery</li>
          </ul>
          <p>Those can make perfectly good weapons while progressing, but I generally would not spend large numbers of harder-to-find Oils developing a bow that I already expect to replace.</p>
        </section>
        <section id="price-limits">
          <h3>Price Limits</h3>
          <p>Do not blindly apply every old Diablo/Hellfire price example to DevilutionX.</p>
          <p>For example, old references sometimes use <strong>Merciless Long War Bow of the Heavens</strong> as an example of an item that cannot be sold because of its price.</p>
          <p>Under DevilutionX Hellfire's pricing and 200,000-gold shop limit, Merciless bows of the Heavens can fit within the normal rules.</p>
          <p>Use the <a class="guide-tool-link" href="<?= site_url('calculators/hellfire-item-price/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Item Price Calculator (opens in a new tab)"><strong>Hellfire Item Price Calculator</strong></a> rather than relying on an old example.</p>
          <p>If the calculator reports that an item exceeds the normal shop limit, you can still theoretically attempt to force it through DevilutionX fallback behavior, but the odds become dramatically worse.</p>
        </section>
      </section>
      <section id="wirt-1" class="guide-section">
        <h2>Wirt</h2>
        <p>Wirt is simultaneously one of the most powerful and most annoying merchants in the game.</p>
        <p>He:</p>
        <ul class="guide-list">
          <li>Shows one item at a time</li>
          <li>Charges 50 gold just to see it</li>
          <li>Is located far away from the main town shopping area</li>
          <li>Has Hellfire class-based item restrictions</li>
          <li>Requires restarting or another valid refresh condition to generate another useful roll</li>
        </ul>
        <p>For practical repeated Wirt farming, the normal routine is basically:</p>
        <pre class="guide-mechanics"><code>Start game → run across town → pay Wirt → check item → restart</code></pre>
        <p>This is much slower than Griswold shopping.</p>
        <p>Wirt is primarily worth the effort when the item you're hunting either:</p>
        <ul class="guide-list">
          <li>Uses affixes Griswold cannot generate</li>
          <li>Uses a prefix/suffix combination Griswold cannot generate</li>
          <li>Requires advanced shop breaking</li>
        </ul>
        <section id="wirts-qlvl-rules">
          <h3>Wirt's qlvl Rules</h3>
          <p>Wirt can generate base items with qlvl:</p>
          <pre class="guide-mechanics"><code>Base-item qlvl: 1 through clvl
Affix qlvl:     clvl through 2 × clvl</code></pre>
          <p>Once the lower end rises above 25, it is capped back at 25.</p>
          <p>Wirt's maximum affix qlvl is <strong>60</strong>.</p>
          <p>This gives him access to high-level affixes that Griswold can never sell.</p>
          <p>Examples include:</p>
          <ul class="guide-list">
            <li>Godly</li>
            <li>Merciless</li>
            <li>Strange</li>
            <li>Whale</li>
            <li>Slaughter</li>
            <li>Osmosis</li>
          </ul>
          <p>and other qlvl 30+ affixes.</p>
        </section>
        <section id="hellfire-wirt-class-restrictions">
          <h3>Hellfire Wirt Class Restrictions</h3>
          <p>Wirt restricts certain item categories depending on the shopper's class.</p>
          <section class="guide-table-wrap" aria-label="Wirt class restrictions" tabindex="0">
            <table class="guide-table">
              <caption>Item categories Wirt normally excludes by shopper class</caption>
              <thead>
                <tr>
                  <th>Class</th>
                  <th>Wirt normally will not sell</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Warrior</td>
                  <td>Bows, Staves</td>
                </tr>
                <tr>
                  <td>Rogue</td>
                  <td>Axes, Clubs, Swords, Staves, Shields</td>
                </tr>
                <tr>
                  <td>Sorcerer</td>
                  <td>Axes, Clubs, Bows, Staves</td>
                </tr>
                <tr>
                  <td>Monk</td>
                  <td>Clubs, Bows, Shields, Medium Armor</td>
                </tr>
                <tr>
                  <td>Bard</td>
                  <td>Axes, Clubs, Staves</td>
                </tr>
                <tr>
                  <td>Barbarian</td>
                  <td>Bows, Staves</td>
                </tr>
              </tbody>
            </table>
          </section>
          <p>Shopper class therefore matters at Wirt even before stat filtering is considered.</p>
          <p>In some cases, these restrictions are useful because they eliminate unwanted item types.</p>
          <p>At the 200-attempt shop-breaking threshold, these class restrictions stop being enforced.</p>
        </section>
        <section id="why-wirt-loves-of-ages">
          <h3>Why Wirt Loves "of Ages"</h3>
          <p>High-level Wirt inventories often appear flooded with <strong>of Ages</strong>.</p>
          <p>That is not because Ages is particularly valuable.</p>
          <p>At high Wirt qlvls there simply are not many suffixes that:</p>
          <ul class="guide-list">
            <li>Can occur on the item</li>
            <li>Fit the qlvl relationship</li>
            <li>Stay inside normal price restrictions</li>
          </ul>
          <p>Ages happens to survive those rules surprisingly often.</p>
          <p>The same issue explains why an <strong>Emerald Gothic Shield of Ages</strong> is a normal Wirt possibility even though Ages itself adds very little monetary value.</p>
        </section>
      </section>
      <section id="adria" class="guide-section">
        <h2>Adria</h2>
        <p>Adria works slightly differently between Diablo and Hellfire.</p>
        <section id="hellfire">
          <h3>Hellfire</h3>
          <p>In Hellfire, Adria only sells <strong>staves containing spell charges</strong>.</p>
          <p>Griswold sells the opposite type: magical staves <strong>without spell charges</strong>.</p>
          <p>Wirt can sell staves both with and without spell charges depending on his generation rules.</p>
        </section>
        <section id="diablo">
          <h3>Diablo</h3>
          <p>In Diablo:</p>
          <ul class="guide-list">
            <li>Griswold does not sell staves</li>
            <li>Adria can sell charged and uncharged staves</li>
          </ul>
          <p>This is one of the relatively small but important vendor differences between Diablo and Hellfire.</p>
        </section>
        <section id="adrias-limits">
          <h3>Adria's Limits</h3>
          <p>Adria's maximum item creation level is 16.</p>
          <p>This means:</p>
          <ul class="guide-list">
            <li>Base items cap at qlvl 16</li>
            <li>Spells on books or charged staves cap at qlvl 16</li>
            <li>Staff prefixes can reach qlvl 32</li>
          </ul>
          <p>One important consequence:</p>
          <p><strong>Staves of Magi cannot spawn at Adria.</strong></p>
          <p>Magi is too high-level for her spell-generation limit.</p>
          <p>If you want a staff carrying Magi charges, shop Wirt.</p>
        </section>
        <section id="check-adria-often">
          <h3>Check Adria Often</h3>
          <p>Adria refreshes whenever you return to town from the dungeon.</p>
          <p>That makes her worth checking constantly during normal play for:</p>
          <ul class="guide-list">
            <li>Spellbooks</li>
            <li>Useful charged staves</li>
            <li>Elixirs once available</li>
            <li>Mana supplies</li>
          </ul>
          <p>Once your character is high enough for elixirs, every trip back to town gives you another chance at permanent stat progression.</p>
        </section>
      </section>
      <section id="jewelry-and-multiplayer" class="guide-section">
        <h2>Jewelry and Multiplayer</h2>
        <p>Griswold and Wirt only sell jewelry in <strong>single player</strong>.</p>
        <p>In multiplayer, rings and amulets must be found.</p>
        <aside class="guide-callout guide-callout-note" aria-label="Multiplayer jewelry note">
          <p class="guide-callout-title">Multiplayer difference</p>
          <p>Targeted vendor shopping cannot solve multiplayer jewelry. Rings and amulets remain a dungeon-farming problem.</p>
        </aside>
        <p>That makes dungeon farming unavoidable for many of the strongest jewelry combinations, including:</p>
        <ul class="guide-list">
          <li>Obsidian Jewelry of the Zodiac</li>
          <li>Dragon's Jewelry of the Zodiac</li>
          <li>Dragon's Jewelry of Wizardry</li>
          <li>Gold Jewelry of the Heavens</li>
          <li>Gold Jewelry of Perfection</li>
          <li>High single-resistance jewelry</li>
          <li>Jewelry of the Dark</li>
        </ul>
        <p>This is one reason Normal Hell farming remains useful even for players who do a large amount of targeted shopping.</p>
        <p>Shops can solve your weapons, armor, helms, and shields.</p>
        <p>Your jewelry is still largely a dungeon problem.</p>
      </section>
      <section id="oils-and-shop-equipment" class="guide-section">
        <h2>Oils and Shop Equipment</h2>
        <p>Hellfire Oils change how you evaluate final equipment, but they do not mean every near-miss is worth spending Oils on.</p>
        <p>Oils are valuable enough that I generally save them for equipment I expect to keep.</p>
        <p>Any excellent permanent item can be worth developing.</p>
        <p>Weapons in particular can often benefit from <strong>Oils of Sharpness</strong>.</p>
        <p><strong>Oils of Accuracy</strong> are more situational because many top magical melee weapons already use King's, which naturally gives more To Hit than Accuracy Oils can add.</p>
        <p>Accuracy Oils become more valuable on strong weapons that lack their own To Hit, such as Civerb's Cudgel.</p>
        <p>Durability Oils and Hidden Shrines can eventually be used to push good equipment toward 255 durability and indestructibility.</p>
        <p>The main rule is simple:</p>
        <aside class="guide-callout guide-callout-warning" aria-label="Oil investment warning">
          <p class="guide-callout-title">Save rare Oils for permanent gear</p>
          <p>Don't spend a pile of rare Oils on a temporary item you're already planning to replace.</p>
        </aside>
      </section>
      <section id="gold-management" class="guide-section">
        <h2>Gold Management</h2>
        <p>High-end shopping requires a lot of gold.</p>
        <p>Even when the individual refreshes are cheap, the final item may cost close to the shop maximum.</p>
        <p>Good gold sources include:</p>
        <ul class="guide-list">
          <li>Selling magical equipment from Hell runs</li>
          <li>Warlord of Blood runs</li>
          <li>Weapon and Armor Racks</li>
          <li>Lazarus runs</li>
          <li>Normal high-level dungeon farming</li>
        </ul>
        <p>Warlord runs are particularly convenient in DevilutionX when multiplayer quests are enabled and quest randomization is disabled.</p>
        <p>The Warlord room provides:</p>
        <ul class="guide-list">
          <li>A guaranteed boss drop</li>
          <li>2 Armor Racks</li>
          <li>6 Weapon Racks</li>
        </ul>
        <p>Most of the equipment will be junk, but junk sells.</p>
        <aside class="guide-callout guide-callout-tip" aria-label="Gold management tip">
          <p class="guide-callout-title">Fund the session first</p>
          <p>Build up the gold reserve before serious shopping. Finding the item you've spent hours targeting and then realizing you cannot afford it is avoidable.</p>
        </aside>
      </section>
      <section id="a-practical-targeted-shopping-workflow" class="guide-section">
        <h2>A Practical Targeted Shopping Workflow</h2>
        <p>When casually playing, there is no reason to calculate every Griswold visit.</p>
        <p>Just check the shops when you return to town and buy anything obviously useful.</p>
        <p>Targeted shopping is different.</p>
        <p>If you're hunting a specific high-end item:</p>
        <ol class="guide-list guide-list-ordered">
          <li><strong>Choose the exact base item.</strong></li>
          <li><strong>Choose the exact prefix and suffix.</strong></li>
          <li><strong>Check the <a class="guide-tool-link" href="<?= site_url('calculators/premium-item-checker/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Premium Item Checker (opens in a new tab)">Premium Item Checker</a> to make sure the combination is valid.</strong></li>
          <li><strong>Use Jarulf's Guide for detailed affix and item information when needed.</strong></li>
          <li><strong>Use the <a class="guide-tool-link" href="<?= site_url('calculators/shop-qlvl/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Shop Qlvl Calculator (opens in a new tab)">Shop Qlvl Calculator</a> to find the best shopper level and Griswold slots.</strong></li>
          <li><strong>Use the <a class="guide-tool-link" href="<?= site_url('calculators/hellfire-item-price/') ?>" target="_blank" rel="noopener" aria-label="Hellfire Item Price Calculator (opens in a new tab)">Hellfire Item Price Calculator</a> if the item is expensive.</strong></li>
          <li><strong>Choose the shopper class that removes the most unwanted equipment without removing your target.</strong></li>
          <li><strong>Set the shopper's stats carefully.</strong></li>
          <li><strong>Carry the best price anchors you have for every relevant item type.</strong></li>
          <li><strong>Back up the shopper at useful character levels.</strong></li>
          <li><strong>Check only the shop slots that can actually generate the target.</strong></li>
          <li><strong>Refresh as efficiently as possible.</strong></li>
        </ol>
        <p>Once those variables are controlled, shopping becomes a form of targeted item farming rather than simply checking Griswold and hoping he happens to have something good.</p>
      </section>
      <section id="shopping-and-dungeon-farming-work-together" class="guide-section">
        <h2>Shopping and Dungeon Farming Work Together</h2>
        <p>Shopping does not replace dungeon farming.</p>
        <p>Use Griswold and Wirt when their generation rules make a target practical.</p>
        <p>Farm the dungeon for:</p>
        <ul class="guide-list">
          <li>Jewelry in multiplayer</li>
          <li>Unique items</li>
          <li>High-level boss drops</li>
          <li>Equipment the shops cannot normally generate</li>
          <li>Better shopping anchors</li>
          <li>Gold</li>
        </ul>
        <p>Check Adria throughout the process for books, charged staves, and elixirs.</p>
        <p>Hellfire's shopping system rewards understanding exactly what the game is allowed to generate and then removing as much of the unwanted pool as possible.</p>
        <p>Understanding the shop rules lets you narrow the possible results substantially, which makes targeted shopping much more efficient than blindly checking vendors and hoping for the best.</p>
      </section>
    </div>

    <footer class="guide-footer">
      <div class="guide-topic-row">
        <span class="guide-topic-label">Topics</span>
        <ul class="guide-tags">
          <li><a class="guide-tag" href="<?= site_url('guides/') ?>">Hellfire</a></li>
          <li><a class="guide-tag" href="<?= site_url('guides/') ?>">Shopping</a></li>
          <li><a class="guide-tag" href="<?= site_url('guides/') ?>">Item Generation</a></li>
        </ul>
      </div>
    </footer>
  </article>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/public_footer.php'; ?>
