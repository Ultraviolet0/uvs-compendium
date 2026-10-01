# UV's Compendium

UV's Compendium is a Diablo I and Hellfire reference site focused on preserving useful game knowledge, updating older community tools, and making obscure mechanics easier to check in modern DevilutionX play.

The project is especially interested in Hellfire support. A lot of classic Diablo I resources were written before Hellfire multiplayer was practical or common. With DevilutionX making multiplayer Hellfire possible, old tools and documentation often need another pass so Hellfire classes, items, spells, affixes, vendors, and edge cases are represented clearly.

## Purpose

This site exists to collect and modernize practical Diablo I and Hellfire knowledge.

The main goals are:

- Preserve classic Diablo I and Hellfire mechanics research.
- Update older calculators with Hellfire-specific data where applicable.
- Make shop, item, damage, repair, and pricing mechanics easier to test.
- Publish practical written and video guides through a reusable article system.
- Keep community knowledge available in a lightweight, fast, readable format.
- Credit earlier community work while adapting it for modern DevilutionX use.

This is not intended to replace foundational resources like Jarulf's Guide, Ghast's Grotto, or DevilutionX community research. It is meant to sit beside them as a practical compendium and calculator hub.

## Current Calculator Tools

The compendium currently includes:

- **Item Price Calculator:** Estimates Diablo and Hellfire item prices using item class, base item, affixes, play mode, and vendor source.

- **Shop Qlvl Calculator:** Checks Diablo and Hellfire shop qlvl ranges using character level or single-player dungeon depth.

- **Premium Item Checker:** Checks Diablo and Hellfire item combinations, affix data, and mode-specific source availability.

- **Warrior Repair Calculator**  
  Plans guaranteed Warrior repair durability-loss paths for deterministic one-cycle repair cases.

- **Damage Calculator:** Calculates Diablo and Hellfire physical and spell damage using game-specific classes, item effects, and spells.

Each calculator has a standalone page and is also included on the combined calculators page. The combined page automatically builds a calculator navigator from its section headings so players can move quickly between several tools.

The Premium Item Checker and Item Price Calculator treat **Decay as a Hellfire suffix**, correcting its mistaken classification as a prefix in Jarulf's Guide.

## Current Guides

The guide catalog at `/guides/` currently includes:

- **Fast Character Development**  
  A leveling, gearing, spell-development, shrine-hunting, and farming route for Diablo I, Hellfire, and DevilutionX. It is based on GainTrain's original D1Legit strategy and has been rewritten and expanded for the compendium.

- **UV's Shopping & Affixes**  
  A detailed guide to vendor mechanics, qlvl ranges, ideal shopper levels, stat filtering, price anchors, shop limits, and targeted equipment shopping.

- **Max's Hellfire Shopping Video**  
  An internal guide page containing a responsive, privacy-enhanced YouTube embed, attribution, related shopping resources, and calculator links.

The reusable template remains available at `/guides/template/`, but it is deliberately omitted from the public navigation and guide catalog.

## Calculator Architecture

Calculators are organized so the reusable calculator body lives in one place and can be included wherever needed.

Typical structure:

```text
calculators/
  css/
    styles.css

  example-calculator/
    index.php
    calculator.php
    css/
      styles.css
    js/
      scripts.js
```

The intended separation is:

- `index.php`  
  Standalone page wrapper for that calculator. Public links target the calculator directory rather than the `index.php` filename.

- `calculator.php`  
  The reusable calculator body. This is the single source of truth for the calculator markup.

- `css/styles.css`  
  Calculator-specific styling.

- `js/scripts.js`  
  Calculator-specific behavior.

- `calculators/css/styles.css`  
  Shared calculator layout, breadcrumb, combined-page navigation, and form styles.

- `calculators/index.php`  
  Combined calculator page that includes each calculator's `calculator.php`.

- `calculators/breadcrumbs.php`  
  Shared calculator breadcrumb markup used by the calculator hub and standalone calculator pages.

This keeps repeated markup low and makes it easier to update a calculator once while having the change appear on both its standalone page and the combined calculators page.

## Guide Architecture

Guide entries use the shared site header and footer, guide-specific presentation styles, and the shared in-page navigation component:

```text
guides/
  index.php
  css/
    styles.css
  fast-character-development/
    index.php
  shopping/
    index.php
  max-shopping-video/
    index.php
  template/
    index.php

css/
  in-page-navigation.css
js/
  in-page-navigation.js
```

