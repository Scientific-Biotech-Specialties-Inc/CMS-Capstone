import { BRAND_PALETTE } from '@/constants/theme'

// Role names come from auth-module (custom roles possible), so this stays
// open. Tabs Badges for unknown roles fall back to a neutral style.
export type Role   = string
export type Status = 'Active' | 'Inactive'

export interface User {
  id:        string
  name:      string
  email:     string
  role:      Role
  status:    Status
  dateAdded: string
  department?: string
}

const KNOWN_ROLE_BADGES: Record<string, string> = {
  Admin:   'bg-brand-navy/8 text-brand-navy border-brand-navy/20',
  Manager: 'bg-brand-dark/8 text-brand-dark border-brand-dark/20',
  Employee:'bg-brand-blue/8 text-brand-blue border-brand-blue/20',
}

/** @deprecated Use roleBadgeFor() — unknown (custom) roles need the fallback. */
export const roleBadge: Record<string, string> = KNOWN_ROLE_BADGES

export function roleBadgeFor(role: string): string {
  return KNOWN_ROLE_BADGES[role] ?? 'bg-black/[0.04] text-black/55 border-black/10'
}

export interface RoleTab {
  label: string
  value: string
}

/**
 * Dynamic User Management tabs: "View all" plus one tab per
 * CMS-accessible role (from useRoleSystems). Pure — unit-tested.
 */
export function buildRoleTabs(cmsRoleNames: string[]): RoleTab[] {
  return [
    { label: 'View all', value: 'all' },
    ...cmsRoleNames.map(name => ({ label: name, value: name })),
  ]
}

const ROLE_PLURALS: Record<string, string> = {
  Sales: 'Sales',
}

export function pluralRoleName(name: string): string {
  return ROLE_PLURALS[name] ?? `${name}s`
}

export const palette = BRAND_PALETTE

export function getInitials(name: string) {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

export function avatarColor(idx: number) {
  return palette[idx % palette.length]
}
