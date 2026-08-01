/**
 * Split a query into significant words (whitespace-separated).
 * @param {string} query
 * @returns {string[]}
 */
export function searchTokens(query) {
  return String(query || '')
    .trim()
    .toLowerCase()
    .split(/\s+/)
    .filter(Boolean);
}

/**
 * True when every query word appears in the item name (AND match).
 * @param {string} name
 * @param {string} query
 * @returns {boolean}
 */
export function nameMatchesAllWords(name, query) {
  const tokens = searchTokens(query);
  if (!tokens.length) return true;
  const hay = String(name || '').toLowerCase();
  return tokens.every((token) => hay.includes(token));
}
