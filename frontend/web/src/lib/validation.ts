/**
 * Lightweight, pure validation rule factories for frontend forms (OWASP Step 6 / Item #15).
 */

export type ValidatorFn = (value: unknown) => string | null

export const required = (message = 'This field is required.'): ValidatorFn => {
  return (value: unknown) => {
    if (value === null || value === undefined) return message
    if (typeof value === 'string' && value.trim().length === 0) return message
    if (Array.isArray(value) && value.length === 0) return message
    return null
  }
}

export const email = (message = 'Please enter a valid email address.'): ValidatorFn => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return (value: unknown) => {
    if (!value || typeof value !== 'string') return null
    return emailRegex.test(value.trim()) ? null : message
  }
}

export const minLength = (min: number, message?: string): ValidatorFn => {
  const defaultMsg = `Must be at least ${min} characters.`
  return (value: unknown) => {
    if (!value || typeof value !== 'string') return null
    return value.trim().length >= min ? null : (message ?? defaultMsg)
  }
}

export const maxLength = (max: number, message?: string): ValidatorFn => {
  const defaultMsg = `Must not exceed ${max} characters.`
  return (value: unknown) => {
    if (!value || typeof value !== 'string') return null
    return value.length <= max ? null : (message ?? defaultMsg)
  }
}

export const pattern = (regex: RegExp, message: string): ValidatorFn => {
  return (value: unknown) => {
    if (!value || typeof value !== 'string') return null
    return regex.test(value) ? null : message
  }
}

export const phPhone = (message = 'Must be an 11-digit mobile number (e.g. 09171234567).'): ValidatorFn => {
  return pattern(/^09\d{9}$/, message)
}

export const tin = (message = 'Must be a valid TIN (e.g. 123-456-789 or 123-456-789-000).'): ValidatorFn => {
  return pattern(/^\d{3}-\d{3}-\d{3}(-\d{3,5})?$/, message)
}
