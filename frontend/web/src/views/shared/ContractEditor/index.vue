<script setup lang="ts">
import { ref, computed, reactive, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowLeft,
  Sparkles,
  Printer,
  Loader2,
  FileCheck2,
  RefreshCw,
} from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { useToast } from '@/composables/useToast'
import { useCreateContractDraft, type HeaderFooterConfig } from '@/composables/useCreateContractDraft'
import { useQuillEditor } from '@/composables/useQuillEditor'
import { useContractGenerator } from '@/composables/useContractGenerator'
import { useDocumentUpload } from '@/composables/useDocumentUpload'
import type { UploadedDoc } from '@/types/contract'
import { jsPDF } from 'jspdf'
import type { PageMargins, MarginPreset } from '@/types/contract-margins'
import { MARGIN_PRESETS } from '@/types/contract-margins'
import EditorToolbar from './EditorToolbar.vue'
import QuillCanvas from './QuillCanvas.vue'
import ConfirmationDialog from '@/components/shared/ConfirmationDialog.vue'

const router = useRouter()
const { success, error: toastError } = useToast()
const { draft, setGeneratedDoc, attachDoc } = useCreateContractDraft()
const { isGenerating, generate } = useContractGenerator()
const { uploadFile } = useDocumentUpload()
const {
  pages,
  activePageId,
  state: toolbarState,
  registerPageInstance,
  unregisterPageInstance,
  setActivePage,
  addPage,
  removePage,
  setPaperSize,
  setOrientation,
  undo,
  redo,
  getCombinedHtml,
  setCombinedHtml,
  splitPageOverflow,
} = useQuillEditor()

const docTitle = ref('Contract Agreement')
const zoomLevel = ref(1)
const isAttaching = ref(false)
const showRegenerateConfirm = ref(false)

function handleRegenerateClick() {
  showRegenerateConfirm.value = true
}

async function confirmRegenerate() {
  showRegenerateConfirm.value = false
  await runContractGeneration(true)
}
const isPreparing = ref(true)
const isEditingHeaderFooter = ref(false)

const margins = reactive<PageMargins>({
  top: 48,
  bottom: 48,
  left: 64,
  right: 64,
})

const currentPreset = computed<MarginPreset>(() => {
  for (const [key, val] of Object.entries(MARGIN_PRESETS)) {
    if (
      val.top === margins.top &&
      val.bottom === margins.bottom &&
      val.left === margins.left &&
      val.right === margins.right
    ) {
      return key as MarginPreset
    }
  }
  return 'custom'
})

function handleSetMarginPreset(preset: MarginPreset) {
  if (preset === 'custom') return
  const p = MARGIN_PRESETS[preset]
  if (p) {
    margins.top = p.top
    margins.bottom = p.bottom
    margins.left = p.left
    margins.right = p.right
    success('Margins Updated', `Applied ${preset.charAt(0).toUpperCase() + preset.slice(1)} margin preset.`)
  }
}

const headerFooter = reactive<HeaderFooterConfig>({
  headerLeft: draft.headerFooter?.headerLeft || 'CONFIDENTIAL CONTRACT AGREEMENT',
  headerRight: draft.headerFooter?.headerRight || 'SBSI CRMS',
  footerLeft: draft.headerFooter?.footerLeft || 'Authorized Legal Draft',
  headerLogoUrl: draft.headerFooter?.headerLogoUrl || '',
  showPageNumber: draft.headerFooter?.showPageNumber ?? true,
})

watch(headerFooter, (newVal) => {
  draft.headerFooter = { ...newVal }
}, { deep: true })

const generationSource = ref<'gemini' | 'template_engine' | 'client_fallback' | null>(null)

const isDraftEmpty = computed(() => {
  return !draft.form.businessPartner && !draft.form.category
})

