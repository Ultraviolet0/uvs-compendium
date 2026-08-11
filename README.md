# UV's Compendium

UV's Compendium is a Diablo I and Hellfire reference site focused on preserving useful game knowledge, updating older community tools, and making obscure mechanics easier to check in modern DevilutionX play.

The project is especially interested in Hellfire support. A lot of classic Diablo I resources were written before Hellfire multiplayer was practical or common. With DevilutionX making multiplayer Hellfire possible, old tools and documentation often need another pass so Hellfire classes, items, spells, affixes, vendors, and edge cases are represented clearly.

## Purpose

This site exists to collect and modernize practical Diablo I and Hellfire knowledge.

The main goals are:

- Preserve classic Diablo I and Hellfire mechanics research.
- Update older calculators with Hellfire-specific data where applicable.
- Make shop, item, damage, repair, and pricing mechanics easier to test.
- Keep community knowledge available in a lightweight, fast, readable format.
- Credit earlier community work while adapting it for modern DevilutionX use.

This is not intended to replace foundational resources like Jarulf's Guide, Ghast's Grotto, or DevilutionX community research. It is meant to sit beside them as a practical compendium and calculator hub.

## Current Calculator Tools

The compendium currently includes:

- **Hellfire Item Price Calculator**  
  Estimates Hellfire item prices using item class, base item, prefix, suffix, variable affix values, and vendor source.

- **Hellfire Shop Qlvl Calculator**  
  Checks base-item and affix qlvl ranges available from Hellfire shop sources based on character level.

- **Hellfire Premium Item Checker**  
  Checks valid premium item combinations, affix data, source availability, and detailed price ranges for Diablo I and Hellfire items.

- **Warrior Repair Calculator**  
  Plans guaranteed Warrior repair durability-loss paths for deterministic one-cycle repair cases.

- **Hellfire Damage Calculator**  
  Calculates physical and spell damage behavior using Hellfire-focused character, stat, spell, weapon, and monster-resistance data.

Each calculator has a standalone page and is also included on the combined calculators page.

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
  Standalone page wrapper for that calculator.

- `calculator.php`  
  The reusable calculator body. This is the single source of truth for the calculator markup.

- `css/styles.css`  
  Calculator-specific styling.

- `js/scripts.js`  
  Calculator-specific behavior.

- `calculators/css/styles.css`  
  Shared calculator layout and form styles.

- `calculators/index.php`  
  Combined calculator page that includes each calculator's `calculator.php`.

This keeps repeated markup low and makes it easier to update a calculator once while having the change appear on both its standalone page and the combined calculators page.

## Guide Architecture

Guide entries use the shared site header and footer, the reusable in-page navigation component, and a guide-specific stylesheet:

```text
guides/
  template/
    index.php
  css/
    styles.css

css/
  in-page-navigation.css

js/
  in-page-navigation.js
```

- `guides/template/index.php` demonstrates the semantic article structure and reusable guide components. Link to it using the clean `guides/template/` directory URL.
- `guides/css/styles.css` contains guide-only typography, figures, callouts, tables, cards, metadata, and responsive article layout rules.
- `css/in-page-navigation.css` provides the shared sticky panel, nested-link styling, responsive presentation, and themed scrollbar used by guides and the combined calculator page.
- `js/in-page-navigation.js` is the shared opt-in navigation builder used by both guides and the combined calculator page.

To create a guide, copy the template, update its metadata and article content, and keep section `h2` and `h3` elements inside `#guide-content`. The script assigns missing heading IDs, builds the table of contents, highlights the current section, and can omit any heading marked with `data-in-page-nav-ignore`.

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
  css/
    styles.css

  hellfire-item-price/
  shop-qlvl/
  premium-item-checker/
  warrior-repair/
  hellfire-damage/

docs/
references/
guides/
  index.php
  fast-character-development/
    index.php
  shopping/
    index.php
  template/
    index.php
  css/
    styles.css
```

The global stylesheet and script are reserved for site-wide layout, navigation, typography, and shared behavior. Calculator-specific styles and scripts are loaded only on the pages that need them.

## Design Philosophy

UV's Compendium is intentionally lightweight. It favors:

- Plain PHP includes over a heavy framework.
- Page-specific CSS and JavaScript instead of one large bundle.
- Fast-loading pages.
- Readable, maintainable calculator code.
- Old-school reference-site utility with modern responsive behavior.

Modern coding practices are used where they help maintainability, accessibility, and clarity, but the main goal is not to chase trends. The main goal is to keep useful Diablo and Hellfire information alive, accurate, and easy to use.

## Credits and Sources

This project builds on decades of Diablo community research and toolmaking.

Important sources and inspirations include:

- Jarulf's Guide
- Ghast's Grotto
- DevilutionX
- The DevilutionX community
- Classic Diablo and Hellfire calculator authors and document maintainers

Individual calculator pages include more specific credit notes where applicable.

## Disclaimer

UV's Compendium is an unofficial fan project. It is not affiliated with Blizzard Entertainment, GOG, DevilutionX, or any original Diablo rights holders.

Diablo and Hellfire belong to their respective owners. This project is intended for preservation, study, and community use.
