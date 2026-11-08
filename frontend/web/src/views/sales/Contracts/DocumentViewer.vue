<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, FileText, Download, ExternalLink, Loader2, Printer, Menu, ChevronLeft, ChevronRight, ZoomIn, ZoomOut, RotateCcw } from 'lucide-vue-next'
import { useApiCache } from '@/composables/useApiCache'
import { useAuth } from '@/composables/useAuth'
import { setBreadcrumbTitle } from '@/composables/useBreadcrumbs'
import VuePdfEmbed from 'vue-pdf-embed'
import mammoth from 'mammoth'
import DOMPurify from 'dompurify'

// Import styles for annotation and text layers
import 'vue-pdf-embed/dist/styles/annotationLayer.css'
import 'vue-pdf-embed/dist/styles/textLayer.css'

const route = useRoute()
const router = useRouter()
const { state: cacheState, fetchContracts } = useApiCache()
const { state: authState } = useAuth()

const contractId = route.params.id as string
const docId = route.params.docId as string

const loading = ref(true)
const loadingFile = ref(false)
const contract = ref<any>(null)
watchEffect(() => {
  if (contract.value?.businessPartner) {
    setBreadcrumbTitle(contract.value.businessPartner)
  }
})
const pdfBlobUrl = ref('')

// DOCX state
const docxHtml = ref('')
const docxLoading = ref(false)
const docxFontSize = ref(15) // px — used as the zoom proxy for DOCX

const pageCount = ref(1)
const currentPage = ref(1)
const pdfWidth = ref(800)
const showSidebar = ref(true)

// ── PDF zoom ──────────────────────────────────────────────────────────────────
function zoomIn() {
  if (pdfWidth.value < 1600) pdfWidth.value += 100
}

function zoomOut() {
  if (pdfWidth.value > 400) pdfWidth.value -= 100
}

function resetZoom() {
  pdfWidth.value = 800
}

// ── DOCX zoom (font-size based) ───────────────────────────────────────────────
function docxZoomIn() {
  if (docxFontSize.value < 24) docxFontSize.value += 1
}

function docxZoomOut() {
  if (docxFontSize.value > 10) docxFontSize.value -= 1
}

function docxResetZoom() {
  docxFontSize.value = 15
}

const docxZoomLabel = computed(() =>
  Math.round((docxFontSize.value / 15) * 100) + '%'
)

// ── PDF navigation ────────────────────────────────────────────────────────────
function handlePdfLoaded(pdf: any) {
  pageCount.value = pdf.numPages || 1
}

function prevPage() {
  if (currentPage.value > 1) currentPage.value--
}

function nextPage() {
  if (currentPage.value < pageCount.value) currentPage.value++
}

function handlePageInput(e: Event) {
  const target = e.target as HTMLInputElement
  let val = parseInt(target.value, 10)
  if (isNaN(val)) return
  if (val < 1) val = 1
  if (val > pageCount.value) val = pageCount.value
  currentPage.value = val
  target.value = String(val)
}

// ── Data fetching ─────────────────────────────────────────────────────────────
const apiBase = import.meta.env.VITE_CONTRACT_API_URL as string

const fromCreate   = computed(() => route.query.from === 'create')
const docNameParam = computed(() => route.query.docName as string || '')
const docSizeParam = computed(() => Number(route.query.docSize) || 0)
const docTypeParam = computed(() => (route.query.docType as 'pdf' | 'docx') || 'pdf')

const snapshotVersion = computed(() => route.query.snapshotVersion as string || '')

