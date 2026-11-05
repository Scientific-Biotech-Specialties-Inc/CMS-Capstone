import { reactive } from 'vue'
import type { ContractRegion, UploadedDoc } from '@/types/contract'

export interface DraftForm {
  businessPartner: string
  category:        string
  itemCode:        string
  description:     string
  serialNo:        string
  sbuNumber:       string
  region:          ContractRegion | ''
  startDate:       string
  endDate:         string
  prsActivityId?:  number | ''
}

export interface GeneratedDocument {
  title: string
  html: string
}

export interface HeaderFooterConfig {
  headerLeft?: string
  headerRight?: string
  footerLeft?: string
  headerHtml?: string
  footerHtml?: string
  headerLogoUrl?: string
  showPageNumber: boolean
}

export interface Draft {
  active: boolean
  form:   DraftForm
  docs:   UploadedDoc[]
  role:   'sales' | 'manager' | 'admin' | ''
  generatedDoc?: GeneratedDocument | null
  headerFooter?: HeaderFooterConfig | null
}

// Module-level singleton
const draft = reactive<Draft>({
  active: false,
  form: {
    businessPartner: '',
    category: '',
    itemCode: '',
    description: '',
    serialNo: '',
    sbuNumber: '',
    region: '',
    startDate: '',
    endDate: '',
    prsActivityId: ''
  },
  docs: [],
  role: '',
  generatedDoc: null,
  headerFooter: {
    headerLeft: 'CONFIDENTIAL CONTRACT AGREEMENT',
    headerRight: 'SBSI CRMS',
    footerLeft: 'Authorized Legal Draft',
    headerHtml: '',
    footerHtml: '',
    headerLogoUrl: '',
    showPageNumber: true,
  }
})

export function useCreateContractDraft() {
  function saveDraft(form: DraftForm, docs: UploadedDoc[], role: Draft['role']) {
    Object.assign(draft.form, form)
    draft.docs   = [...docs]
    draft.role   = role
    draft.active = true
  }

  function restoreDraft() {
    return draft.active ? { ...draft, docs: [...draft.docs] } : null
  }

  function setGeneratedDoc(doc: GeneratedDocument | null) {
    draft.generatedDoc = doc
  }

  function attachDoc(doc: UploadedDoc) {
    const existingIdx = draft.docs.findIndex(d => d.name === doc.name || (d.id && doc.id && d.id === doc.id))
    if (existingIdx !== -1) {
      draft.docs[existingIdx] = doc
    } else {
      draft.docs.push(doc)
    }
    draft.active = true
  }

  function clearDraft() {
    draft.active = false
    draft.docs   = []
    draft.role   = ''
    draft.generatedDoc = null
    Object.keys(draft.form).forEach(k => { 
      (draft.form as any)[k] = '' 
    })
  }

  return { draft, saveDraft, restoreDraft, setGeneratedDoc, attachDoc, clearDraft }
}
