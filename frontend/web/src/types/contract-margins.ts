export interface PageMargins {
  top: number    // px, e.g. 48
  bottom: number // px, e.g. 48
  left: number   // px, e.g. 64
  right: number  // px, e.g. 64
}

export type MarginPreset = 'normal' | 'narrow' | 'moderate' | 'wide' | 'custom'

export const MARGIN_PRESETS: Record<Exclude<MarginPreset, 'custom'>, PageMargins> = {
  normal: { top: 72, bottom: 72, left: 72, right: 72 },    // 1.0 inch (at 72-96 dpi scale)
  narrow: { top: 36, bottom: 36, left: 36, right: 36 },    // 0.5 inch
  moderate: { top: 54, bottom: 54, left: 54, right: 54 },  // 0.75 inch
  wide: { top: 72, bottom: 72, left: 96, right: 96 },      // 1.25 inch sides
}