const backPath = computed(() => {
  if (fromCreate.value) {
    const role = route.query.role as string || 'sales'
    return `/${role}/contracts/create`
  }
  if (route.query.from === 'amend') {
    const role = route.query.role as string || 'sales'
    return `/${role}/contracts/${contractId}/amend`
  }
  const fromAmd = route.query.fromAmd as string
  if (fromAmd) {
    if (route.path.startsWith('/manager')) return `/manager/amendment-requests/${fromAmd}`
    if (route.path.startsWith('/admin')) return `/admin/amendment-requests/${fromAmd}`
    return `/sales/contract-amendments/${fromAmd}`
  }
  const querySuffix = snapshotVersion.value ? `?snapshotVersion=${snapshotVersion.value}` : ''
  if (route.path.startsWith('/admin')) return `/admin/contracts/${contractId}${querySuffix}`
  if (route.path.startsWith('/manager')) return `/manager/contracts/${contractId}${querySuffix}`
  return `/sales/contracts/${contractId}${querySuffix}`
})

const document = computed(() => {
  if (fromCreate.value || route.query.from === 'amend') {
    return {
      id: docId,
      name: docNameParam.value,
      size: docSizeParam.value,
      type: docTypeParam.value
    }
  }
  if (!contract.value) return null
  return contract.value.docs.find((d: any) => String(d.id) === docId)
})

async function fetchFileBlob() {
  if (!docId) return
  loadingFile.value = true
  try {
    const res = await fetch(`${apiBase}/documents/${docId}/file`, {
      headers: {
        'Authorization': `Bearer ${authState.token}`,
        'Accept': 'application/json'
      }
    })
    if (res.ok) {
      const blob = await res.blob()

      if (pdfBlobUrl.value) {
        URL.revokeObjectURL(pdfBlobUrl.value)
      }
      pdfBlobUrl.value = URL.createObjectURL(blob)

      // If it's a DOCX, also convert via mammoth
      if (document.value?.type === 'docx') {
        docxLoading.value = true
        try {
          const arrayBuffer = await blob.arrayBuffer()
          const result = await mammoth.convertToHtml({ arrayBuffer })
          docxHtml.value = DOMPurify.sanitize(result.value)
        } catch (err) {
          console.error('mammoth conversion error:', err)
          docxHtml.value = '<p style="color:red">Failed to render document.</p>'
        } finally {
          docxLoading.value = false
        }
      }
    } else {
      console.error('Failed to fetch file from API')
    }
  } catch (err) {
    console.error('Error fetching file blob:', err)
  } finally {
    loadingFile.value = false
  }
}

onMounted(async () => {
  if (fromCreate.value) {
    loading.value = false
    await fetchFileBlob()
    return
  }

  // Try to find in cache first
  const cached = cacheState.contracts?.find(c => String(c.id) === contractId)
  if (cached) {
    contract.value = cached
    loading.value = false
    await fetchFileBlob()
  }

  // Load fresh contract details
  try {
    const isAdmin = route.path.startsWith('/admin')
    const isManager = route.path.startsWith('/manager')
    const userId = (isAdmin || isManager) ? undefined : authState.user?.id
    await fetchContracts(userId, true) // force reload
    const fresh = cacheState.contracts?.find(c => String(c.id) === contractId)
    if (fresh) {
      contract.value = fresh
      await fetchFileBlob()
    }
  } catch (err) {
    console.error('Failed to load contract:', err)
  } finally {
    loading.value = false
  }
})

onUnmounted(() => {
  if (pdfBlobUrl.value) {
    URL.revokeObjectURL(pdfBlobUrl.value)
  }
})

function downloadFile() {
  if (!pdfBlobUrl.value) return

  const a = window.document.createElement('a')
  a.href = pdfBlobUrl.value
  a.download = document.value?.name || 'document'
  window.document.body.appendChild(a)
  a.click()
  window.document.body.removeChild(a)
}

function printFile() {
  if (!pdfBlobUrl.value) return

  const iframe = window.document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = 'none'
  iframe.src = pdfBlobUrl.value

  window.document.body.appendChild(iframe)

  iframe.onload = () => {
    iframe.contentWindow?.focus()
    iframe.contentWindow?.print()
    setTimeout(() => {
      window.document.body.removeChild(iframe)
    }, 5000)
  }
}
</script>

