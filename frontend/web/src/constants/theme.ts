/**
 * CRMS Capstone — Central Brand Color Variable System
 *
 * Single source of truth for design system brand colors in JS/TS contexts.
 * Values correspond directly to CSS Custom Properties in src/style.css:
 *   --brand-navy: #252578
 *   --brand-blue: #2E85D8
 *   --brand-dark: #2F2F73
 *
 * Modifying the variables in src/style.css updates the whole Tailwind UI,
 * while updating this file guarantees charts, SVG canvases, and avatar
 * fallbacks stay synchronized.
 */

// Raw hex constants (essential for SVG charts, canvas, and third-party libs)
export const BRAND_HEX = {
  navy: '#252578',
  blue: '#2E85D8',
  dark: '#2F2F73',
} as const

// CSS variable references (for inline CSS style bindings)
export const BRAND_COLORS = {
  navy: 'var(--brand-navy, #252578)',
  blue: 'var(--brand-blue, #2E85D8)',
  dark: 'var(--brand-dark, #2F2F73)',
} as const

// Restricted avatar and chart color palette (per AGENTS.md design system rule)
export const BRAND_PALETTE = [
  BRAND_HEX.navy,
  BRAND_HEX.blue,
  BRAND_HEX.dark,
] as const

/**
 * Reads the live CSS variable value at runtime.
 */
export function getBrandColor(name: 'navy' | 'blue' | 'dark'): string {
  if (typeof window !== 'undefined') {
    const val = getComputedStyle(document.documentElement).getPropertyValue(`--brand-${name}`).trim()
    if (val) return val
  }
  return BRAND_HEX[name]
}
