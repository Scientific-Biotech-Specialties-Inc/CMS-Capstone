import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'

// Intercept mangled auth callbacks from proxy before Vue Router even boots
if (window.location.href.includes('auth/callback')) {
  console.log('%c[CMS Auth Intercept] Caught mangled callback URL!', 'color: #10b981; font-weight: bold; font-size: 14px;')
  console.log('Raw Browser URL:', window.location.href)

  const match = window.location.href.match(/state=([^&]+)/)
  const target = match ? decodeURIComponent(match[1]) : '/cms/admin/dashboard'
  const safeTarget = target.startsWith('/cms') ? target : `/cms${target.startsWith('/') ? '' : '/'}${target}`
  
  console.log('Successfully extracted target. Redirecting to:', safeTarget)
  
  // Wait 1.5 seconds before redirecting so the user can read the console log!
  setTimeout(() => {
    window.location.replace(safeTarget)
  }, 1500)
} else {
  const app = createApp(App)
  app.use(router)
  app.mount('#app')
}
