/**
 * Category helpers — sort/nest WooCommerce product_cat terms by parent.
 */

/**
 * @param {Array<{id:number,name:string,parent:number}>} categories
 * @returns {Array<{id:number,name:string,parent:number,children:Array}>}
 */
export function buildCategoryTree(categories) {
  const list = Array.isArray(categories) ? categories : [];
  const byParent = new Map();

  list.forEach((cat) => {
    const parentId = Number(cat.parent) || 0;
    if (!byParent.has(parentId)) {
      byParent.set(parentId, []);
    }
    byParent.get(parentId).push(cat);
  });

  const sortByName = (a, b) =>
    String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' });

  function branch(parentId) {
    const kids = (byParent.get(parentId) || []).slice().sort(sortByName);
    return kids.map((cat) => ({
      ...cat,
      children: branch(cat.id),
    }));
  }

  return branch(0);
}

/**
 * Flat list of root (main) categories only.
 *
 * @param {Array<{id:number,parent:number}>} categories
 */
export function getRootCategories(categories) {
  return (categories || [])
    .filter((cat) => !Number(cat.parent))
    .slice()
    .sort((a, b) =>
      String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' })
    );
}

/**
 * Children of a given parent id.
 *
 * @param {Array<{id:number,parent:number}>} categories
 * @param {number} parentId
 */
export function getChildCategories(categories, parentId) {
  const pid = Number(parentId) || 0;
  return (categories || [])
    .filter((cat) => Number(cat.parent) === pid)
    .slice()
    .sort((a, b) =>
      String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' })
    );
}

/**
 * All nested categories under a root, depth-first, with depth starting at 1.
 *
 * @param {Array<{id:number,parent:number,name:string}>} categories
 * @param {number} rootId
 * @returns {Array<{id:number,name:string,parent:number,depth:number}>}
 */
export function getNestedCategories(categories, rootId) {
  const result = [];

  function walk(parentId, depth) {
    getChildCategories(categories, parentId).forEach((cat) => {
      result.push({ ...cat, depth });
      walk(cat.id, depth + 1);
    });
  }

  walk(Number(rootId) || 0, 1);
  return result;
}

/**
 * Find the top-level ancestor id for a category (itself if root).
 *
 * @param {Array<{id:number,parent:number}>} categories
 * @param {number} categoryId
 * @returns {number}
 */
export function getRootAncestorId(categories, categoryId) {
  const byId = new Map((categories || []).map((c) => [c.id, c]));
  let current = byId.get(Number(categoryId));
  if (!current) return 0;

  const seen = new Set();
  while (current && Number(current.parent) && !seen.has(current.id)) {
    seen.add(current.id);
    const parent = byId.get(Number(current.parent));
    if (!parent) break;
    current = parent;
  }
  return current ? current.id : 0;
}

/**
 * Options for <select>, indented by depth.
 *
 * @param {Array<{id:number,name:string,parent:number}>} categories
 * @returns {Array<{id:number,name:string,label:string,depth:number}>}
 */
export function flattenCategoriesForSelect(categories) {
  const tree = buildCategoryTree(categories);
  const out = [];

  function walk(nodes, depth) {
    nodes.forEach((node) => {
      const pad = depth > 0 ? `${'— '.repeat(depth)}` : '';
      out.push({
        id: node.id,
        name: node.name,
        depth,
        label: `${pad}${node.name}`,
      });
      if (node.children?.length) {
        walk(node.children, depth + 1);
      }
    });
  }

  walk(tree, 0);
  return out;
}