<template>
  <div class="h-[calc(100vh-4rem)] flex flex-col bg-slate-50">
    <!-- Header -->
    <header class="h-16 border-b border-black/8 bg-white px-6 flex items-center justify-between shrink-0">
      <div class="flex items-center gap-4">
        <button @click="router.push(backPath)" 
          class="flex items-center justify-center w-8 h-8 rounded-full border border-black/8 hover:bg-black/2 transition text-black/60 hover:text-black">
          <ArrowLeft class="w-4 h-4" />
        </button>
        <div v-if="document" class="flex items-center gap-2.5">
          <!-- PDF icon (red) -->
          <div v-if="document.type === 'pdf'"
            class="w-8 h-8 rounded bg-red-50 border border-red-100 flex items-center justify-center text-red-500">
            <FileText class="w-4.5 h-4.5" />
          </div>
          <!-- DOCX icon (blue) -->
          <div v-else
            class="w-8 h-8 rounded bg-blue-50 border border-blue-100 flex items-center justify-center text-brand-blue">
            <FileText class="w-4.5 h-4.5" />
          </div>
          <div>
            <h1 class="text-sm font-semibold text-black leading-tight truncate max-w-md">{{ document.name }}</h1>
            <p class="text-[10px] text-black/35 mt-0.5">
              <span v-if="document.type === 'docx'" class="mr-1.5 font-bold text-brand-blue uppercase text-[9px] tracking-wide">DOCX · Read-only</span>
              {{ (document.size / 1024 / 1024).toFixed(2) }} MB
            </p>
          </div>
        </div>
      </div>
      
      <div class="flex items-center gap-2" v-if="document">
        <button @click="printFile" :disabled="!pdfBlobUrl"
          class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg border border-black/8 hover:bg-black/2 text-xs font-semibold text-black/60 hover:text-black transition disabled:opacity-50 disabled:cursor-not-allowed">
          <Printer class="w-3.5 h-3.5" />
          Print
        </button>
        <button @click="downloadFile" :disabled="!pdfBlobUrl"
          class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg border border-black/8 hover:bg-black/2 text-xs font-semibold text-black/60 hover:text-black transition disabled:opacity-50 disabled:cursor-not-allowed">
          <Download class="w-3.5 h-3.5" />
          Download
        </button>
        <a :href="pdfBlobUrl" target="_blank" v-if="pdfBlobUrl && document.type === 'pdf'"
          class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-brand-blue hover:bg-brand-navy text-xs font-semibold text-white transition shadow-sm">
          <ExternalLink class="w-3.5 h-3.5" />
          Open in New Tab
        </a>
      </div>
    </header>

    <!-- ── Preview Mode Banner ── -->
    <div v-if="fromCreate" class="bg-brand-blue/10 border-b border-brand-blue/20 px-6 py-2.5 flex items-center justify-between shrink-0 shadow-sm z-20">
      <div class="flex items-center gap-2">
        <div class="w-5 h-5 rounded-full bg-brand-blue flex items-center justify-center text-white">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
        </div>
        <span class="text-xs font-medium text-[#1A4F82]">Previewing document during contract creation.</span>
      </div>
      <button @click="router.push(backPath)" class="flex items-center gap-1.5 text-xs font-bold text-brand-blue hover:text-[#1A4F82] transition-colors">
        <ArrowLeft class="w-3.5 h-3.5" />
        Return to form
      </button>
    </div>

    <!-- ── PDF Toolbar ── -->
    <div v-if="document && document.type === 'pdf'" class="h-12 border-b border-black/8 bg-white px-6 flex items-center justify-between shrink-0 shadow-sm z-10">
      <div class="flex items-center gap-3">
        <!-- Sidebar Toggle Button -->
        <button @click="showSidebar = !showSidebar" 
          class="flex items-center justify-center w-8 h-8 rounded-lg border border-black/8 hover:bg-black/2 transition text-black/60 hover:text-black"
          :class="showSidebar ? 'bg-blue-50 border-blue-200 text-brand-blue' : ''"
          title="Toggle sidebar">
          <Menu class="w-4 h-4" />
        </button>
        <div class="h-4 w-px bg-black/10"></div>
        
        <!-- Page Navigation -->
        <div class="flex items-center gap-1.5">
          <button @click="prevPage" :disabled="currentPage <= 1"
            class="flex items-center justify-center w-7 h-7 rounded hover:bg-black/5 text-black/60 hover:text-black transition disabled:opacity-30 disabled:cursor-not-allowed">
            <ChevronLeft class="w-4 h-4" />
          </button>
          
          <div class="flex items-center gap-1">
            <input type="text" :value="currentPage" @change="handlePageInput"
              class="w-10 h-7 text-center text-xs font-semibold border border-black/10 rounded focus:border-brand-blue focus:outline-none" />
            <span class="text-xs text-black/45 font-medium">/ {{ pageCount }}</span>
          </div>
          
          <button @click="nextPage" :disabled="currentPage >= pageCount"
            class="flex items-center justify-center w-7 h-7 rounded hover:bg-black/5 text-black/60 hover:text-black transition disabled:opacity-30 disabled:cursor-not-allowed">
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>
      </div>
      
      <!-- PDF Zoom Controls -->
      <div class="flex items-center gap-2">
        <button @click="zoomOut" :disabled="pdfWidth <= 400"
          class="flex items-center justify-center w-7 h-7 rounded hover:bg-black/5 text-black/60 hover:text-black transition disabled:opacity-30 disabled:cursor-not-allowed"
          title="Zoom out">
          <ZoomOut class="w-4 h-4" />
        </button>
        <span class="text-xs text-black/55 font-semibold select-none w-12 text-center">{{ Math.round((pdfWidth / 800) * 100) }}%</span>
        <button @click="zoomIn" :disabled="pdfWidth >= 1600"
          class="flex items-center justify-center w-7 h-7 rounded hover:bg-black/5 text-black/60 hover:text-black transition disabled:opacity-30 disabled:cursor-not-allowed"
          title="Zoom in">
          <ZoomIn class="w-4 h-4" />
        </button>
        <button @click="resetZoom"
          class="flex items-center justify-center w-7 h-7 rounded hover:bg-black/5 text-black/60 hover:text-black transition"
          title="Reset zoom">
          <RotateCcw class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <!-- ── DOCX Toolbar ── -->
    <div v-else-if="document && document.type === 'docx'" class="h-12 border-b border-black/8 bg-white px-6 flex items-center justify-between shrink-0 shadow-sm z-10">
      <!-- Read-only badge -->
      <div class="flex items-center gap-2">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-blue-50 border border-blue-100 text-[11px] font-semibold text-brand-blue select-none">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
          </svg>
          View Only
        </span>
      </div>

      <!-- DOCX Zoom Controls -->
      <div class="flex items-center gap-2">
        <button @click="docxZoomOut" :disabled="docxFontSize <= 10"
          class="flex items-center justify-center w-7 h-7 rounded hover:bg-black/5 text-black/60 hover:text-black transition disabled:opacity-30 disabled:cursor-not-allowed"
          title="Zoom out">
          <ZoomOut class="w-4 h-4" />
        </button>
        <span class="text-xs text-black/55 font-semibold select-none w-12 text-center">{{ docxZoomLabel }}</span>
        <button @click="docxZoomIn" :disabled="docxFontSize >= 24"
          class="flex items-center justify-center w-7 h-7 rounded hover:bg-black/5 text-black/60 hover:text-black transition disabled:opacity-30 disabled:cursor-not-allowed"
          title="Zoom in">
          <ZoomIn class="w-4 h-4" />
        </button>
        <button @click="docxResetZoom"
          class="flex items-center justify-center w-7 h-7 rounded hover:bg-black/5 text-black/60 hover:text-black transition"
          title="Reset zoom">
          <RotateCcw class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <!-- Main Workspace -->
    <div class="flex-grow flex overflow-hidden bg-slate-100">
      <!-- Sidebar (PDF only) -->
      <aside v-if="showSidebar && document && document.type === 'pdf'" 
        class="w-60 border-r border-black/8 bg-white flex flex-col shrink-0 overflow-y-auto shadow-inner transition-all duration-300">
        <div class="p-3 border-b border-black/5 bg-slate-50 flex items-center justify-between">
          <span class="text-[10px] font-bold text-black/40 uppercase tracking-wider">Page Outline</span>
          <span class="text-[10px] font-semibold text-black/50 bg-black/5 px-1.5 py-0.5 rounded-full">{{ pageCount }} pages</span>
        </div>
        <div class="p-3 space-y-1">
          <div v-for="page in pageCount" :key="page"
            @click="currentPage = page"
            class="flex items-center gap-3 p-2 rounded-lg cursor-pointer transition select-none"
            :class="currentPage === page ? 'bg-blue-50 border border-blue-100 text-brand-blue' : 'border border-transparent hover:bg-black/[0.02] text-black/60 hover:text-black'">
            <div class="w-7 h-9 rounded bg-white border shadow-sm flex items-center justify-center text-xs font-bold"
              :class="currentPage === page ? 'border-blue-300 text-brand-blue' : 'border-black/10 text-black/40'">
              {{ page }}
            </div>
            <div class="flex flex-col">
              <span class="text-xs font-medium">Page {{ page }}</span>
            </div>
          </div>
        </div>
      </aside>

      <!-- Document Display Area -->
      <main class="flex-grow overflow-y-auto p-8 flex justify-center items-start relative bg-slate-100">

        <!-- Global loading -->
        <div v-if="loading" class="absolute inset-0 flex flex-col items-center justify-center gap-3">
          <Loader2 class="w-8 h-8 text-brand-blue animate-spin" />
          <span class="text-xs font-medium text-black/45">Loading document viewer...</span>
        </div>

        <!-- Document not found -->
        <div v-else-if="!document" class="absolute inset-0 flex flex-col items-center justify-center gap-2">
          <FileText class="w-12 h-12 text-black/20" />
          <p class="text-sm font-semibold text-black/55">Document not found</p>
          <p class="text-xs text-black/35">The requested document is not attached to this contract.</p>
        </div>

        <!-- ── PDF viewer ── -->
        <div v-else-if="document.type === 'pdf'" class="w-full flex justify-center py-4">
          <!-- PDF loading -->
          <div v-if="!pdfBlobUrl" class="absolute inset-0 flex flex-col items-center justify-center gap-3">
            <Loader2 class="w-8 h-8 text-brand-blue animate-spin" />
            <span class="text-xs font-medium text-black/45">Loading document viewer...</span>
          </div>

          <!-- White wrapper card that fits the PDF perfectly -->
          <div v-else class="shadow-md border border-black/8 bg-white p-6 rounded-xl flex flex-col items-center transition-all duration-300"
            :style="{ width: `${pdfWidth + 48}px` }">
            <VuePdfEmbed
              :source="pdfBlobUrl"
              :page="currentPage"
              :width="pdfWidth"
              @loaded="handlePdfLoaded"
              class="w-full select-none"
            />
          </div>
        </div>

        <!-- ── DOCX viewer ── -->
        <div v-else-if="document.type === 'docx'" class="w-full flex justify-center py-4">
          <!-- DOCX loading (mammoth converting) -->
          <div v-if="docxLoading || loadingFile" class="absolute inset-0 flex flex-col items-center justify-center gap-3">
            <Loader2 class="w-8 h-8 text-brand-blue animate-spin" />
            <span class="text-xs font-medium text-black/45">Rendering document...</span>
          </div>

          <!-- Rendered content card — mirrors PDF white wrapper -->
          <div v-else
            class="shadow-md border border-black/8 bg-white rounded-xl w-full max-w-[860px] docx-content select-none"
            :style="{ fontSize: `${docxFontSize}px` }">
            <div v-html="docxHtml" class="docx-body" />
          </div>
        </div>

      </main>
    </div>
  </div>