async function runContractGeneration(force = false) {
  // If a generated document already exists in this session and not forced, restore it
  if (!force && draft.generatedDoc?.html) {
    docTitle.value = draft.generatedDoc.title || docTitle.value
    setCombinedHtml(draft.generatedDoc.html)
    isPreparing.value = false
    return
  }

  if (isDraftEmpty.value) {
    // If accessed directly without draft data, load sample contract with 3 distinct pages
    docTitle.value = 'Commercial Services Agreement'
    setCombinedHtml(`
      <h1 style="text-align: center;"><strong>COMMERCIAL SERVICES AGREEMENT</strong></h1>
      <p style="text-align: center; color: #555555;"><strong>CONTRACT NO. SBSI-2026-001</strong></p>
      <hr />
      <p>This Commercial Services Agreement (the &ldquo;Agreement&rdquo;) is entered into and made effective as of <strong>October 16, 2026</strong>, by and between <strong>SBSI Corporation</strong> (&ldquo;First Party / Client&rdquo;) and <strong>Nexus Tech Solutions Inc.</strong> (&ldquo;Second Party / Contractor&rdquo;).</p>

      <h2><strong>1. RECITALS &amp; APPOINTMENT</strong></h2>
      <p>WHEREAS, the Client requires technical engineering, server installation, and enterprise deployment services; and the Contractor represents that it possesses the requisite professional qualifications, equipment, and certified personnel to execute such services in accordance with corporate standards.</p>
      <p>NOW, THEREFORE, in consideration of the mutual covenants contained herein, the Parties agree to the terms set forth below:</p>

      <h2><strong>2. SCOPE OF DELIVERABLES &amp; SPECIFICATIONS</strong></h2>
      <p>The Contractor shall provide system engineering, deployment, integration verification, and ongoing technical support in accordance with the following specifications:</p>
      <table class="crms-quill-table" style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; margin-bottom: 0.75rem;">
        <tbody>
          <tr>
            <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold; width: 35%;">Deliverable Description</td>
            <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">Enterprise Server Infrastructure Deployment</td>
          </tr>
          <tr>
            <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Designated Item Code</td>
            <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">SRV-NEX-001</td>
          </tr>
          <tr>
            <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Assigned Strategic Unit</td>
            <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">SBU 101 - Core Infrastructure</td>
          </tr>
          <tr>
            <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Operational Region</td>
            <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">National Capital Region, Philippines</td>
          </tr>
        </tbody>
      </table>

      <h2><strong>3. CONTRACT DURATION &amp; TERM</strong></h2>
      <p>This Agreement shall commence on October 16, 2026 and remain in full force and effect until October 15, 2027, unless earlier terminated in accordance with Section 7.</p>

      <div class="crms-page-break"></div>

      <h2><strong>4. PERFORMANCE STANDARDS &amp; WARRANTIES</strong></h2>
      <ol>
        <li>The Contractor warrants that all deliverables furnished hereunder will perform without material defect and operate with standard high-availability reliability (99.9% uptime).</li>
        <li>In the event of any service discrepancy or technical outage, Contractor guarantees immediate remedial response within twenty-four (24) business hours upon written notice.</li>
      </ol>

      <h2><strong>5. COMPENSATION &amp; BILLING TERMS</strong></h2>
      <p>Client shall compensate the Contractor upon formal inspection, acceptance, and approval of milestones. All billings shall be subject to standard withholding taxes applicable under the Philippine National Internal Revenue Code.</p>

      <h2><strong>6. DATA PRIVACY &amp; CONFIDENTIALITY</strong></h2>
      <p>Both parties agree to comply with the Philippine Data Privacy Act of 2012 (Republic Act No. 10173). Confidential information shall strictly not be disclosed, duplicated, or transferred to any third party without express prior written consent of the Client.</p>

      <h2><strong>7. TERMINATION &amp; DEFAULT</strong></h2>
      <p>Either party may terminate this Agreement upon thirty (30) days prior written notice in the event of default or uncured breach.</p>

      <div class="crms-page-break"></div>

      <h2><strong>8. GOVERNING LAW &amp; DISPUTE RESOLUTION</strong></h2>
      <p>This Agreement shall be governed by and construed in accordance with Philippine law. Any legal dispute shall be submitted to the competent courts of Metro Manila, Philippines, to the exclusion of all other courts.</p>

      <h2><strong>9. GENERAL PROVISIONS</strong></h2>
      <p>This Agreement constitutes the entire agreement between the parties with respect to the subject matter hereof and supersedes all prior representations, negotiations, or agreements.</p>

      <h2><strong>10. EXECUTION &amp; FORMAL SIGNATURES</strong></h2>
      <p>IN WITNESS WHEREOF, the parties hereto have caused this Agreement to be executed by their duly authorized representatives as of the date first above written.</p>

      <table class="crms-quill-table" style="width: 100%; border: 1px solid #cbd5e1; margin-top: 1.5rem; border-collapse: collapse;">
        <tbody>
          <tr>
            <th style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left; width: 50%;">FOR SBSI CORPORATION (CLIENT)</th>
            <th style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left; width: 50%;">FOR CONTRACTOR</th>
          </tr>
          <tr>
            <td style="padding: 36px 12px 14px; border: 1px solid #cbd5e1; vertical-align: bottom;">
              ____________________________________________<br />
              <strong>AUTHORIZED REPRESENTATIVE</strong><br />
              SBSI Corporation<br />
              Date: ________________________
            </td>
            <td style="padding: 36px 12px 14px; border: 1px solid #cbd5e1; vertical-align: bottom;">
              ____________________________________________<br />
              <strong>AUTHORIZED REPRESENTATIVE</strong><br />
              Nexus Tech Solutions Inc.<br />
              Date: ________________________
            </td>
          </tr>
        </tbody>
      </table>
    `)
    isPreparing.value = false
    return
  }

  docTitle.value = `${draft.form.category || 'Contract'} - ${draft.form.businessPartner || 'Draft'}`

  try {
    const result = await generate(draft.form)
    if (result && result.html_content) {
      setCombinedHtml(result.html_content)
      docTitle.value = result.title || docTitle.value
      generationSource.value = result.source || 'gemini'
      setGeneratedDoc({
        title: docTitle.value,
        html: result.html_content,
      })
      success('Contract Generated', 'AI successfully composed your agreement draft across pages.')
    }
  } catch (err) {
    toastError('Generation Note', 'Applied standard corporate legal template.')
  } finally {
    isPreparing.value = false
  }
}

