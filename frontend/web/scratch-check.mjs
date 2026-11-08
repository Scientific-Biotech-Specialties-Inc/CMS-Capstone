import { chromium } from '@playwright/test'

async function check() {
  const browser = await chromium.launch({ headless: true })
  const context = await browser.newContext()
  const page = await context.newPage()

  const consoleLogs = []
  page.on('console', msg => consoleLogs.push(`[${msg.type()}] ${msg.text()}`))
  page.on('pageerror', err => consoleLogs.push(`[PAGE_ERROR] ${err.stack || err.message}`))

  try {
    // 1. Visit base URL first to set localStorage
    await page.goto('http://localhost:5001/cms/', { waitUntil: 'domcontentloaded' })
    await page.evaluate(() => {
      const mockUser = {
        id: 1,
        email: 'sales@example.com',
        first_name: 'Sales',
        last_name: 'Rep',
        role: 'Sales Representative',
        profile: {
          role: {
            name: 'Sales Representative',
            nav_group: 'staff'
          }
        },
        permissions: ['cms.ai.contract_generator']
      }
      localStorage.setItem('user', JSON.stringify(mockUser))
      localStorage.setItem('access_token', 'mock-valid-token')
    })

    console.log('Navigating to http://localhost:5001/cms/sales/contracts/editor ...')
    await page.goto('http://localhost:5001/cms/sales/contracts/editor', { waitUntil: 'networkidle', timeout: 15000 })
    await page.waitForTimeout(3000)

    console.log('--- CONSOLE LOGS ---')
    consoleLogs.forEach(l => console.log(l))

    const title = await page.title()
    console.log('Page Title:', title)
    const url = page.url()
    console.log('Current URL:', url)

    const appHtml = await page.evaluate(() => document.getElementById('app')?.innerHTML?.slice(0, 1000))
    console.log('App HTML snippet:', appHtml)
  } catch (err) {
    console.error('Error during check:', err)
    console.log('--- CONSOLE LOGS COLLECTED ---')
    consoleLogs.forEach(l => console.log(l))
  } finally {
    await browser.close()
  }
}

check()