</template>

<style scoped>
/* ── PDF canvas fix ── */
.vue-pdf-embed :deep(canvas) {
  width: 100% !important;
  height: auto !important;
}

/* ── DOCX prose styles ── */
.docx-content {
  color: #1a1a1a;
  line-height: 1.75;
  font-family: 'Poppins', Georgia, serif;
}

.docx-body {
  padding: 3rem 4rem;
}

/* Headings */
.docx-content :deep(h1) {
  font-size: 1.75em;
  font-weight: 700;
  color: #111;
  margin-top: 0;
  margin-bottom: 0.6em;
  line-height: 1.2;
  border-bottom: 2px solid #e5e7eb;
  padding-bottom: 0.4em;
}

.docx-content :deep(h2) {
  font-size: 1.35em;
  font-weight: 700;
  color: #1a1a1a;
  margin-top: 1.8em;
  margin-bottom: 0.5em;
  line-height: 1.3;
}

.docx-content :deep(h3) {
  font-size: 1.1em;
  font-weight: 600;
  color: var(--brand-navy);
  margin-top: 1.4em;
  margin-bottom: 0.4em;
}

.docx-content :deep(h4),
.docx-content :deep(h5),
.docx-content :deep(h6) {
  font-size: 1em;
  font-weight: 600;
  color: #374151;
  margin-top: 1.2em;
  margin-bottom: 0.35em;
}

