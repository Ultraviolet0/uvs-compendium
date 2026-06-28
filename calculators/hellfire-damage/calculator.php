<?php
$hellfire_damage_heading_level = $hellfire_damage_heading_level ?? 'h1';

if (!in_array($hellfire_damage_heading_level, ['h1', 'h2', 'h3'], true)) {
  $hellfire_damage_heading_level = 'h1';
}

$hellfire_damage_section_heading_level = $hellfire_damage_heading_level === 'h1' ? 'h2' : 'h3';
?>
<section class="section-panel calculator-page hellfire-damage-page flow-lg" aria-labelledby="hellfire-damage-title">
  <header class="flow">
    <p class="eyebrow">Calculator</p>
    <<?= $hellfire_damage_heading_level ?> id="hellfire-damage-title">Hellfire Damage Calculator</<?= $hellfire_damage_heading_level ?>>
    <p class="hero-copy">Calculate physical attack damage and direct spell damage ranges for Diablo: Hellfire. Class selection auto-fills naked max Strength, Magic, Dexterity, and Vitality, but every stat remains manually editable.</p>
    <p class="warning-text"><strong>Physical order used:</strong> weapon roll → weapon % → flat +damage → character damage → crit/type/Civerb/Devastation/Jester/quarter/Peril.</p>
  </header>

  <form id="hellfire-damage-calculator" class="hellfire-damage-form" action="#" novalidate>
    <section class="damage-section" aria-labelledby="hellfire-damage-character-heading">
      <<?= $hellfire_damage_section_heading_level ?> id="hellfire-damage-character-heading" class="result-heading">Character</<?= $hellfire_damage_section_heading_level ?>>
      <p class="damage-section-note">Changing class auto-fills naked max Strength, Magic, Dexterity, and Vitality.</p>

      <div class="damage-grid damage-grid-2">
        <div class="form-field calculator-field">
          <label for="characterClass">Character type</label>
          <select id="characterClass">
            <option value="warrior">Warrior</option>
            <option value="rogue">Rogue</option>
            <option value="sorcerer">Sorcerer</option>
            <option value="monk">Monk</option>
            <option value="bard">Bard</option>
            <option value="barbarian">Barbarian</option>
          </select>
        </div>

        <div class="form-field calculator-field">
          <label for="characterLevel">Character level</label>
          <input id="characterLevel" type="number" min="1" max="50" step="1" value="50">
        </div>
      </div>

      <div class="damage-grid damage-grid-4 damage-stat-grid">
        <div class="form-field calculator-field">
          <label for="strength">Effective Strength</label>
          <input id="strength" type="number" min="0" max="999" step="1" value="250">
        </div>
        <div class="form-field calculator-field">
          <label for="magic">Effective Magic</label>
          <input id="magic" type="number" min="0" max="999" step="1" value="50">
        </div>
        <div class="form-field calculator-field">
          <label for="dexterity">Effective Dexterity</label>
          <input id="dexterity" type="number" min="0" max="999" step="1" value="60">
        </div>
        <div class="form-field calculator-field">
          <label for="vitality">Effective Vitality</label>
          <input id="vitality" type="number" min="0" max="999" step="1" value="100">
        </div>
      </div>
    </section>

    <section class="damage-section" aria-labelledby="hellfire-damage-physical-heading">
      <<?= $hellfire_damage_section_heading_level ?> id="hellfire-damage-physical-heading" class="result-heading">Physical Attack</<?= $hellfire_damage_section_heading_level ?>>
      <p class="damage-section-note">Weapon +% damage applies only to weapon damage. Later checkboxes apply to total damage when valid.</p>

      <div class="damage-grid damage-grid-2">
        <div class="form-field calculator-field">
          <label for="weaponType">Weapon type</label>
          <select id="weaponType">
            <option value="sword">Sword</option>
            <option value="club">Club / blunt</option>
            <option value="axe">Axe</option>
            <option value="bow">Bow</option>
            <option value="staff">Staff</option>
            <option value="unarmed">Unarmed / hands-feet</option>
            <option value="shield">Shield as weapon</option>
          </select>
        </div>

        <label class="damage-checkline damage-align-end" id="shieldRow">
          <input id="hasShield" type="checkbox">
          <span>Shield equipped with weapon</span>
        </label>
      </div>

      <div class="damage-grid damage-grid-3">
        <div class="form-field calculator-field">
          <label for="weaponMin">Weapon min damage</label>
          <input id="weaponMin" type="number" min="0" max="999" step="0.1" value="6">
        </div>
        <div class="form-field calculator-field">
          <label for="weaponMax">Weapon max damage</label>
          <input id="weaponMax" type="number" min="0" max="999" step="0.1" value="15">
        </div>
        <div class="form-field calculator-field">
          <label for="weaponPercent">Weapon +% damage</label>
          <input id="weaponPercent" type="number" min="0" max="500" step="1" value="0">
        </div>
      </div>

      <div class="damage-grid damage-grid-2">
        <div class="form-field calculator-field">
          <label for="flatDamage">Flat +damage</label>
          <input id="flatDamage" type="number" min="0" max="999" step="0.1" value="0">
        </div>
        <label class="damage-checkline damage-align-end">
          <input id="autoSpecialDamage" type="checkbox" checked>
          <span>Auto-fill unarmed/shield special damage</span>
        </label>
      </div>

      <div class="damage-extras">
        <label class="damage-checkline" id="bardSwordRow">
          <input id="bardHasSword" type="checkbox">
          <span>Bard has at least one sword equipped</span>
        </label>
        <label class="damage-checkline" id="criticalRow">
          <input id="criticalHit" type="checkbox">
          <span>Force critical hit</span>
        </label>
        <label class="damage-checkline" id="civerbRow">
          <input id="civerbDemons" type="checkbox">
          <span>+200% damage vs demons, e.g. Civerb's Cudgel</span>
        </label>
        <label class="damage-checkline" id="devastationRow">
          <input id="devastation" type="checkbox">
          <span>Devastation proc: ×3 total damage</span>
        </label>
        <label class="damage-checkline" id="jesterRow">
          <input id="jester" type="checkbox">
          <span>Jester's prefix: ×0 to ×6-ish (avg ×1.985)</span>
        </label>
        <label class="damage-checkline" id="quarterRow">
          <input id="quarterDamage" type="checkbox">
          <span>Adjacent quarter damage: ÷4</span>
        </label>
        <label class="damage-checkline" id="perilRow">
          <input id="peril" type="checkbox">
          <span>Peril suffix: ×2 total damage</span>
        </label>
      </div>
    </section>

    <section class="damage-section" aria-labelledby="hellfire-damage-physical-output-heading" aria-live="polite">
      <<?= $hellfire_damage_section_heading_level ?> id="hellfire-damage-physical-output-heading" class="result-heading">Physical Output</<?= $hellfire_damage_section_heading_level ?>>

      <div class="damage-summary-grid">
        <div>
          <span class="damage-summary-label">Character damage</span>
          <strong id="characterDamageOut">—</strong>
        </div>
        <div>
          <span class="damage-summary-label">Weapon after +% and flat</span>
          <strong id="weaponDamageOut">—</strong>
        </div>
        <div>
          <span class="damage-summary-label">Screen/base damage (min / avg / max)</span>
          <strong id="screenDamageOut">—</strong>
        </div>
      </div>

      <div class="damage-table-wrap">
        <table class="damage-table">
          <thead>
            <tr>
              <th>Enemy category</th>
              <th>Minimum</th>
              <th>Average</th>
              <th>Maximum</th>
              <th>Applied category rule</th>
            </tr>
          </thead>
          <tbody id="resultRows"></tbody>
        </table>
      </div>

      <div class="damage-notes" id="notes"></div>
    </section>

    <section class="damage-section" aria-labelledby="hellfire-damage-spell-heading">
      <<?= $hellfire_damage_section_heading_level ?> id="hellfire-damage-spell-heading" class="result-heading">Spell Damage</<?= $hellfire_damage_section_heading_level ?>>
      <p class="damage-section-note">Outputs are per hit, per bolt, or per wall/flame tick depending on the selected spell. Resistance rows are generic: no resistance, resistant, and immune.</p>

      <div class="damage-grid damage-grid-3">
        <div class="form-field calculator-field">
          <label for="spellKey">Spell</label>
          <select id="spellKey"></select>
        </div>
        <div class="form-field calculator-field">
          <label for="spellLevel">Spell level</label>
          <select id="spellLevel"></select>
        </div>
        <div class="form-field calculator-field" id="targetHpRow">
          <label for="targetHp">Target HP, for Bone Spirit</label>
          <input id="targetHp" type="number" min="1" max="99999" step="1" value="1000">
        </div>
      </div>

      <div class="damage-spell-meta" id="spellMeta">—</div>
    </section>

    <section class="damage-section" aria-labelledby="hellfire-damage-spell-output-heading" aria-live="polite">
      <<?= $hellfire_damage_section_heading_level ?> id="hellfire-damage-spell-output-heading" class="result-heading">Spell Output</<?= $hellfire_damage_section_heading_level ?>>

      <div class="damage-summary-grid">
        <div>
          <span class="damage-summary-label">Selected spell</span>
          <strong id="spellNameOut">—</strong>
        </div>
        <div>
          <span class="damage-summary-label">Type / unit</span>
          <strong id="spellTypeOut">—</strong>
        </div>
        <div>
          <span class="damage-summary-label">Base damage (min / avg / max)</span>
          <strong id="spellBaseOut">—</strong>
        </div>
      </div>

      <div class="damage-table-wrap">
        <table class="damage-table">
          <thead>
            <tr>
              <th>Resistance case</th>
              <th>Minimum</th>
              <th>Average</th>
              <th>Maximum</th>
              <th>Rule</th>
            </tr>
          </thead>
          <tbody id="spellRows"></tbody>
        </table>
      </div>

      <div class="damage-notes" id="spellNotes"></div>
    </section>
  </form>
</section>
