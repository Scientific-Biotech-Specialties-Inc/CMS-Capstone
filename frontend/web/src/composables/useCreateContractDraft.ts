import { reactive } from 'vue'
import type { ContractRegion, UploadedDoc } from '@/types/contract'

export interface DraftForm {
  businessPartner: string
  category:        string
  itemCode:        string
  description:     string
  serialNo:        string
  sbuNumber:       string
  sbsiBranch:      string
  equipmentName:   string
  brand:           string
  model:           string
  region:          ContractRegion | ''
  startDate:       string
  endDate:         string
  prsActivityId?:  number | ''
  productId?:      number
  prsInstitutionId?: number
  prsBranchId?:    number
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
  targetContractId?: string
  targetContractDbId?: number
  returnPath?: string
  returnEditMode?: boolean
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
    sbsiBranch: '',
    equipmentName: '',
    brand: '',
    model: '',
    region: '',
    startDate: '',
    endDate: '',
    prsActivityId: ''
  },
  docs: [],
  role: '',
  generatedDoc: null,
  targetContractId: undefined,
  targetContractDbId: undefined,
  returnPath: undefined,
  returnEditMode: false,
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

export interface SaveDraftTarget {
  targetContractId?: string
  targetContractDbId?: number
  returnPath?: string
  returnEditMode?: boolean
}

export function useCreateContractDraft() {
  function saveDraft(form: DraftForm, docs: UploadedDoc[], role: Draft['role'], target?: SaveDraftTarget) {
    draft.form = { ...form }
    draft.docs   = [...docs]
    draft.role   = role
    draft.targetContractId = target?.targetContractId
    draft.targetContractDbId = target?.targetContractDbId
    draft.returnPath = target?.returnPath
    draft.returnEditMode = target?.returnEditMode ?? false
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
    draft.targetContractId = undefined
    draft.targetContractDbId = undefined
    draft.returnPath = undefined
    draft.returnEditMode = false
    Object.keys(draft.form).forEach(k => { 
      (draft.form as any)[k] = '' 
    })
  }

  return { draft, saveDraft, restoreDraft, setGeneratedDoc, attachDoc, clearDraft }
}