/* Paragraphs */
.docx-content :deep(p) {
  margin-top: 0;
  margin-bottom: 0.9em;
}

/* Bold & italic */
.docx-content :deep(strong),
.docx-content :deep(b) {
  font-weight: 700;
  color: #111;
}

.docx-content :deep(em),
.docx-content :deep(i) {
  font-style: italic;
}

/* Underline */
.docx-content :deep(u) {
  text-decoration: underline;
  text-underline-offset: 2px;
}

/* Lists */
.docx-content :deep(ul) {
  list-style-type: disc;
  padding-left: 1.5em;
  margin-bottom: 0.9em;
}

.docx-content :deep(ol) {
  list-style-type: decimal;
  padding-left: 1.5em;
  margin-bottom: 0.9em;
}

.docx-content :deep(li) {
  margin-bottom: 0.25em;
}

/* Tables */
.docx-content :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1.2em;
  font-size: 0.92em;
}

.docx-content :deep(th) {
  background-color: rgba(0, 0, 0, 0.018);
  border-bottom: 2px solid rgba(0, 0, 0, 0.08);
  padding: 0.6em 0.9em;
  text-align: left;
  font-weight: 700;
  font-size: 0.82em;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: rgba(0, 0, 0, 0.5);
}

.docx-content :deep(td) {
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
  padding: 0.55em 0.9em;
  vertical-align: top;
  color: #374151;
}

