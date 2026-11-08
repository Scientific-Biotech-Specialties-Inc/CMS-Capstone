import { ref } from 'vue'
import { useAuth } from './useAuth'

const BASE_URL = (import.meta.env.VITE_AI_API_URL as string) || 'http://localhost:8006/api'

export interface GenerateContractParams {
  businessPartner: string
  category: string
  itemCode: string
  description: string
  serialNo: string
  sbuNumber: string
  region: string
  startDate: string
  endDate: string
  prsActivityId?: number | ''
}

export interface GeneratedContractData {
  title: string
  summary: string
  html_content: string
  source?: 'gemini' | 'template_engine' | 'client_fallback'
}

export function useContractGenerator() {
  const isGenerating = ref(false)
  const error = ref<string | null>(null)
  const result = ref<GeneratedContractData | null>(null)

  async function generate(params: GenerateContractParams): Promise<GeneratedContractData> {
    isGenerating.value = true
    error.value = null

    const payload = {
      business_partner: params.businessPartner,
      category: params.category,
      item_code: params.itemCode,
      description: params.description,
      serial_number: params.serialNo,
      sbu_number: params.sbuNumber,
      region: params.region,
      start_date: params.startDate,
      end_date: params.endDate,
      prs_activity_id: params.prsActivityId ? Number(params.prsActivityId) : null,
    }

    try {
      const { state } = useAuth()
      const res = await fetch(`${BASE_URL}/contracts/generate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${state.token}`,
        },
        body: JSON.stringify(payload),
      })

      const json = await res.json().catch(() => ({}))

      if (res.ok && json.data) {
        result.value = {
          title: json.data.title || `${params.category} - ${params.businessPartner}`,
          summary: json.data.summary || '',
          html_content: json.data.html_content || '',
          source: json.source || 'gemini',
        }
        isGenerating.value = false
        return result.value
      }

      console.warn('Backend contract generation returned error, using client fallback:', json.message)
    } catch (err) {
      console.warn('Network issue reaching AI service, using client fallback generator:', err)
    }

    // Client fallback to ensure user is never blocked
    const fallback = generateClientFallback(params)
    result.value = fallback
    isGenerating.value = false
    return fallback
  }

  function generateClientFallback(params: GenerateContractParams): GeneratedContractData {
    const norm = (params.category || '').toLowerCase()

    if (norm.includes('supply')) {
      return buildSupplyFallback(params)
    }
    if (norm.includes('lease')) {
      return buildLeaseFallback(params)
    }
    if (norm.includes('maintenance')) {
      return buildMaintenanceFallback(params)
    }
    if (norm.includes('partnership')) {
      return buildPartnershipFallback(params)
    }

    return buildServiceFallback(params)
  }

  function buildServiceFallback(params: GenerateContractParams): GeneratedContractData {
    const title = 'COMMERCIAL SERVICES AGREEMENT'
    const html = `
<h1 style="text-align: center;"><strong>${title}</strong></h1>
<p style="text-align: center; color: #555555;"><strong>CONTRACT NO. SBSI-${params.sbuNumber}-${params.itemCode}</strong></p>
<hr />
<p>This <strong>Service Agreement</strong> (the &ldquo;Agreement&rdquo;) is entered into and made effective as of <strong>${params.startDate}</strong>, by and between:</p>

<p><strong>SBSI CORPORATION</strong>, a corporation organized and existing under the laws of the Republic of the Philippines, having its principal office at Metro Manila, Philippines (hereinafter referred to as the &ldquo;First Party&rdquo; or &ldquo;Client&rdquo;);</p>

<p style="text-align: center;">- and -</p>

<p><strong>${params.businessPartner}</strong>, an authorized business entity operating in <strong>${params.region}</strong>, Philippines (hereinafter referred to as the &ldquo;Second Party&rdquo; or &ldquo;Contractor&rdquo;).</p>

<p>The First Party and the Second Party may collectively be referred to as the &ldquo;Parties&rdquo; and individually as a &ldquo;Party&rdquo;.</p>

<h2><strong>1. RECITALS &amp; APPOINTMENT</strong></h2>
<p><strong>WHEREAS</strong>, the First Party requires specialized technical engineering, deployment, and servicing of <em>${params.description}</em> for Strategic Business Unit <strong>${params.sbuNumber}</strong>;</p>
<p><strong>WHEREAS</strong>, the Second Party possesses the requisite skill, technical qualifications, and equipment to fulfill these deliverables according to SBSI standards;</p>
<p><strong>NOW, THEREFORE</strong>, the Parties mutually agree to the terms set forth below:</p>

<h2><strong>2. SCOPE OF SERVICES &amp; TECHNICAL SPECIFICATIONS</strong></h2>
<p>The Second Party agrees to deliver, install, and support the following item:</p>
<table class="crms-quill-table" style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; margin-bottom: 0.75rem;">
  <tbody>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold; width: 35%;">Service Deliverable</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.description}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Designated Item Code</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.itemCode}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Serial Identification</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.serialNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Assigned Strategic Unit</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">SBU ${params.sbuNumber}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Operational Region</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.region}, Philippines</td>
    </tr>
  </tbody>
</table>

<h2><strong>3. CONTRACT TERM &amp; DURATION</strong></h2>
<p>This Agreement takes effect on <strong>${params.startDate}</strong> and remains effective until <strong>${params.endDate}</strong>, unless terminated earlier in accordance with Section 7.</p>

<div class="crms-page-break"></div>

<h2><strong>4. SERVICE LEVEL AGREEMENT (SLA) &amp; PERFORMANCE STANDARDS</strong></h2>
<ol>
    <li>The Second Party guarantees that all services under Item Code <strong>${params.itemCode}</strong> meet commercial standards of excellence with a minimum 99.9% uptime.</li>
    <li>Any identified defects or operational discrepancies shall be remedied within twenty-four (24) business hours upon formal notification.</li>
</ol>

<h2><strong>5. COMPENSATION &amp; BILLING SCHEDULE</strong></h2>
<p>In consideration of the faithful performance of deliverables, the First Party shall compensate the Second Party in accordance with milestone progress billings, less applicable withholding taxes under Philippine law.</p>

<h2><strong>6. CONFIDENTIALITY &amp; DATA PRIVACY (RA 10173)</strong></h2>
<p>Both Parties shall protect proprietary trade secrets, customer data, and operational records in compliance with Republic Act No. 10173 (Philippine Data Privacy Act of 2012).</p>

<h2><strong>7. TERMINATION &amp; DEFAULT REMEDIES</strong></h2>
<p>Either Party may terminate this Agreement upon thirty (30) days prior written notice in the event of default or uncured breach.</p>

<div class="crms-page-break"></div>

<h2><strong>8. GOVERNING LAW &amp; DISPUTE RESOLUTION</strong></h2>
<p>This Agreement is governed by Philippine law. Any dispute shall be submitted to the competent courts of Metro Manila, Philippines, to the exclusion of all other courts.</p>

<h2><strong>9. GENERAL PROVISIONS</strong></h2>
<p>This Agreement constitutes the entire agreement between the Parties with respect to the subject matter hereof and supersedes all prior negotiations or agreements. Any modification shall only be valid if executed in writing and signed by both Parties.</p>

<h2><strong>10. EXECUTION &amp; FORMAL SIGNATURES</strong></h2>
<p>IN WITNESS WHEREOF, the Parties hereto have caused this Agreement to be executed by their duly authorized representatives as of the date first above written.</p>

<table class="crms-quill-table" style="width: 100%; border: 1px solid #cbd5e1; margin-top: 1.5rem; border-collapse: collapse;">
  <tbody>
    <tr>
      <th style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left; width: 50%;">FOR SBSI CORPORATION (CLIENT)</th>
      <th style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left; width: 50%;">FOR SECOND PARTY (CONTRACTOR)</th>
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
        ${params.businessPartner}<br />
        Date: ________________________
      </td>
    </tr>
  </tbody>
</table>
`
    return {
      title: `${title} - ${params.businessPartner}`,
      summary: `Commercial Services Agreement between SBSI and ${params.businessPartner} for ${params.description}.`,
      html_content: html,
      source: 'client_fallback',
    }
  }

  function buildSupplyFallback(params: GenerateContractParams): GeneratedContractData {
    const title = 'COMMERCIAL SUPPLY CONTRACT'
    const html = `
<h1 style="text-align: center;"><strong>${title}</strong></h1>
<p style="text-align: center; color: #555555;"><strong>CONTRACT NO. SBSI-${params.sbuNumber}-${params.itemCode}</strong></p>
<hr />
<p>This <strong>Supply Contract</strong> (the &ldquo;Agreement&rdquo;) is entered into as of <strong>${params.startDate}</strong>, by and between <strong>SBSI CORPORATION</strong> (&ldquo;Purchaser&rdquo;) and <strong>${params.businessPartner}</strong> (&ldquo;Supplier&rdquo;).</p>

<h2><strong>1. RECITALS &amp; ENGAGEMENT</strong></h2>
<p>WHEREAS, the Purchaser requires reliable commercial supply of goods for Strategic Business Unit <strong>${params.sbuNumber}</strong>; and Supplier maintains inventory and distribution capacity to deliver conforming items.</p>

<h2><strong>2. GOODS SPECIFICATIONS &amp; DELIVERY COMMITMENTS</strong></h2>
<table class="crms-quill-table" style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; margin-bottom: 0.75rem;">
  <tbody>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold; width: 35%;">Designated Goods Description</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.description}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Inventory / SKU Code</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.itemCode}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Batch / Serial Identifier</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.serialNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Consignee Strategic Unit</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">SBU ${params.sbuNumber}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Delivery Region</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.region}, Philippines</td>
    </tr>
  </tbody>
</table>

<h2><strong>3. CONTRACT TERM &amp; SUPPLY TIMELINE</strong></h2>
<p>This Supply Contract covers purchase orders issued between <strong>${params.startDate}</strong> and <strong>${params.endDate}</strong>.</p>

<div class="crms-page-break"></div>

<h2><strong>4. INSPECTION, ACCEPTANCE &amp; QUALITY TESTING</strong></h2>
<ol>
  <li>Purchaser inspects all deliveries upon arrival; non-conforming items shall be replaced within seven (7) days at Supplier's cost.</li>
  <li>Formal acceptance occurs upon signed Receiving Inspection Reports.</li>
</ol>

<h2><strong>5. PURCHASE PRICE, BILLING &amp; PAYMENT TERMS</strong></h2>
<p>Invoices must include signed delivery receipts and shall be paid within thirty (30) days from verified receipt, less withholding taxes.</p>

<h2><strong>6. TITLE RETENTION, PACKAGING &amp; RISK OF LOSS</strong></h2>
<p>Delivery is F.O.B. Destination. Title and risk of loss pass to Purchaser only upon physical acceptance.</p>

<h2><strong>7. PRODUCT WARRANTIES &amp; REPLACEMENT REMEDIES</strong></h2>
<p>Supplier warrants goods are genuine, free of defects, with a minimum 12-month replacement warranty.</p>

<div class="crms-page-break"></div>

<h2><strong>8. TERMINATION &amp; DEFAULT</strong></h2>
<p>Failure to supply within timelines entitles Purchaser to cancel and source alternatives at Supplier's cost.</p>

<h2><strong>9. GOVERNING LAW &amp; DISPUTE RESOLUTION</strong></h2>
<p>Governed by Philippine law with dispute venue in Metro Manila courts.</p>

<h2><strong>10. EXECUTION &amp; FORMAL SIGNATURES</strong></h2>
<table class="crms-quill-table" style="width: 100%; border: 1px solid #cbd5e1; margin-top: 1.5rem; border-collapse: collapse;">
  <tbody>
    <tr>
      <th style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left; width: 50%;">FOR SBSI CORPORATION (PURCHASER)</th>
      <th style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left; width: 50%;">FOR SECOND PARTY (SUPPLIER)</th>
    </tr>
    <tr>
      <td style="padding: 36px 12px 14px; border: 1px solid #cbd5e1; vertical-align: bottom;">
        ____________________________________________<br />
        <strong>AUTHORIZED PURCHASING OFFICER</strong><br />
        SBSI Corporation<br />
        Date: ________________________
      </td>
      <td style="padding: 36px 12px 14px; border: 1px solid #cbd5e1; vertical-align: bottom;">
        ____________________________________________<br />
        <strong>AUTHORIZED COMMERCIAL AGENT</strong><br />
        ${params.businessPartner}<br />
        Date: ________________________
      </td>
    </tr>
  </tbody>
</table>
`
    return {
      title: `${title} - ${params.businessPartner}`,
      summary: `Commercial Supply Contract between SBSI and ${params.businessPartner} for ${params.description}.`,
      html_content: html,
      source: 'client_fallback',
    }
  }

  function buildLeaseFallback(params: GenerateContractParams): GeneratedContractData {
    const title = 'EQUIPMENT LEASE AGREEMENT'
    const html = `
<h1 style="text-align: center;"><strong>${title}</strong></h1>
<p style="text-align: center; color: #555555;"><strong>CONTRACT NO. SBSI-${params.sbuNumber}-${params.itemCode}</strong></p>
<hr />
<p>This <strong>Equipment Lease Agreement</strong> is entered into as of <strong>${params.startDate}</strong>, between <strong>SBSI CORPORATION</strong> (&ldquo;Lessee&rdquo;) and <strong>${params.businessPartner}</strong> (&ldquo;Lessor&rdquo;).</p>

<h2><strong>1. RECITALS &amp; LEASE DEMISE</strong></h2>
<p>Lessor leases to Lessee the industrial equipment described herein for operations within SBU <strong>${params.sbuNumber}</strong>.</p>

<h2><strong>2. EQUIPMENT IDENTIFICATION &amp; SPECIFICATIONS</strong></h2>
<table class="crms-quill-table" style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; margin-bottom: 0.75rem;">
  <tbody>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold; width: 35%;">Leased Equipment</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.description}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Asset Item Code</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.itemCode}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Serial Identifier</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.serialNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Assigned SBU</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">SBU ${params.sbuNumber}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Authorized Region</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.region}, Philippines</td>
    </tr>
  </tbody>
</table>

<h2><strong>3. LEASE TERM &amp; COMMENCEMENT</strong></h2>
<p>Effective from <strong>${params.startDate}</strong> to <strong>${params.endDate}</strong>.</p>

<div class="crms-page-break"></div>

<h2><strong>4. RENTAL CHARGES, SECURITY DEPOSIT &amp; BILLING</strong></h2>
<p>Lessee pays agreed periodic lease fees upon receipt of invoice, less withholding tax.</p>

<h2><strong>5. TITLE RETENTION &amp; CARE OF PROPERTY</strong></h2>
<p>Equipment remains the sole property of Lessor without liens or unauthorized encumbrances.</p>

<h2><strong>6. PERMITTED OPERATION &amp; MAINTENANCE</strong></h2>
<p>Operated strictly by qualified personnel following manufacturer manuals.</p>

<h2><strong>7. RETURN OF EQUIPMENT &amp; SURRENDER CONDITION</strong></h2>
<p>Surrendered in good working order, ordinary wear and tear excepted.</p>

<div class="crms-page-break"></div>

<h2><strong>8. DEFAULT &amp; REPOSSESSION</strong></h2>
<p>Uncured default entitles Lessor to repossession without court intervention.</p>

<h2><strong>9. GOVERNING LAW &amp; JURISDICTION</strong></h2>
<p>Governed by Philippine law with venue in Metro Manila courts.</p>

<h2><strong>10. EXECUTION &amp; FORMAL SIGNATURES</strong></h2>
<table class="crms-quill-table" style="width: 100%; border: 1px solid #cbd5e1; margin-top: 1.5rem; border-collapse: collapse;">
  <tbody>
    <tr>
      <th style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left; width: 50%;">FOR SBSI CORPORATION (LESSEE)</th>
      <th style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left; width: 50%;">FOR SECOND PARTY (LESSOR)</th>
    </tr>
    <tr>
      <td style="padding: 36px 12px 14px; border: 1px solid #cbd5e1; vertical-align: bottom;">
        ____________________________________________<br />
        <strong>AUTHORIZED SIGNATORY</strong><br />
        SBSI Corporation<br />
        Date: ________________________
      </td>
      <td style="padding: 36px 12px 14px; border: 1px solid #cbd5e1; vertical-align: bottom;">
        ____________________________________________<br />
        <strong>AUTHORIZED SIGNATORY</strong><br />
        ${params.businessPartner}<br />
        Date: ________________________
      </td>
    </tr>
  </tbody>
</table>
`
    return {
      title: `${title} - ${params.businessPartner}`,
      summary: `Equipment Lease Agreement between SBSI and ${params.businessPartner} for ${params.description}.`,
      html_content: html,
      source: 'client_fallback',
    }
  }

  function buildMaintenanceFallback(params: GenerateContractParams): GeneratedContractData {
    const title = 'EQUIPMENT MAINTENANCE & SERVICING AGREEMENT'
    const html = `
<h1 style="text-align: center;"><strong>${title}</strong></h1>
<p style="text-align: center; color: #555555;"><strong>CONTRACT NO. SBSI-${params.sbuNumber}-${params.itemCode}</strong></p>
<hr />
<p>This <strong>Equipment Maintenance Agreement</strong> is entered into as of <strong>${params.startDate}</strong>, between <strong>SBSI CORPORATION</strong> (&ldquo;Client&rdquo;) and <strong>${params.businessPartner}</strong> (&ldquo;Maintenance Provider&rdquo;).</p>

<h2><strong>1. RECITALS &amp; APPOINTMENT</strong></h2>
<p>Client engages Maintenance Provider to provide scheduled preventive servicing and 24-hour emergency response for equipment in SBU <strong>${params.sbuNumber}</strong>.</p>

<h2><strong>2. COVERED EQUIPMENT &amp; SERVICE SPECIFICATIONS</strong></h2>
<table class="crms-quill-table" style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; margin-bottom: 0.75rem;">
  <tbody>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold; width: 35%;">Covered Equipment</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.description}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Asset Item Code</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.itemCode}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Serial Identification</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.serialNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Assigned SBU</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">SBU ${params.sbuNumber}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Servicing Region</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.region}, Philippines</td>
    </tr>
  </tbody>
</table>

<h2><strong>3. AGREEMENT TERM &amp; COVERAGE SCHEDULE</strong></h2>
<p>Takes effect on <strong>${params.startDate}</strong> and terminates on <strong>${params.endDate}</strong>.</p>

<div class="crms-page-break"></div>

<h2><strong>4. PREVENTIVE MAINTENANCE SCHEDULE &amp; ROUTINE SERVICING</strong></h2>
<p>Structured quarterly preventive service visits with documented certification logs.</p>

<h2><strong>5. CORRECTIVE SERVICING &amp; 24-HOUR EMERGENCY RESPONSE</strong></h2>
<p>Guaranteed on-site technical response within twenty-four (24) business hours in ${params.region}.</p>

<h2><strong>6. SPARE PARTS AUTHENTICITY &amp; WORKMANSHIP WARRANTIES</strong></h2>
<p>Genuine manufacturer parts with a 90-day parts and labor warranty.</p>

<h2><strong>7. SERVICE COMPENSATION &amp; INVOICING SCHEDULE</strong></h2>
<p>Periodic retainers paid upon verified service sign-off, less withholding taxes.</p>

<div class="crms-page-break"></div>

<h2><strong>8. TERMINATION, DEFAULT &amp; PENALTIES</strong></h2>
<p>Repeated SLA defaults entitle Client to terminate with thirty (30) days notice.</p>

<h2><strong>9. GOVERNING LAW &amp; DISPUTE RESOLUTION</strong></h2>
<p>Governed by Philippine law with venue in Metro Manila courts.</p>

<h2><strong>10. EXECUTION &amp; FORMAL SIGNATURES</strong></h2>
<table class="crms-quill-table" style="width: 100%; border: 1px solid #cbd5e1; margin-top: 1.5rem; border-collapse: collapse;">
  <tbody>
    <tr>
      <th style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left; width: 50%;">FOR SBSI CORPORATION (CLIENT)</th>
      <th style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left; width: 50%;">FOR MAINTENANCE PROVIDER</th>
    </tr>
    <tr>
      <td style="padding: 36px 12px 14px; border: 1px solid #cbd5e1; vertical-align: bottom;">
        ____________________________________________<br />
        <strong>AUTHORIZED OPERATIONS MANAGER</strong><br />
        SBSI Corporation<br />
        Date: ________________________
      </td>
      <td style="padding: 36px 12px 14px; border: 1px solid #cbd5e1; vertical-align: bottom;">
        ____________________________________________<br />
        <strong>AUTHORIZED SERVICE DIRECTOR</strong><br />
        ${params.businessPartner}<br />
        Date: ________________________
      </td>
    </tr>
  </tbody>
</table>
`
    return {
      title: `${title} - ${params.businessPartner}`,
      summary: `Equipment Maintenance Agreement between SBSI and ${params.businessPartner} for ${params.description}.`,
      html_content: html,
      source: 'client_fallback',
    }
  }

  function buildPartnershipFallback(params: GenerateContractParams): GeneratedContractData {
    const title = 'STRATEGIC PARTNERSHIP & COLLABORATION AGREEMENT'
    const html = `
<h1 style="text-align: center;"><strong>${title}</strong></h1>
<p style="text-align: center; color: #555555;"><strong>CONTRACT NO. SBSI-${params.sbuNumber}-${params.itemCode}</strong></p>
<hr />
<p>This <strong>Strategic Partnership Agreement</strong> is entered into as of <strong>${params.startDate}</strong>, between <strong>SBSI CORPORATION</strong> (&ldquo;First Partner&rdquo;) and <strong>${params.businessPartner}</strong> (&ldquo;Second Partner&rdquo;).</p>

<h2><strong>1. RECITALS &amp; MUTUAL PURPOSE</strong></h2>
<p>The Partners join strategic capabilities to deploy deliverables for Strategic Business Unit <strong>${params.sbuNumber}</strong>.</p>

<h2><strong>2. STRATEGIC SCOPE &amp; ALLOCATION OF RESPONSIBILITIES</strong></h2>
<table class="crms-quill-table" style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; margin-bottom: 0.75rem;">
  <tbody>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold; width: 35%;">Partnership Deliverable / Focus</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.description}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Project Reference Code</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.itemCode}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Serial Identification</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.serialNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Allocated SBU</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">SBU ${params.sbuNumber}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Operating Region</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">${params.region}, Philippines</td>
    </tr>
  </tbody>
</table>

<h2><strong>3. PARTNERSHIP DURATION &amp; TIMELINE</strong></h2>
<p>Takes effect on <strong>${params.startDate}</strong> and terminates on <strong>${params.endDate}</strong>.</p>

<div class="crms-page-break"></div>

<h2><strong>4. CONTRIBUTIONS, REVENUE APPORTIONMENT &amp; RESOURCE ALLOCATION</strong></h2>
<p>Contributions and net commercial revenue apportionment strictly follow Schedule A.</p>

<h2><strong>5. JOINT GOVERNANCE &amp; COORDINATION COMMITTEE</strong></h2>
<p>Governed by a Joint Steering Committee composed of representatives from both Partners.</p>

<h2><strong>6. INTELLECTUAL PROPERTY &amp; BRAND RIGHTS</strong></h2>
<p>Pre-existing IP remains with the contributor; joint works are co-owned under separate licenses.</p>

<h2><strong>7. CONFIDENTIALITY &amp; NON-SOLICITATION COVENANTS</strong></h2>
<p>Protection of proprietary data under Republic Act No. 10173.</p>

<div class="crms-page-break"></div>

<h2><strong>8. DISSOLUTION &amp; TERMINATION OF PARTNERSHIP</strong></h2>
<p>Dissolution may be initiated upon sixty (60) days written notice.</p>

<h2><strong>9. GOVERNING LAW &amp; ARBITRATION</strong></h2>
<p>Binding arbitration in Metro Manila under Republic Act No. 9285.</p>

<h2><strong>10. EXECUTION &amp; FORMAL SIGNATURES</strong></h2>
<table class="crms-quill-table" style="width: 100%; border: 1px solid #cbd5e1; margin-top: 1.5rem; border-collapse: collapse;">
  <tbody>
    <tr>
      <th style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left; width: 50%;">FOR SBSI CORPORATION (FIRST PARTNER)</th>
      <th style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; text-align: left; width: 50%;">FOR SECOND PARTNER</th>
    </tr>
    <tr>
      <td style="padding: 36px 12px 14px; border: 1px solid #cbd5e1; vertical-align: bottom;">
        ____________________________________________<br />
        <strong>MANAGING DIRECTOR</strong><br />
        SBSI Corporation<br />
        Date: ________________________
      </td>
      <td style="padding: 36px 12px 14px; border: 1px solid #cbd5e1; vertical-align: bottom;">
        ____________________________________________<br />
        <strong>MANAGING DIRECTOR</strong><br />
        ${params.businessPartner}<br />
        Date: ________________________
      </td>
    </tr>
  </tbody>
</table>
`
    return {
      title: `${title} - ${params.businessPartner}`,
      summary: `Strategic Partnership Agreement between SBSI and ${params.businessPartner} for ${params.description}.`,
      html_content: html,
      source: 'client_fallback',
    }
  }

  return {
    isGenerating,
    error,
    result,
    generate,
  }
}
