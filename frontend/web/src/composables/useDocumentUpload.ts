import { useAuth } from './useAuth'
import type { UploadedDoc } from '@/types/contract'

/**
 * Shared logic for detecting/validating contract document files and
 * uploading them to the contract-management `/documents/upload` endpoint.
 *
 * Used by:
 * - DocumentUpload.vue (manual attachment UI)
 * - CreateContract.vue views (auto-attaching the file used for "Fill with OCR")
 */
export function useDocumentUpload() {
  const { state: authState } = useAuth()
  const MAX_BYTES = 10 * 1024 * 1024 // 10 MB

  function detectType(f: File): 'pdf' | 'docx' | null {
    const name = f.name.toLowerCase()
    if (f.type === 'application/pdf' || name.endsWith('.pdf')) return 'pdf'
    if (
      f.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
      name.endsWith('.docx')
    ) return 'docx'
    return null
  }

  function validate(f: File, existing: UploadedDoc[]): string {
    if (!detectType(f))     return 'Only PDF or DOCX files are accepted.'
    if (f.size > MAX_BYTES) return `File exceeds the 10 MB limit (${(f.size / 1024 / 1024).toFixed(2)} MB).`
    const isDuplicate = existing.some(d => d.name === f.name && d.size === f.size)
    if (isDuplicate)        return 'This file has already been added.'
    return ''
  }

  /**
   * Uploads a single file to the document store and reports progress via
   * `onUpdate`, mirroring the uploading -> scanning -> success/error stages
   * used by DocumentUpload.vue.
   */
  async function uploadFile(doc: UploadedDoc, onUpdate: (patch: Partial<UploadedDoc>) => void): Promise<void> {
    if (!doc.file) return

    onUpdate({ uploadStatus: 'uploading' })

    const scanTimer = setTimeout(() => {
      onUpdate({ uploadStatus: 'scanning' })
    }, 450)

    try {
      const formData = new FormData()
      formData.append('file', doc.file)

      const res = await fetch(`${import.meta.env.VITE_CONTRACT_API_URL}/documents/upload`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${authState.token}`,
        },
        body: formData,
      })

      clearTimeout(scanTimer)
      const data = await res.json()

      if (res.ok) {
        onUpdate({
          id: data.data.document_id,
          uploadStatus: 'success',
          scanWarning: data.scan_warning,
        })
      } else {
        const msg = data.errors?.file?.[0] || data.message || 'Upload failed.'
        onUpdate({ uploadStatus: 'error', errorMessage: msg })
      }
    } catch {
      clearTimeout(scanTimer)
      onUpdate({ uploadStatus: 'error', errorMessage: 'Network error. Failed to scan file.' })
    }
  }

  return {
    MAX_BYTES,
    detectType,
    validate,
    uploadFile,
  }
}