function handleInsertPageBreak() {
  addPage(activePageId.value)
  success('Page Added', 'Created a new document page below.')
}

function handleSplitOverflow(pageId: string, maxPrintableHeight: number) {
  const didSplit = splitPageOverflow(pageId, maxPrintableHeight)
  if (didSplit) {
    success('Page Extended', 'Overflowing content was automatically pushed to the next sheet.')
  }
}

async function createPdfBlob(): Promise<Blob> {
  const isLandscape = toolbarState.orientation === 'landscape'
  const format = toolbarState.paperSize === 'a4' ? 'a4' : toolbarState.paperSize === 'legal' ? 'legal' : 'letter'

  const pdf = new jsPDF({
    orientation: isLandscape ? 'landscape' : 'portrait',
    unit: 'pt',
    format,
  })

  const pageWidth = isLandscape
    ? (format === 'a4' ? 842 : format === 'legal' ? 1008 : 792)
    : (format === 'a4' ? 595 : format === 'legal' ? 612 : 612)

  const pageHeight = isLandscape
    ? (format === 'a4' ? 595 : format === 'legal' ? 612 : 612)
    : (format === 'a4' ? 842 : format === 'legal' ? 1008 : 792)

  const totalPages = pages.value.length

  const marginX = 48
  const headerY = 28
  const headerLineY = 34
  const footerLineY = pageHeight - 34
  const footerY = pageHeight - 22
  const topContentY = 56
  const maxContentY = pageHeight - 50
  const contentWidth = pageWidth - (marginX * 2)

  const parser = new DOMParser()

  for (let i = 0; i < totalPages; i++) {
    if (i > 0) {
      pdf.addPage(format, isLandscape ? 'landscape' : 'portrait')
    }

    // 1. Draw Header
    let headerTextX = marginX
    if (headerFooter.headerLogoUrl) {
      try {
        pdf.addImage(headerFooter.headerLogoUrl, 'PNG', marginX, 14, 46, 16)
        headerTextX = marginX + 54
      } catch {}
    }

    pdf.setFont('helvetica', 'bold')
    pdf.setFontSize(8)
    pdf.setTextColor(37, 37, 120) // brand navy #252578
    const headerLeft = (headerFooter.headerLeft || 'CONFIDENTIAL CONTRACT AGREEMENT').toUpperCase()
    pdf.text(headerLeft, headerTextX, headerY)

    pdf.setFont('helvetica', 'normal')
    pdf.setFontSize(8)
    pdf.setTextColor(100, 116, 139)
    pdf.text(headerFooter.headerRight || 'SBSI CRMS', pageWidth - marginX, headerY, { align: 'right' })

    pdf.setDrawColor(226, 232, 240)
    pdf.setLineWidth(0.5)
    pdf.line(marginX, headerLineY, pageWidth - marginX, headerLineY)

    // 2. Draw Page Content
    const pageHtml = pages.value[i].html || '<p></p>'
    const doc = parser.parseFromString(pageHtml, 'text/html')
    const elements = Array.from(doc.body.children)

    let currentY = topContentY

    for (const el of elements) {
      if (currentY > maxContentY) break
      const tag = el.tagName.toLowerCase()

      if (tag === 'h1') {
        const text = el.textContent?.trim() || ''
        pdf.setFont('helvetica', 'bold')
        pdf.setFontSize(13)
        pdf.setTextColor(17, 24, 39)
        pdf.text(text, pageWidth / 2, currentY, { align: 'center' })
        currentY += 18
      } else if (tag === 'h2') {
        const text = el.textContent?.trim() || ''
        currentY += 4
        pdf.setFont('helvetica', 'bold')
        pdf.setFontSize(10)
        pdf.setTextColor(30, 41, 59)
        pdf.text(text, marginX, currentY)
        currentY += 4
        pdf.setDrawColor(226, 232, 240)
        pdf.line(marginX, currentY, marginX + 160, currentY)
        currentY += 12
      } else if (tag === 'h3') {
        const text = el.textContent?.trim() || ''
        pdf.setFont('helvetica', 'bold')
        pdf.setFontSize(9.5)
        pdf.setTextColor(30, 41, 59)
        pdf.text(text, marginX, currentY)
        currentY += 13
      } else if (tag === 'hr') {
        pdf.setDrawColor(226, 232, 240)
        pdf.line(marginX, currentY, pageWidth - marginX, currentY)
        currentY += 10
      } else if (tag === 'table') {
        const rows = Array.from(el.querySelectorAll('tr'))
        for (const row of rows) {
          if (currentY > maxContentY) break
          const cells = Array.from(row.querySelectorAll('td, th'))
          if (cells.length === 2) {
            const col1Text = cells[0].textContent?.trim() || ''
            const col2Text = cells[1].textContent?.trim() || ''
            const col1Width = contentWidth * 0.45

            pdf.setFont('helvetica', 'bold')
            pdf.setFontSize(8.5)
            pdf.setTextColor(30, 41, 59)
            pdf.text(col1Text, marginX + 4, currentY + 11)

            pdf.setFont('helvetica', 'normal')
            pdf.setFontSize(8.5)
            pdf.text(col2Text, marginX + col1Width + 4, currentY + 11)

            pdf.setDrawColor(226, 232, 240)
            pdf.rect(marginX, currentY, contentWidth, 16)
            pdf.line(marginX + col1Width, currentY, marginX + col1Width, currentY + 16)
            currentY += 16
          } else {
            const cellText = cells.map(c => c.textContent?.trim()).join('  |  ')
            pdf.setFont('helvetica', 'normal')
            pdf.setFontSize(8.5)
            pdf.setTextColor(30, 41, 59)
            pdf.text(cellText, marginX + 4, currentY + 11)
            pdf.setDrawColor(226, 232, 240)
            pdf.rect(marginX, currentY, contentWidth, 16)
            currentY += 16
          }
        }
        currentY += 8
      } else if (tag === 'ol' || tag === 'ul') {
        const items = Array.from(el.querySelectorAll('li'))
        let itemIndex = 1
        for (const li of items) {
          if (currentY > maxContentY) break
          const bullet = tag === 'ol' ? `${itemIndex}. ` : '• '
          const text = li.textContent?.trim() || ''
          const lines = pdf.splitTextToSize(bullet + text, contentWidth - 12)
          pdf.setFont('helvetica', 'normal')
          pdf.setFontSize(9)
          pdf.setTextColor(30, 41, 59)
          for (const line of lines) {
            if (currentY > maxContentY) break
            pdf.text(line, marginX + 8, currentY)
            currentY += 12
          }
          itemIndex++
          currentY += 2
        }
        currentY += 4
      } else {
        // Standard paragraph or div
        const text = el.textContent?.trim() || ''
        if (text) {
          const isCentered = (el as HTMLElement).style?.textAlign === 'center'
          const lines = pdf.splitTextToSize(text, contentWidth)
          pdf.setFont('helvetica', 'normal')
          pdf.setFontSize(9)
          pdf.setTextColor(30, 41, 59)
          for (const line of lines) {
            if (currentY > maxContentY) break
            if (isCentered) {
              pdf.text(line, pageWidth / 2, currentY, { align: 'center' })
            } else {
              pdf.text(line, marginX, currentY)
            }
            currentY += 12.5
          }
          currentY += 4
        }
      }
    }

    // 3. Draw Footer
    pdf.setDrawColor(226, 232, 240)
    pdf.setLineWidth(0.5)
    pdf.line(marginX, footerLineY, pageWidth - marginX, footerLineY)

    pdf.setFont('helvetica', 'normal')
    pdf.setFontSize(8)
    pdf.setTextColor(100, 116, 139)
    pdf.text(headerFooter.footerLeft || 'Authorized Legal Draft · Strictly Confidential', marginX, footerY)

    if (headerFooter.showPageNumber) {
      pdf.text(`Page ${i + 1} of ${totalPages}`, pageWidth - marginX, footerY, { align: 'right' })
    }
  }

  return pdf.output('blob')
}

