// Feature 3: Vendor AI Suggestions — Gemini-suggested candidate PH business
// partners/suppliers (Admin-only), reviewed/edited before being saved as a
// real supplier or business partner via vendor-management.

export type VendorSuggestionStatus = 'pending' | 'completed' | 'failed'
export type CandidateDecision = 'pending' | 'accepted' | 'dismissed'

export interface VendorSuggestionCandidate {
  id:               number
  name:             string
  industry:         string | null
  region:           'Luzon' | 'Visayas' | 'Mindanao' | null
  contactEmail:     string | null
  contactNumber:    string | null
  address:          string | null
  suggestionScore:  number | null
  suggestionReason: string | null
  decision:         CandidateDecision
}

export interface VendorSuggestionBatch {
  id:            number
  status:        VendorSuggestionStatus
  industryHint:  string | null
  regionHint:    string | null
  candidates:    VendorSuggestionCandidate[]
}

// Per-tab review form state — reuses the same field shape as
// AddPartnerForm/AddPartnerPage.vue so the review step's inputs match the
// manual Add Partner/Supplier form exactly.
export interface VendorSuggestionReviewForm {
  candidateId:  number
  vendorType:   'supplier' | 'partner'
  name:         string
  industry:     string
  region:       'Luzon' | 'Visayas' | 'Mindanao' | ''
  contactPerson: string
  email:        string
  phone:        string
  address:      string
  tinNumber:    string
  bpCode:       string
}
