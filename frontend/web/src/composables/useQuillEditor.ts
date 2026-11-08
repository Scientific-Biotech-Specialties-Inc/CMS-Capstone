import { ref, reactive, nextTick } from 'vue'
import Quill from 'quill'
import 'quill/dist/quill.snow.css'

export type PaperSize = 'letter' | 'a4' | 'legal'
export type PageOrientation = 'portrait' | 'landscape'

export interface PageSheet {
  id: string
  html: string
}

export interface EditorToolbarState {
  isBold: boolean
  isItalic: boolean
  isUnderline: boolean
  isStrikethrough: boolean
  isSubscript: boolean
  isSuperscript: boolean
  canUndo: boolean
  canRedo: boolean
  fontFamily: string
  fontSize: string
  fontColor: string
  highlightColor: string
  blockType: 'paragraph' | 'h1' | 'h2' | 'h3' | 'quote' | 'bullet' | 'number'
  align: 'left' | 'center' | 'right' | 'justify'
  paperSize: PaperSize
  orientation: PageOrientation
}

// ── Register Custom Quill Extensions ──
let isRegistered = false

function registerQuillExtensions() {
  if (isRegistered) return

  try {
    // 1. Font Style Attributor
    const FontStyle = Quill.import('attributors/style/font') as any
    if (FontStyle) {
      FontStyle.whitelist = [
        'Poppins',
        'Montserrat',
        'Times New Roman',
        'Arial',
        'Courier New',
      ]
      Quill.register(FontStyle, true)
    }

    // 2. Font Size Style Attributor
    const SizeStyle = Quill.import('attributors/style/size') as any
    if (SizeStyle) {
      SizeStyle.whitelist = [
        '10pt',
        '11pt',
        '12pt',
        '14pt',
        '16pt',
        '18pt',
      ]
      Quill.register(SizeStyle, true)
    }

    // 3. Custom Page Break Blot
    const BlockEmbed = Quill.import('blots/block/embed') as any
    if (BlockEmbed) {
      class PageBreakBlot extends BlockEmbed {
        static blotName = 'page-break'
        static tagName = 'div'
        static className = 'crms-page-break'

        static create() {
          const node = super.create() as HTMLElement
          node.setAttribute('class', 'crms-page-break')
          node.setAttribute('data-page-break', 'true')
          node.setAttribute('contenteditable', 'false')
          return node
        }

        static value() {
          return true
        }
      }
      Quill.register(PageBreakBlot, true)

      // 4. Custom Horizontal Rule Blot
      class DividerBlot extends BlockEmbed {
        static blotName = 'divider'
        static tagName = 'hr'
        static className = 'crms-hr'

        static create() {
          const node = super.create() as HTMLElement
          node.setAttribute('class', 'crms-hr')
          node.setAttribute('contenteditable', 'false')
          return node
        }

        static value() {
          return true
        }
      }
      Quill.register(DividerBlot, true)
    }

    isRegistered = true
  } catch (err) {
    console.warn('Error registering Quill extensions:', err)
  }
}