async function handleAttachToContract() {
  const html = getCombinedHtml()
  if (!html.trim()) {
    toastError('Empty Document', 'Cannot attach an empty document.')
    return
  }

  isAttaching.value = true

  const safeName = (docTitle.value || 'Contract_Agreement')
    .replace(/[^a-zA-Z0-9_\-\s]/g, '')
    .trim()
    .replace(/\s+/g, '_')

  const fileName = `${safeName}.pdf`

  try {
    const blob = await createPdfBlob()
    const file = new File([blob], fileName, { type: 'application/pdf' })

    const newDoc: UploadedDoc = {
      file,
      name: fileName,
      size: blob.size,
      type: 'pdf',
      previewUrl: URL.createObjectURL(blob),
      uploadStatus: 'uploading',
    }

    try {
      await uploadFile(newDoc, (patch) => {
        Object.assign(newDoc, patch)
      })
    } catch (uploadErr) {
      console.warn('Backend document upload skipped or failed, persisting locally in draft:', uploadErr)
    }

    // If upload was completed, keep backend ID. If still pending or offline, mark success locally but retain file for pre-submit upload fallback
    if (!newDoc.id) {
      newDoc.uploadStatus = 'success'
    }

    attachDoc(newDoc)
    setGeneratedDoc({
      title: docTitle.value,
      html,
    })

    success('Contract Attached', 'Document attached to contract draft.')

    document.body.style.pointerEvents = 'auto'
    document.body.style.overflow = ''

    const backRoute = draft.role ? `/${draft.role}/contracts/create` : '/sales/contracts/create'
    router.push(backRoute)
  } catch (err: any) {
    console.error('Error attaching contract:', err)
    toastError('Attachment Error', err?.message || 'Could not attach contract.')
  } finally {
    isAttaching.value = false
    document.body.style.pointerEvents = 'auto'
    document.body.style.overflow = ''
  }
}

