// Jarulf 1.62, section 3.9: Adria and Griswold's basic stock use the
// deepest dungeon level visited in single player, or character level in MP.
function townItemLevel({ gameMode, characterLevel, dungeonLevel }) {
  return gameMode === 'single-player'
    ? Math.min(16, Math.max(6, dungeonLevel + 2))
    : Math.min(16, Math.max(6, Math.floor(characterLevel / 2) + 2));
}

export { townItemLevel };
