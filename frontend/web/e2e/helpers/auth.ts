import { Page } from '@playwright/test'

interface User {
  id: number
  email: string
  first_name: string
  last_name: string
  role: string
  permissions: string[]
  department?: string
}

/**
 * Mocks the local storage authentication keys before any page script is executed.
 * This effectively bypasses the auth-service login redirect for E2E tests.
 */
export async function mockLogin(page: Page, role: 'Admin' | 'Manager' | 'Sales' = 'Admin') {
  const user: User = {
    id: 99,
    email: `${role.toLowerCase()}@cms-test.com`,
    first_name: 'Test',
    last_name: role,
    role: role,
    permissions: [
      'cms.dashboard.view',
      'cms.users.view',
      'cms.roles.view',
      'cms.partners.view',
      'cms.system.ocr',
      'cms.system.risk_assessment'
    ]
  }

  // Inject user and access_token into local storage before scripts load
  await page.addInitScript((data: { user: User }) => {
    window.localStorage.setItem('user', JSON.stringify(data.user))
    window.localStorage.setItem('access_token', 'mocked-jwt-token-for-e2e')
  }, { user })
}