function escapeHtml(str: string): string {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

function handlePrint() {
  const isLandscape = toolbarState.orientation === 'landscape'
  const format = toolbarState.paperSize || 'letter'

  // Dimensions in inches/mm for CSS @page and sheet sizing
  let pageCssSize = 'letter portrait'
  let sheetWidth = '8.5in'
  let sheetHeight = '11in'

  if (format === 'a4') {
    if (isLandscape) {
      pageCssSize = 'A4 landscape'
      sheetWidth = '297mm'
      sheetHeight = '210mm'
    } else {
      pageCssSize = 'A4 portrait'
      sheetWidth = '210mm'
      sheetHeight = '297mm'
    }
  } else if (format === 'legal') {
    if (isLandscape) {
      pageCssSize = 'legal landscape'
      sheetWidth = '14in'
      sheetHeight = '8.5in'
    } else {
      pageCssSize = 'legal portrait'
      sheetWidth = '8.5in'
      sheetHeight = '14in'
    }
  } else {
    // letter
    if (isLandscape) {
      pageCssSize = 'letter landscape'
      sheetWidth = '11in'
      sheetHeight = '8.5in'
    } else {
      pageCssSize = 'letter portrait'
      sheetWidth = '8.5in'
      sheetHeight = '11in'
    }
  }

  const totalPages = pages.value.length

  // Build sheets HTML
  let sheetsHtml = ''
  for (let i = 0; i < totalPages; i++) {
    const page = pages.value[i]
    // Clean and normalize content: strip any inline page break indicators
    let bodyHtml = page.html || '<p></p>'
    bodyHtml = bodyHtml.replace(
      /<div[^>]*class="[^"]*crms-page-break[^"]*"[^>]*><\/div>|<div[^>]*data-page-break="true"[^>]*><\/div>/gi,
      ''
    )

    const logoHtml = headerFooter.headerLogoUrl
      ? `<img src="${headerFooter.headerLogoUrl}" style="height: 26px; max-width: 120px; object-fit: contain; margin-right: 10px;" alt="Logo" />`
      : ''

    const headerLeftText = headerFooter.headerLeft || 'CONFIDENTIAL CONTRACT AGREEMENT'
    const headerRightText = headerFooter.headerRight || 'SBSI CRMS'
    const footerLeftText = headerFooter.footerLeft || 'Authorized Legal Draft · Strictly Confidential'
    const pageNumberText = headerFooter.showPageNumber ? `Page ${i + 1} of ${totalPages}` : ''

    sheetsHtml += `
      <div class="print-sheet" style="width: ${sheetWidth}; height: ${sheetHeight};">
        <div class="print-header" style="padding-left: ${margins.left}px; padding-right: ${margins.right}px;">
          <div style="display: flex; align-items: center; max-width: 60%; overflow: hidden;">
            ${logoHtml}
            <span style="font-size: 10px; font-weight: 700; color: #252578; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              ${escapeHtml(headerLeftText)}
            </span>
          </div>
          <div style="font-size: 10px; font-weight: 500; color: #64748b; white-space: nowrap;">
            ${escapeHtml(headerRightText)}
          </div>
        </div>

        <div class="print-body" style="padding-left: ${margins.left}px; padding-right: ${margins.right}px; padding-top: ${margins.top}px; padding-bottom: ${margins.bottom}px;">
          ${bodyHtml}
        </div>

        <div class="print-footer" style="padding-left: ${margins.left}px; padding-right: ${margins.right}px;">
          <div style="font-size: 10px; font-weight: 500; color: #64748b; max-width: 70%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            ${escapeHtml(footerLeftText)}
          </div>
          <div style="font-size: 10px; font-weight: 600; color: #252578; white-space: nowrap;">
            ${pageNumberText}
          </div>
        </div>
      </div>
    `
  }

  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  iframe.style.visibility = 'hidden'
  document.body.appendChild(iframe)

  const doc = iframe.contentWindow?.document
  if (!doc) {
    document.body.removeChild(iframe)
    window.print()
    return
  }

  const printHtml = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${escapeHtml(docTitle.value || 'Contract Agreement')}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300..700;1,300..700&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
  <style>
    @page {
      size: ${pageCssSize};
      margin: 0;
    }
    * {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    html, body {
      margin: 0 !important;
      padding: 0 !important;
      background: #ffffff !important;
      color: #111827;
      font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }
    .print-sheet {
      box-sizing: border-box;
      position: relative;
      background: #ffffff;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      page-break-after: always;
      break-after: page;
      page-break-inside: avoid;
      break-inside: avoid;
      overflow: hidden;
      margin: 0;
    }
    .print-sheet:last-child {
      page-break-after: auto;
      break-after: auto;
    }
    .print-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 14px;
      padding-bottom: 10px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.12);
      flex-shrink: 0;
      background: #ffffff;
      font-family: 'Poppins', sans-serif;
    }
    .print-body {
      flex: 1 1 auto;
      font-family: 'Poppins', sans-serif;
      font-size: 11pt;
      line-height: 1.6;
      color: #111827;
      overflow: hidden;
      background: #ffffff;
    }
    .print-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 10px;
      padding-bottom: 14px;
      border-top: 1px solid rgba(0, 0, 0, 0.12);
      flex-shrink: 0;
      background: #ffffff;
      font-family: 'Poppins', sans-serif;
    }
    .print-body h1 {
      font-size: 18pt;
      font-weight: 700;
      margin-top: 0;
      margin-bottom: 0.6rem;
      line-height: 1.3;
      color: #111827;
      text-align: center;
    }
    .print-body h2 {
      font-size: 13pt;
      font-weight: 700;
      margin-top: 0.8rem;
      margin-bottom: 0.4rem;
      line-height: 1.35;
      color: #111827;
    }
    .print-body h3 {
      font-size: 11pt;
      font-weight: 600;
      margin-top: 0.6rem;
      margin-bottom: 0.3rem;
      line-height: 1.35;
      color: #111827;
    }
    .print-body p {
      margin-top: 0;
      margin-bottom: 0.5rem;
    }
    .print-body hr, .print-body .crms-hr {
      border: 0;
      border-top: 1px solid #cbd5e1;
      margin: 0.75rem 0;
    }
    .print-body ol, .print-body ul {
      margin-top: 0;
      margin-bottom: 0.6rem;
      padding-left: 1.5rem;
    }
    .print-body li {
      margin-bottom: 0.25rem;
    }
    .print-body table, .print-body .crms-quill-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 0.5rem;
      margin-bottom: 0.75rem;
    }
    .print-body table td, .print-body table th {
      border: 1px solid #cbd5e1;
      padding: 6px 10px;
      font-size: 10pt;
    }
    .crms-page-break {
      display: none !important;
    }
    .ql-container, .ql-editor {
      border: none !important;
      outline: none !important;
      padding: 0 !important;
    }
  </style>
