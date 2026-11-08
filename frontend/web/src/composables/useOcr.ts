import { ref } from 'vue'
import { useAuth } from './useAuth'
import type { OcrExtractedData } from '@/types/ocr'

const BASE_URL = (import.meta.env.VITE_AI_API_URL as string) || 'http://localhost:8006/api'

export function useOcr() {
  const loading = ref(false)
  const error = ref<string | null>(null)
  const data = ref<OcrExtractedData | null>(null)

  async function extractFromFile(file: File, candidatePartners: string[] = []): Promise<OcrExtractedData | null> {
    loading.value = true
    error.value = null
    data.value = null

    try {
      const { state } = useAuth()
      const formData = new FormData()
      formData.append('file', file)
      candidatePartners.forEach(partner => {
        formData.append('candidate_partners[]', partner)
      })

      // We omit Content-Type header so the browser calculates the boundary itself
      const res = await fetch(`${BASE_URL}/ocr/extract`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${state.token}`,
        },
        body: formData,
      })

      const json = await res.json().catch(() => ({}))

      if (!res.ok) {
        error.value = json.message || 'Failed to extract text from the file.'
        return null
      }

      data.value = json.data as OcrExtractedData
      return json.data as OcrExtractedData
    } catch (e) {
      console.error('OCR Extraction error:', e)
      error.value = 'Network error. Could not connect to the OCR service.'
      return null
    } finally {
      loading.value = false
    }
  }

  async function extractFromDocumentId(documentId: string, candidatePartners: string[] = []): Promise<OcrExtractedData | null> {
    loading.value = true
    error.value = null
    data.value = null

    try {
      const { state } = useAuth()
      const res = await fetch(`${BASE_URL}/ocr/extract`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${state.token}`,
        },
        body: JSON.stringify({
          document_id: documentId,
          candidate_partners: candidatePartners,
        }),
      })

      const json = await res.json().catch(() => ({}))

      if (!res.ok) {
        error.value = json.message || 'Failed to extract text from the document.'
        return null
      }

      data.value = json.data as OcrExtractedData
      return json.data as OcrExtractedData
    } catch (e) {
      console.error('OCR Extraction error:', e)
      error.value = 'Network error. Could not connect to the OCR service.'
      return null
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    data,
    extractFromFile,
    extractFromDocumentId,
  }
}