export function useQuillEditor() {
  registerQuillExtensions()

  // Multi-page document state
  const pages = ref<PageSheet[]>([
    { id: 'page-1', html: '' },
  ])
  const activePageId = ref<string>('page-1')
  const pageInstances = new Map<string, Quill>()

  const state = reactive<EditorToolbarState>({
    isBold: false,
    isItalic: false,
    isUnderline: false,
    isStrikethrough: false,
    isSubscript: false,
    isSuperscript: false,
    canUndo: false,
    canRedo: false,
    fontFamily: 'Poppins',
    fontSize: '11pt',
    fontColor: '#000000',
    highlightColor: 'transparent',
    blockType: 'paragraph',
    align: 'left',
    paperSize: 'letter',
    orientation: 'portrait',
  })

  function getActiveEditor(): Quill | null {
    if (activePageId.value && pageInstances.has(activePageId.value)) {
      return pageInstances.get(activePageId.value)!
    }
    const first = pageInstances.values().next().value
    return first || null
  }

  function updateToolbarFromEditor(editor: Quill | null) {
    if (!editor) return

    let formats: Record<string, any> = {}
    try {
      const range = editor.getSelection()
      if (range && range.length >= 0) {
        formats = editor.getFormat(range)
      } else {
        formats = editor.getFormat(0, 0)
      }
    } catch {
      formats = {}
    }

    state.isBold = !!formats.bold
    state.isItalic = !!formats.italic
    state.isUnderline = !!formats.underline
    state.isStrikethrough = !!formats.strike
    state.isSubscript = formats.script === 'sub'
    state.isSuperscript = formats.script === 'super'

    const fontVal = formats.font as string | undefined
    state.fontFamily = fontVal ? fontVal.replace(/['"]/g, '') : 'Poppins'

    state.fontSize = (formats.size as string) || '11pt'
    state.fontColor = (formats.color as string) || '#000000'
    state.highlightColor = (formats.background as string) || 'transparent'

    if (formats.header === 1) state.blockType = 'h1'
    else if (formats.header === 2) state.blockType = 'h2'
    else if (formats.header === 3) state.blockType = 'h3'
    else if (formats.blockquote) state.blockType = 'quote'
    else if (formats.list === 'bullet') state.blockType = 'bullet'
    else if (formats.list === 'ordered') state.blockType = 'number'
    else state.blockType = 'paragraph'

    state.align = (formats.align as 'left' | 'center' | 'right' | 'justify') || 'left'

    try {
      const history = (editor as any).history
      if (history?.stack) {
        state.canUndo = (history.stack.undo?.length ?? 0) > 0
        state.canRedo = (history.stack.redo?.length ?? 0) > 0
      }
    } catch {
      // safe fallback
    }
  }

    let sharedToolbar: any = null

  function registerPageInstance(pageId: string, rootElement: HTMLElement, initialHtml: string, onOverflow?: () => void) {
    if (pageInstances.has(pageId)) {
      const existing = pageInstances.get(pageId)!
      // If already registered to this root element or child, reuse
      if (existing.root && (existing.root === rootElement || existing.root.parentElement === rootElement)) {
        return
      }
      existing.off('selection-change')
      existing.off('text-change')
      pageInstances.delete(pageId)
    }

    // Clean any leftover child nodes before mounting Quill
    while (rootElement.firstChild) {
      rootElement.removeChild(rootElement.firstChild)
    }

    const hasToolbarContainer = typeof document !== 'undefined' && document.getElementById('quill-toolbar-container')
    const toolbarOption = hasToolbarContainer && !sharedToolbar ? '#quill-toolbar-container' : false

    const editor = new Quill(rootElement, {
      theme: 'snow',
      modules: {
        toolbar: toolbarOption,
        history: {
          delay: 400,
          maxStack: 500,
          userOnly: true,
        },
        table: true,
        clipboard: {
          matchVisual: false,
        },
      },
      placeholder: 'Type contract clauses for this page...',
    })

    if (!sharedToolbar && hasToolbarContainer) {
      sharedToolbar = (editor as any).getModule('toolbar')
    }

    editor.format('font', 'Poppins')

    if (initialHtml) {
      editor.clipboard.dangerouslyPasteHTML(0, initialHtml, 'silent')
    }

    editor.on('selection-change', (range: any) => {
      if (range) {
        activePageId.value = pageId
        if (sharedToolbar) {
          sharedToolbar.quill = editor
          try {
            sharedToolbar.update(range)
          } catch {}
        }
        updateToolbarFromEditor(editor)
      }
    })

    editor.on('text-change', () => {
      const p = pages.value.find((item) => item.id === pageId)
      if (p) {
        p.html = typeof (editor as any).getSemanticHTML === 'function'
          ? (editor as any).getSemanticHTML()
          : editor.root.innerHTML
      }
      updateToolbarFromEditor(editor)
      onOverflow?.()
    })

    pageInstances.set(pageId, editor)

    if (activePageId.value === pageId || pageInstances.size === 1) {
      activePageId.value = pageId
      if (sharedToolbar) {
        sharedToolbar.quill = editor
      }
      setTimeout(() => {
        try {
          updateToolbarFromEditor(editor)
          if (sharedToolbar) {
            sharedToolbar.update(editor.getSelection())
          }
        } catch {
          // ignore initial DOM render race
        }
      }, 0)
    }
  }

  function unregisterPageInstance(pageId: string) {
    const editor = pageInstances.get(pageId)
    if (editor) {
      editor.off('selection-change')
      editor.off('text-change')
      pageInstances.delete(pageId)
    }
    if (activePageId.value === pageId) {
      const nextKey = pageInstances.keys().next().value
      activePageId.value = nextKey || ''
      if (activePageId.value && pageInstances.has(activePageId.value)) {
        const nextEditor = pageInstances.get(activePageId.value)!
        if (sharedToolbar) {
          sharedToolbar.quill = nextEditor
        }
        updateToolbarFromEditor(nextEditor)
      }
    }
  }

  function setActivePage(pageId: string) {
    activePageId.value = pageId
    const editor = pageInstances.get(pageId)
    if (editor) {
      if (sharedToolbar) {
        sharedToolbar.quill = editor
        try {
          sharedToolbar.update(editor.getSelection())
        } catch {}
      }
      updateToolbarFromEditor(editor)
    }
  }

  function addPage(afterPageId?: string, initialHtml: string = '<p><br></p>'): string {
    const newId = `page-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`
    const newPage: PageSheet = { id: newId, html: initialHtml }

    if (afterPageId) {
      const idx = pages.value.findIndex((p) => p.id === afterPageId)
      if (idx !== -1) {
        pages.value.splice(idx + 1, 0, newPage)
      } else {
        pages.value.push(newPage)
      }
    } else {
      pages.value.push(newPage)
    }

    activePageId.value = newId
    return newId
  }

  function removePage(pageId: string) {
    if (pages.value.length <= 1) return
    const idx = pages.value.findIndex((p) => p.id === pageId)
    if (idx !== -1) {
      unregisterPageInstance(pageId)
      pages.value.splice(idx, 1)
      const newActiveIdx = Math.max(0, idx - 1)
      activePageId.value = pages.value[newActiveIdx]?.id || ''
    }
  }

  function isPageOverflowing(pageId: string, maxPrintableHeight: number): boolean {
    const editor = pageInstances.get(pageId)
    if (!editor || !editor.root) return false
    return editor.root.scrollHeight > maxPrintableHeight + 10
  }

  function splitPageOverflow(pageId: string, maxPrintableHeight: number): boolean {
    const editor = pageInstances.get(pageId)
    if (!editor || !editor.root) return false

    if (editor.root.scrollHeight <= maxPrintableHeight + 5) {
      return false
    }

    const children = Array.from(editor.root.children) as HTMLElement[]
    if (children.length <= 1) return false

    let splitIndex = -1
    for (let i = 0; i < children.length; i++) {
      const child = children[i]
      if (child.offsetTop + child.offsetHeight > maxPrintableHeight) {
        splitIndex = i
        break
      }
    }

    if (splitIndex <= 0) {
      splitIndex = Math.max(1, children.length - 1)
    }

    const overflowingElements = children.slice(splitIndex)
    const overflowHtml = overflowingElements.map((el) => el.outerHTML).join('')

    overflowingElements.forEach((el) => el.remove())
    const currentHtml = editor.root.innerHTML
    const p = pages.value.find((item) => item.id === pageId)
    if (p) p.html = currentHtml

    const pageIndex = pages.value.findIndex((item) => item.id === pageId)
    if (pageIndex !== -1 && pageIndex < pages.value.length - 1) {
      const nextPage = pages.value[pageIndex + 1]
      const nextEditor = pageInstances.get(nextPage.id)
      if (nextEditor) {
        const nextCurrentHtml = nextEditor.root.innerHTML
        const mergedHtml = overflowHtml + nextCurrentHtml
        nextEditor.clipboard.dangerouslyPasteHTML(0, mergedHtml, 'silent')
        nextPage.html = mergedHtml
      } else {
        nextPage.html = overflowHtml + nextPage.html
      }
    } else {
      addPage(pageId, overflowHtml)
    }

    return true
  }

  // ── HTML Normalizer: Strips arbitrary font sizes and families from AI or paste ──
  function normalizeContractHtml(rawHtml: string): string {
    if (!rawHtml || !rawHtml.trim()) return ''

    // 1. Strip rogue inline font-size, font-family, and line-height styles
    let cleaned = rawHtml
      .replace(/font-size\s*:\s*[^;"]+;?/gi, '')
      .replace(/font-family\s*:\s*[^;"]+;?/gi, '')
      .replace(/line-height\s*:\s*[^;"]+;?/gi, '')
      .replace(/background-color\s*:\s*transparent;?/gi, '')
      .replace(/style="\s*"/gi, '')
      .replace(/style='\s*'/gi, '')

    // 2. Standardize page break markers
    cleaned = cleaned.replace(
      /<div[^>]*class="[^"]*crms-page-break[^"]*"[^>]*><\/div>|<div[^>]*data-page-break="true"[^>]*><\/div>/gi,
      '<div class="crms-page-break" data-page-break="true"></div>'
    )

    return cleaned
  }

  // ── Splitter: Parses incoming HTML into discrete page chunks ──
  function splitHtmlIntoPages(html: string): string[] {
    const sanitized = normalizeContractHtml(html)
    if (!sanitized.trim()) {
      return ['']
    }

    // 1. If explicit page break markers exist in the HTML, split by them first!
    const breakMarker = '<div class="crms-page-break" data-page-break="true"></div>'
    if (sanitized.includes(breakMarker)) {
      const parts = sanitized.split(breakMarker).map((p) => p.trim()).filter((p) => p.length > 0)
      if (parts.length > 0) return parts
    }

    // Fallback: Check regex for variant page break markers
    const breakRegex = /<div[^>]*class="[^"]*crms-page-break[^"]*"[^>]*><\/div>|<div[^>]*data-page-break="true"[^>]*><\/div>/gi
    if (breakRegex.test(sanitized)) {
      const parts = sanitized.split(breakRegex).map((p) => p.trim()).filter((p) => p.length > 0)
      if (parts.length > 0) return parts
    }

    // 2. Parse blocks with DOMParser and chunk into pages intelligently
    const parser = new DOMParser()
    const doc = parser.parseFromString(sanitized, 'text/html')
    const blocks = Array.from(doc.body.children)

    if (blocks.length === 0) {
      return [sanitized]
    }

    const pageChunks: string[] = []
    let currentChunkHtml = ''
    let blockCount = 0

    // Typical contract page holds ~7-9 major block elements
    const BLOCKS_PER_PAGE = 7

    for (let i = 0; i < blocks.length; i++) {
      const el = blocks[i] as HTMLElement
      const isBreak = el.classList.contains('crms-page-break') || el.getAttribute('data-page-break') === 'true'

      if (isBreak) {
        if (currentChunkHtml.trim()) {
          pageChunks.push(currentChunkHtml)
          currentChunkHtml = ''
          blockCount = 0
        }
        continue
      }

      currentChunkHtml += el.outerHTML
      blockCount++

      // Major headings (H1 or H2) at a later block count make natural page dividers
      const isHeading = el.tagName === 'H1' || el.tagName === 'H2'

      if ((blockCount >= BLOCKS_PER_PAGE) || (blockCount >= 5 && isHeading && i < blocks.length - 2)) {
        pageChunks.push(currentChunkHtml)
        currentChunkHtml = ''
        blockCount = 0
      }
    }

    if (currentChunkHtml.trim()) {
      pageChunks.push(currentChunkHtml)
    }

    return pageChunks.length > 0 ? pageChunks : [sanitized]
  }

  function setCombinedHtml(html: string) {
    const sanitized = normalizeContractHtml(html)
    const chunks = splitHtmlIntoPages(sanitized)
    pages.value = chunks.map((chunkHtml, index) => ({
      id: `page-${index + 1}-${Date.now()}`,
      html: chunkHtml,
    }))
    activePageId.value = pages.value[0]?.id || 'page-1'

    nextTick(() => {
      pages.value.forEach((page) => {
        const editor = pageInstances.get(page.id)
        if (editor) {
          editor.clipboard.dangerouslyPasteHTML(0, page.html, 'silent')
        }
      })
    })
  }

  function getCombinedHtml(): string {
    return pages.value
      .map((p) => {
        const editor = pageInstances.get(p.id)
        if (editor) {
          return typeof (editor as any).getSemanticHTML === 'function'
            ? (editor as any).getSemanticHTML()
            : editor.root.innerHTML
        }
        return p.html
      })
      .join('<div class="crms-page-break" data-page-break="true"></div>')
  }

  // ── Toolbar Formatting Actions on Active Editor ──
  function formatText(format: 'bold' | 'italic' | 'underline' | 'strikethrough' | 'subscript' | 'superscript') {
    const editor = getActiveEditor()
    if (!editor) return

    if (format === 'bold') {
      editor.format('bold', !state.isBold)
      state.isBold = !state.isBold
    } else if (format === 'italic') {
      editor.format('italic', !state.isItalic)
      state.isItalic = !state.isItalic
    } else if (format === 'underline') {
      editor.format('underline', !state.isUnderline)
      state.isUnderline = !state.isUnderline
    } else if (format === 'strikethrough') {
      editor.format('strike', !state.isStrikethrough)
      state.isStrikethrough = !state.isStrikethrough
    } else if (format === 'subscript') {
      const newVal = state.isSubscript ? false : 'sub'
      editor.format('script', newVal)
      state.isSubscript = !state.isSubscript
      state.isSuperscript = false
    } else if (format === 'superscript') {
      const newVal = state.isSuperscript ? false : 'super'
      editor.format('script', newVal)
      state.isSuperscript = !state.isSuperscript
      state.isSubscript = false
    }
  }

  function formatAlign(align: 'left' | 'center' | 'right' | 'justify') {
    const editor = getActiveEditor()
    if (!editor) return
    editor.format('align', align === 'left' ? false : align)
    state.align = align
  }

  function formatBlock(type: 'paragraph' | 'h1' | 'h2' | 'h3' | 'quote') {
    const editor = getActiveEditor()
    if (!editor) return

    if (type === 'paragraph') {
      editor.format('header', false)
      editor.format('blockquote', false)
    } else if (type === 'quote') {
      editor.format('header', false)
      editor.format('blockquote', true)
    } else {
      const level = type === 'h1' ? 1 : type === 'h2' ? 2 : 3
      editor.format('blockquote', false)
      editor.format('header', level)
    }
    state.blockType = type
  }

  function formatList(type: 'bullet' | 'number') {
    const editor = getActiveEditor()
    if (!editor) return

    const current = editor.getFormat().list
    if (type === 'bullet') {
      editor.format('list', current === 'bullet' ? false : 'bullet')
      state.blockType = current === 'bullet' ? 'paragraph' : 'bullet'
    } else {
      editor.format('list', current === 'ordered' ? false : 'ordered')
      state.blockType = current === 'ordered' ? 'paragraph' : 'number'
    }
  }

  function setFontFamily(font: string) {
    const editor = getActiveEditor()
    if (!editor) return
    editor.format('font', font)
    state.fontFamily = font
  }

  function setFontSize(size: string) {
    const editor = getActiveEditor()
    if (!editor) return
    editor.format('size', size)
    state.fontSize = size
  }

  function setFontColor(color: string) {
    const editor = getActiveEditor()
    if (!editor) return
    editor.format('color', color)
    state.fontColor = color
  }

  function setHighlight(color: string) {
    const editor = getActiveEditor()
    if (!editor) return
    editor.format('background', color === 'transparent' ? false : color)
    state.highlightColor = color
  }

  function insertTable(rows: number = 3, cols: number = 3) {
    const editor = getActiveEditor()
    if (!editor) return

    const tableModule = (editor as any).getModule('table')
    if (tableModule && typeof tableModule.insertTable === 'function') {
      tableModule.insertTable(rows, cols)
    } else {
      let tableHtml = '<table class="crms-quill-table"><tbody>'
      for (let r = 0; r < rows; r++) {
        tableHtml += '<tr>'
        for (let c = 0; c < cols; c++) {
          tableHtml += r === 0 ? '<th>Header</th>' : '<td>Cell</td>'
        }
        tableHtml += '</tr>'
      }
      tableHtml += '</tbody></table><p><br></p>'
      const range = editor.getSelection(true)
      editor.clipboard.dangerouslyPasteHTML(range.index, tableHtml)
    }
  }

  function insertImage(src: string, _alt?: string) {
    const editor = getActiveEditor()
    if (!editor || !src) return
    const range = editor.getSelection(true)
    editor.insertEmbed(range.index, 'image', src, 'user')
    editor.setSelection(range.index + 1)
  }

  function insertLink(url: string, text?: string) {
    const editor = getActiveEditor()
    if (!editor || !url) return
    const range = editor.getSelection()
    if (range && range.length > 0) {
      editor.format('link', url)
    } else {
      const idx = range ? range.index : editor.getLength()
      const label = text || url
      editor.insertText(idx, label, { link: url }, 'user')
      editor.setSelection(idx + label.length)
    }
  }

  function insertHorizontalRule() {
    const editor = getActiveEditor()
    if (!editor) return
    const range = editor.getSelection(true)
    editor.insertEmbed(range.index, 'divider', true, 'user')
    editor.insertText(range.index + 1, '\n', 'user')
    editor.setSelection(range.index + 2)
  }

  function clearFormatting() {
    const editor = getActiveEditor()
    if (!editor) return
    const range = editor.getSelection()
    if (range && range.length > 0) {
      editor.removeFormat(range.index, range.length)
      editor.format('font', 'Poppins')
      editor.format('size', '11pt')
      editor.format('color', '#000000')
    }
  }

  function undo() {
    const editor = getActiveEditor() as any
    editor?.history?.undo?.()
  }

  function redo() {
    const editor = getActiveEditor() as any
    editor?.history?.redo?.()
  }

  function setPaperSize(size: PaperSize) {
    state.paperSize = size
  }

  function setOrientation(orientation: PageOrientation) {
    state.orientation = orientation
  }

  return {
    pages,
    activePageId,
    state,
    registerPageInstance,
    unregisterPageInstance,
    setActivePage,
    addPage,
    removePage,
    formatText,
    formatAlign,
    formatBlock,
    formatList,
    insertTable,
    insertImage,
    insertLink,
    insertHorizontalRule,
    clearFormatting,
    setPaperSize,
    setOrientation,
    setFontFamily,
    setFontSize,
    setFontColor,
    setHighlight,
    undo,
    redo,
    getCombinedHtml,
    setCombinedHtml,
    isPageOverflowing,
    splitPageOverflow,
  }
}