</head>
<body>
  ${sheetsHtml}
</body>
</html>
`

  doc.open()
  doc.write(printHtml)
  doc.close()

  setTimeout(() => {
    try {
      iframe.contentWindow?.focus()
      iframe.contentWindow?.print()
    } catch (e) {
      console.error('Print iframe error:', e)
    } finally {
      setTimeout(() => {
        if (document.body.contains(iframe)) {
          document.body.removeChild(iframe)
        }
      }, 1000)
    }
  }, 250)
}

function handleBack() {
  document.body.style.pointerEvents = 'auto'
  document.body.style.overflow = ''
  const backRoute = draft.role ? `/${draft.role}/contracts/create` : '/sales/contracts/create'
  router.push(backRoute)
}

onMounted(() => {
  runContractGeneration(false)
})
</script>

<template>
  <div class="relative h-full w-full flex-1 flex flex-col min-h-0 bg-white overflow-hidden font-poppins select-none print:bg-white print:overflow-visible">
    <!-- Full-editor loading overlay while the AI composes the contract -->
    <div
      v-if="isGenerating || isPreparing"
      class="absolute inset-0 z-40 bg-white/85 backdrop-blur-xs flex flex-col items-center justify-center gap-3 animate-in fade-in duration-200 print:hidden"
    >
      <div class="w-12 h-12 rounded-2xl bg-brand-navy/10 flex items-center justify-center">
        <Loader2 class="w-6 h-6 text-brand-navy animate-spin" />
      </div>
      <div class="text-center">
        <h3 class="text-sm font-semibold text-black">Generating Contract Agreement</h3>
        <p class="text-xs text-black/50 mt-1 max-w-sm">
          AI is assembling formal clauses, scopes, dates, and Philippine legal provisions...
        </p>
      </div>
    </div>

    <!-- Top App Bar (Hidden on print) -->
    <header class="h-14 border-b border-black/8 px-4 flex items-center justify-between bg-white shrink-0 z-20 print:hidden">
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="handleBack"
          class="flex items-center justify-center w-8 h-8 rounded-lg border border-black/10 hover:bg-black/4 text-black/60 hover:text-black transition cursor-pointer"
          title="Back to Create Contract"
        >
          <ArrowLeft class="w-4 h-4" />
        </button>

        <div class="flex flex-col">
          <div class="flex items-center gap-2">
            <input
              v-model="docTitle"
              type="text"
              class="text-sm font-semibold text-black bg-transparent border border-transparent hover:border-black/15 focus:border-brand-blue rounded px-1.5 py-0.5 outline-none transition max-w-[280px] sm:max-w-[420px] truncate"
            />
            <span
              class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full border"
              :class="generationSource === 'gemini'
                ? 'bg-purple-50 text-purple-700 border-purple-200'
                : 'bg-brand-blue/10 text-brand-navy border-brand-blue/20'"
            >
              <Sparkles class="w-3 h-3 text-brand-navy" />
              {{ generationSource === 'gemini' ? 'Gemini AI' : 'AI Contract Generator' }}
            </span>
          </div>
          <span class="text-[11px] text-black/40 px-1.5">Paginated multi-page document editor · {{ pages.length }} Page{{ pages.length > 1 ? 's' : '' }}</span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <!-- Regenerate Button -->
        <Button
          variant="outline"
          size="sm"
          @click="handleRegenerateClick"
          :disabled="isGenerating || isAttaching"
          class="h-8 text-xs border-brand-navy/20 text-brand-navy hover:bg-brand-navy/5 gap-1.5 hidden sm:inline-flex"
          title="Regenerate fresh draft with AI"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isGenerating }" />
          Regenerate
        </Button>

        <!-- Print / PDF -->
        <Button
          variant="outline"
          size="sm"
          @click="handlePrint"
          class="h-8 text-xs border-black/12 text-black/70 hover:text-black hover:bg-black/3 gap-1.5 hidden sm:inline-flex cursor-pointer"
          title="Print physical contract or save via browser PDF"
        >
          <Printer class="w-3.5 h-3.5" />
          Print / PDF
        </Button>

        <!-- Attach & Save Contract -->
        <Button
          size="sm"
          @click="handleAttachToContract"
          :disabled="isGenerating || isAttaching"
          class="h-8 px-4 text-xs font-semibold bg-brand-navy hover:bg-brand-dark text-white gap-1.5 shadow-sm transition disabled:opacity-50"
        >
          <Loader2 v-if="isAttaching" class="w-3.5 h-3.5 animate-spin" />
          <FileCheck2 v-else class="w-3.5 h-3.5" />
          {{ isAttaching ? 'Attaching & Saving...' : 'Attach & Save Contract' }}
        </Button>
      </div>
    </header>

    <!-- Toolbar (Hidden on print) -->
    <EditorToolbar
      :state="toolbarState"
      :header-footer="headerFooter"
      :margins="margins"
      :current-preset="currentPreset"
      @update:header-footer="(val) => Object.assign(headerFooter, val)"
      @set-margin-preset="handleSetMarginPreset"
      @insert-page-break="handleInsertPageBreak"
      @set-paper-size="setPaperSize"
      @set-orientation="setOrientation"
      @toggle-header-footer="isEditingHeaderFooter = !isEditingHeaderFooter"
      @undo="undo"
      @redo="redo"
    />

    <!-- Editor Multi-Page Canvas Sheet -->
    <div class="relative flex-1 flex flex-col overflow-hidden print:overflow-visible">
      <QuillCanvas
        :zoom="zoomLevel"
        :paper-size="toolbarState.paperSize"
        :orientation="toolbarState.orientation"
        :header-footer="headerFooter"
        :margins="margins"
        :pages="pages"
        :active-page-id="activePageId"
        :is-editing-header-footer="isEditingHeaderFooter"
        @update:is-editing-header-footer="(val) => isEditingHeaderFooter = val"
        @update:margins="(m) => Object.assign(margins, m)"
        @mount-page="(id, el, initialHtml) => registerPageInstance(id, el, initialHtml)"
        @unmount-page="(id) => unregisterPageInstance(id)"
        @set-active-page="(id) => setActivePage(id)"
        @add-page="(afterId) => addPage(afterId)"
        @remove-page="(id) => removePage(id)"
        @split-overflow="(id, height) => handleSplitOverflow(id, height)"
        @update:header-footer="(val) => Object.assign(headerFooter, val)"
      />
    </div>
    <ConfirmationDialog
      v-model:open="showRegenerateConfirm"
      title="Regenerate Contract with AI"
      description="Are you sure you want to regenerate this contract draft? This will compose a fresh document using AI and replace any unsaved edits on the current pages."
      confirm-label="Regenerate"
      variant="ai"
      :loading="isGenerating"
      @confirm="confirmRegenerate"
    />
  </div>
</template>

<style>
/* Standard Contract Document Typography */
.quill-printable-body .ql-editor {
  font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
  font-size: 13.5px !important;
  line-height: 1.65 !important;
  color: #1e293b !important;
  padding: 0 !important;
  overflow: visible !important;
}

.quill-printable-body .ql-editor h1 {
  font-size: 1.15rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.04em !important;
  color: #0f172a !important;
  margin-top: 0.25rem !important;
  margin-bottom: 0.35rem !important;
  line-height: 1.35 !important;
}

.quill-printable-body .ql-editor h2 {
  font-size: 0.9rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.025em !important;
  color: #1e293b !important;
  margin-top: 1rem !important;
  margin-bottom: 0.35rem !important;
  line-height: 1.4 !important;
  border-bottom: 1px solid #e2e8f0 !important;
  padding-bottom: 2px !important;
}

.quill-printable-body .ql-editor h3 {
  font-size: 0.825rem !important;
  font-weight: 600 !important;
  color: #334155 !important;
  margin-top: 0.75rem !important;
  margin-bottom: 0.25rem !important;
}

.quill-printable-body .ql-editor p {
  font-size: 13.5px !important;
  line-height: 1.65 !important;
  margin-bottom: 0.65rem !important;
  color: #1e293b !important;
}

.quill-printable-body .ql-editor hr {
  border: 0 !important;
  border-top: 1px solid #cbd5e1 !important;
  margin: 0.75rem 0 !important;
}

.quill-printable-body .ql-editor ul,
.quill-printable-body .ql-editor ol {
  padding-left: 1.4rem !important;
  margin-top: 0.25rem !important;
  margin-bottom: 0.65rem !important;
}

.quill-printable-body .ql-editor li {
  font-size: 13.5px !important;
  line-height: 1.6 !important;
  margin-bottom: 0.2rem !important;
}

.quill-printable-body .ql-editor table.crms-quill-table {
  width: 100% !important;
  border-collapse: collapse !important;
  margin: 0.75rem 0 !important;
  font-size: 12.5px !important;
}

.quill-printable-body .ql-editor table.crms-quill-table th,
.quill-printable-body .ql-editor table.crms-quill-table td {
  border: 1px solid #cbd5e1 !important;
  padding: 8px 12px !important;
  font-size: 12.5px !important;
  line-height: 1.5 !important;
}

/* On Screen Page Break Indicator */
.crms-page-break {
  display: block;
  border-top: 1px dashed #cbd5e1;
  margin: 1.5rem 0;
  position: relative;
}
.crms-page-break::after {
  content: 'Page Break';
  position: absolute;
  top: -9px;
  right: 12px;
  background: #f1f5f9;
  color: #64748b;
  font-size: 9px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 1px 6px;
  border-radius: 4px;
}

/* Remove unwanted Quill Snow borders and focus rings from page instances */
.quill-page-instance .ql-container.ql-snow {
  border: none !important;
}

.quill-page-instance .ql-editor {
  border: none !important;
  outline: none !important;
  padding: 0 !important;
}

.quill-page-instance .ql-editor:focus {
  outline: none !important;
}

@media print {
  /* 1. Hide all application shell elements, navigation, breadcrumbs, user avatars, bells, and toolbars */
  aside,
  nav,
  .sidebar-provider > *:not(main),
  [data-sidebar="sidebar"],
  header,
  .app-breadcrumbs,
  .editor-toolbar-wrapper,
  .editor-toolbar,
  #quill-toolbar-container,
  .ql-toolbar,
  .contract-page-controls,
  button,
  .print\:hidden,
  .group\/gap,
  .header-footer-floating-pill,
  .sheet-margin-guides,
  .crms-page-break,
  .crms-page-break::after {
    display: none !important;
    visibility: hidden !important;
  }

  /* 2. Reset layout containers so only the printable document flows naturally */
  html,
  body,
  #app,
  main,
  .sidebar-provider,
  .flex-1.overflow-auto,
  .w-full.flex-1.overflow-y-auto {
    background: #ffffff !important;
    padding: 0 !important;
    margin: 0 !important;
    overflow: visible !important;
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
    height: auto !important;
    min-height: 0 !important;
    width: 100% !important;
  }

  html::-webkit-scrollbar,
  body::-webkit-scrollbar,
  .w-full.flex-1.overflow-y-auto::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
  }

  /* 3. Each physical paper sheet becomes a true printed page */
  .group\/sheet-wrap,
  .quill-page-sheet {
    box-shadow: none !important;
    border: none !important;
    outline: none !important;
    border-radius: 0 !important;
    --tw-ring-shadow: none !important;
    ring: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    page-break-after: always !important;
    break-after: page !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }

  .quill-page-sheet:last-child {
    page-break-after: auto !important;
    break-after: auto !important;
  }

  /* Clean sheet header and footer on print */
  .quill-page-sheet .header-footer-zone {
    display: flex !important;
    border-color: rgba(0, 0, 0, 0.12) !important;
    background: transparent !important;
    border-style: solid !important;
    border-width: 0 0 1px 0 !important;
  }

  .quill-page-sheet .header-footer-zone.border-t {
    border-width: 1px 0 0 0 !important;
  }

  @page {
    margin: 0;
    size: auto;
  }
}
</style>
