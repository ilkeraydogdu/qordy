/**
 * Icons Module
 * SVG icon generators for common UI elements
 */

/**
 * Generate X (close) icon SVG
 * @param {object} options - Icon options (class, size, etc.)
 * @returns {string} SVG HTML string
 */
export function iconX(options = {}) {
    const { class: className = 'w-5 h-5', size = 5 } = options;
    return `<svg class="${className}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
    </svg>`;
}

/**
 * Generate utensils icon SVG
 * @param {object} options - Icon options
 * @returns {string} SVG HTML string
 */
export function iconUtensils(options = {}) {
    const { class: className = 'w-10 h-10 text-gray-400' } = options;
    return `<svg class="${className}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
    </svg>`;
}

/**
 * Generate trash icon SVG
 * @param {object} options - Icon options
 * @returns {string} SVG HTML string
 */
export function iconTrash(options = {}) {
    const { class: className = 'w-5 h-5' } = options;
    return `<svg class="${className}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
    </svg>`;
}

/**
 * Generate check icon SVG
 * @param {object} options - Icon options
 * @returns {string} SVG HTML string
 */
export function iconCheck(options = {}) {
    const { class: className = 'w-5 h-5' } = options;
    return `<svg class="${className}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
    </svg>`;
}

/**
 * Generate loading spinner icon SVG
 * @param {object} options - Icon options
 * @returns {string} SVG HTML string
 */
export function iconSpinner(options = {}) {
    const { class: className = 'w-5 h-5 animate-spin' } = options;
    return `<svg class="${className}" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 .373 0 8h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>`;
}