- `guides/template/index.php` demonstrates the semantic article structure and reusable guide components.
- `guides/css/styles.css` contains guide-only typography, breadcrumbs, figures, local video and YouTube embed styles, callouts, tables, cards, metadata, and responsive article rules.
- `css/in-page-navigation.css` contains the shared sticky navigation presentation used by guides and the combined calculators page.
- `js/in-page-navigation.js` builds the navigation from headings, assigns missing IDs, highlights the current section, and supports nested heading levels.

To create a guide, copy the template, update its metadata and article content, and keep section `h2` and `h3` elements inside `#guide-content`. The script assigns missing heading IDs, builds the table of contents, highlights the current section, and can omit any heading marked with `data-in-page-nav-ignore`.

Featured images belong inside `.guide-featured-figure`; images in that semantic container automatically receive the shared gold border and rounded corners. Local videos use `.guide-video`, while responsive YouTube embeds use `.guide-video-embed`.

Calculator links used as companion tools inside long guides open in a new tab, use `rel="noopener"`, and display a visible new-tab indicator with an accessible label.

## Shared In-Page Navigation

The guide table of contents and the All Calculators navigator intentionally reuse the same CSS and JavaScript.

The component provides:

- Automatic links generated from a configurable heading selector.
- Stable IDs for headings that do not already have one.
- Nested numbering for heading hierarchies.
- Active-section highlighting while scrolling.
- A sticky right-side panel on desktop.
- A balanced two-column layout on tablet widths.
- A collapsible single-column layout on mobile.
- A thin themed scrollbar matching the main site navigation.

The main viewport scrollbar also uses the site's gold styling while retaining a standard page-scrollbar width.

## Breadcrumbs and Clean URLs

Public guide and calculator links use directory URLs such as `/guides/shopping/` and `/calculators/shop-qlvl/`. Each directory contains an `index.php`, but filenames are not exposed in normal navigation.

Breadcrumbs reflect the site's hierarchy:

- Guide hub: `Home / Guides`
- Guide entry: `Home / Guides / Guide Title`
- Calculator hub: `Home / Calculators`
- Calculator entry: `Home / Calculators / Calculator Title`

The root homepage intentionally has no breadcrumb because it has no parent page. Hosted reference documents and external resources open directly rather than being wrapped in the site's breadcrumb structure.

## Project Structure

```text
css/
  styles.css
  in-page-navigation.css

js/
  scripts.js
  in-page-navigation.js

includes/
  public_header.php
  public_footer.php

calculators/
  index.php
  breadcrumbs.php
  css/
    styles.css

  hellfire-item-price/
  shop-qlvl/
  premium-item-checker/
  warrior-repair/
  hellfire-damage/

guides/
  index.php
  css/
    styles.css
  fast-character-development/
    index.php
  shopping/
    index.php
  max-shopping-video/
    index.php
  template/
    index.php

reference/
```

The global stylesheet and script are reserved for site-wide layout, navigation, typography, themed scrollbars, and shared behavior. Calculator- and guide-specific assets are loaded only on the pages that need them.

## Design Philosophy

UV's Compendium is intentionally lightweight. It favors:

- Plain PHP includes over a heavy framework.
- Page-specific CSS and JavaScript instead of one large bundle.
- Clean directory URLs instead of public `index.php` links.
- Fast-loading pages.
- Readable, maintainable calculator code.
- Guide text that uses the available article column instead of arbitrary character-width caps.
- Semantic markup, keyboard-friendly navigation, visible focus states, and accessible labels.
- Old-school reference-site utility with modern responsive behavior.

Modern coding practices are used where they help maintainability, accessibility, and clarity, but the main goal is not to chase trends. The main goal is to keep useful Diablo and Hellfire information alive, accurate, and easy to use.

## Development

See [development setup and workflow](docs/development.md) for local startup, validation, branching, and deployment boundaries. The [game-data source hierarchy](docs/game-data.md) and [corrections record](docs/corrections.md) govern mechanics changes.

## Credits and Sources

This project builds on decades of Diablo community research and toolmaking.

Important sources and inspirations include:

- Jarulf's Guide
- Ghast's Grotto
- DevilutionX
- The DevilutionX community
- GainTrain's original Fast Character Development strategy
- Max058028tingle's Hellfire shopping video
- Classic Diablo and Hellfire calculator authors and document maintainers

Individual calculator pages include more specific credit notes where applicable.

## Disclaimer

UV's Compendium is an unofficial fan project. It is not affiliated with Blizzard Entertainment, GOG, DevilutionX, or any original Diablo rights holders.

Diablo and Hellfire belong to their respective owners. This project is intended for preservation, study, and community use.