.docx-content :deep(tr:last-child td) {
  border-bottom: none;
}

.docx-content :deep(tr:hover td) {
  background-color: rgba(0, 0, 0, 0.012);
}

/* Blockquote */
.docx-content :deep(blockquote) {
  border-left: 3px solid var(--brand-blue);
  padding: 0.5em 1em;
  margin: 1em 0;
  color: rgba(0, 0, 0, 0.6);
  background: color-mix(in srgb, var(--brand-blue) 4%, transparent);
  border-radius: 0 6px 6px 0;
}

/* Horizontal rule */
.docx-content :deep(hr) {
  border: none;
  border-top: 1px solid rgba(0, 0, 0, 0.08);
  margin: 1.5em 0;
}

/* Images — display but not interactive */
.docx-content :deep(img) {
  max-width: 100%;
  border-radius: 6px;
  margin: 0.5em 0;
  pointer-events: none;
}

/* Links — visible but not clickable (read-only) */
.docx-content :deep(a) {
  color: var(--brand-blue);
  text-decoration: underline;
  text-underline-offset: 2px;
  pointer-events: none;
  cursor: default;
}

/* Prevent any form inputs from being interactive */
.docx-content :deep(input),
.docx-content :deep(textarea),
.docx-content :deep(select),
.docx-content :deep(button) {
  pointer-events: none;
}
</style>
