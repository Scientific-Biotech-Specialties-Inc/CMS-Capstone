import { reactive, computed, type ComputedRef } from 'vue'
import type { ValidatorFn } from '@/lib/validation'

export type FormSchema<T extends Record<string, any>> = {
  [K in keyof T]?: ValidatorFn[]
}

export interface UseFormValidationReturn<T extends Record<string, any>> {
  errors: Record<string, string | null>
  touched: Record<string, boolean>
  validateField: (field: keyof T) => boolean
  validateAll: () => boolean
  touchAll: () => void
  resetValidation: () => void
  isValid: ComputedRef<boolean>
}

/**
 * Lightweight, reactive form validation composable (OWASP Step 6 / Item #15).
 */
export function useFormValidation<T extends Record<string, any>>(
  formData: T,
  schema: FormSchema<T>
): UseFormValidationReturn<T> {
  const fields = Object.keys(schema) as (keyof T & string)[]

  const errors = reactive<Record<string, string | null>>({})
  const touched = reactive<Record<string, boolean>>({})

  for (const field of fields) {
    errors[field] = null
    touched[field] = false
  }

  function validateField(field: keyof T): boolean {
    const key = field as string
    const rules = schema[field] ?? []
    const val = formData[field]
    for (const rule of rules) {
      const err = rule(val)
      if (err) {
        errors[key] = err
        return false
      }
    }
    errors[key] = null
    return true
  }

  function validateAll(): boolean {
    let allValid = true
    for (const field of fields) {
      touched[field] = true
      const ok = validateField(field)
      if (!ok) {
        allValid = false
      }
    }
    return allValid
  }

  function touchAll(): void {
    for (const field of fields) {
      touched[field] = true
      validateField(field)
    }
  }

  function resetValidation(): void {
    for (const field of fields) {
      errors[field] = null
      touched[field] = false
    }
  }

  const isValid = computed(() => {
    for (const field of fields) {
      const rules = schema[field] ?? []
      const val = formData[field]
      for (const rule of rules) {
        if (rule(val)) return false
      }
    }
    return true
  })

  return {
    errors,
    touched,
    validateField,
    validateAll,
    touchAll,
    resetValidation,
    isValid,
  }
}
