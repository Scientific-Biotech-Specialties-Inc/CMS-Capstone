<?php

namespace App\Http\Controllers;

use App\Services\Gemini\GeminiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ContractGenerationController extends Controller
{
    public function __construct(protected GeminiClient $geminiClient)
    {
    }

    /**
     * POST /api/contracts/generate
     * Generates a tailored corporate contract draft based on provided contract metadata.
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_partner' => 'required|string|max:255',
            'category'         => 'required|string|max:100',
            'item_code'        => 'required|string|max:50',
            'description'      => 'required|string|max:1000',
            'serial_number'    => 'required|string|max:50',
            'sbu_number'       => 'required|string|max:50',
            'region'           => 'required|string|max:50',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after:start_date',
            'prs_activity_id'  => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed for contract parameters.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $blueprint = $this->getCategoryBlueprint($data['category']);

        $systemInstruction = "You are a senior corporate legal counsel specializing in commercial procurement and contract drafting in the Philippines.
Your objective is to generate a formal commercial agreement between 'SBSI' ({$blueprint['party_one_label']}) and the designated Business Partner ({$blueprint['party_two_label']}).

STRUCTURAL BLUEPRINT FOR: '{$blueprint['name']}'
You MUST strictly follow this exact 3-page section sequence:
Page 1:
- Title: <h1><strong>{$blueprint['title']}</strong></h1> (Centered)
- Reference: <p><strong>CONTRACT NO. SBSI-{$data['sbu_number']}-{$data['item_code']}</strong></p> (Centered)
- Horizontal Rule: <hr />
- Preamble defining First Party and Second Party
- {$blueprint['sections']['p1_s1']}
- {$blueprint['sections']['p1_s2']} (MUST include a clean table with Deliverable/Goods/Equipment specifications: Item Code, Serial No, SBU, Region)
- {$blueprint['sections']['p1_s3']}
Page 1 Break: <div class=\"crms-page-break\"></div>

Page 2:
- {$blueprint['sections']['p2_s1']}
- {$blueprint['sections']['p2_s2']}
- {$blueprint['sections']['p2_s3']}
- {$blueprint['sections']['p2_s4']}
Page 2 Break: <div class=\"crms-page-break\"></div>

Page 3:
- {$blueprint['sections']['p3_s1']}
- {$blueprint['sections']['p3_s2']}
- {$blueprint['sections']['p3_s3']} (MUST include a clean 2-column signature table with First Party and Second Party attestation lines)

FORMAT & STYLING RESTRICTION:
- Absolutely DO NOT include inline style attributes with font-size, font-family, or background-color.
- Use only clean semantic HTML tags (h1, h2, p, table, tbody, tr, th, td, ol, ul, li, strong, em).
- Ensure formal Philippine legal phrasing referencing Republic Acts where applicable.";

        $userPrompt = "Please generate the official {$blueprint['title']} according to the structural blueprint:
- Second Party / Partner: {$data['business_partner']}
- Official Category: {$data['category']}
- Item / Service Code: {$data['item_code']}
- Description: {$data['description']}
- Serial Identification: {$data['serial_number']}
- Strategic Business Unit (SBU): {$data['sbu_number']}
- Region of Execution / Delivery: {$data['region']} (Philippines)
- Effective Date (Start Date): {$data['start_date']}
- Expiration Date (End Date): {$data['end_date']}
" . (!empty($data['prs_activity_id']) ? "- Associated PRS Activity ID: {$data['prs_activity_id']}\n" : "");

        $responseSchema = [
            'type' => 'OBJECT',
            'properties' => [
                'title' => [
                    'type' => 'STRING',
                    'description' => 'Official capitalized title of the agreement.',
                ],
                'summary' => [
                    'type' => 'STRING',
                    'description' => 'A brief 1-2 sentence executive summary of the agreement.',
                ],
                'html_content' => [
                    'type' => 'STRING',
                    'description' => 'Full structured contract text formatted in clean HTML without inline font sizes, divided into exactly 3 pages using <div class="crms-page-break"></div>.',
                ],
            ],
            'required' => ['title', 'summary', 'html_content'],
        ];

        try {
            $geminiResponse = $this->geminiClient->generateJson($systemInstruction, $userPrompt, $responseSchema);

            if ($geminiResponse && !empty($geminiResponse['html_content'])) {
                return response()->json([
                    'status' => 'success',
                    'source' => 'gemini',
                    'data'   => [
                        'title'        => $geminiResponse['title'] ?? ($blueprint['title'] . ' - ' . $data['business_partner']),
                        'summary'      => $geminiResponse['summary'] ?? '',
                        'html_content' => $geminiResponse['html_content'],
                        'metadata'     => $data,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Gemini contract generation encountered exception, using fallback generator.', [
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: Generate high quality category-specific legal template deterministically
        $fallback = $this->buildFallbackContract($data);

        return response()->json([
            'status' => 'success',
            'source' => 'template_engine',
            'data'   => [
                'title'        => $fallback['title'],
                'summary'      => $fallback['summary'],
                'html_content' => $fallback['html_content'],
                'metadata'     => $data,
            ],
        ]);
    }

    /**
     * Resolves the standardized legal blueprint based on category.
     */
    public function getCategoryBlueprint(string $category): array
    {
        $normalized = strtolower(trim($category));

        if (str_contains($normalized, 'supply')) {
            return [
                'key' => 'supply',
                'name' => 'Supply Contract',
                'title' => 'COMMERCIAL SUPPLY CONTRACT',
                'party_one_label' => 'First Party / Purchaser',
                'party_two_label' => 'Second Party / Supplier',
                'sections' => [
                    'p1_s1' => '1. RECITALS & ENGAGEMENT',
                    'p1_s2' => '2. GOODS SPECIFICATIONS & DELIVERY COMMITMENTS',
                    'p1_s3' => '3. CONTRACT TERM & SUPPLY TIMELINE',
                    'p2_s1' => '4. INSPECTION, ACCEPTANCE & QUALITY TESTING',
                    'p2_s2' => '5. PURCHASE PRICE, BILLING & PAYMENT TERMS',
                    'p2_s3' => '6. TITLE RETENTION, PACKAGING & RISK OF LOSS',
                    'p2_s4' => '7. PRODUCT WARRANTIES & REPLACEMENT REMEDIES',
                    'p3_s1' => '8. TERMINATION & DEFAULT',
                    'p3_s2' => '9. GOVERNING LAW & DISPUTE RESOLUTION',
                    'p3_s3' => '10. EXECUTION & FORMAL SIGNATURES',
                ],
            ];
        }

        if (str_contains($normalized, 'lease')) {
            return [
                'key' => 'lease',
                'name' => 'Equipment Lease',
                'title' => 'EQUIPMENT LEASE AGREEMENT',
                'party_one_label' => 'First Party / Lessee',
                'party_two_label' => 'Second Party / Lessor',
                'sections' => [
                    'p1_s1' => '1. RECITALS & LEASE DEMISE',
                    'p1_s2' => '2. EQUIPMENT IDENTIFICATION & SPECIFICATIONS',
                    'p1_s3' => '3. LEASE TERM & COMMENCEMENT',
                    'p2_s1' => '4. RENTAL CHARGES, SECURITY DEPOSIT & BILLING',
                    'p2_s2' => '5. TITLE RETENTION, OWNERSHIP & CARE OF PROPERTY',
                    'p2_s3' => '6. PERMITTED OPERATION, MAINTENANCE & REPAIRS',
                    'p2_s4' => '7. RETURN OF EQUIPMENT & SURRENDER CONDITION',
                    'p3_s1' => '8. DEFAULT, REPOSSESSION & REMEDIES',
                    'p3_s2' => '9. GOVERNING LAW & JURISDICTION',
                    'p3_s3' => '10. EXECUTION & FORMAL SIGNATURES',
                ],
            ];
        }

        if (str_contains($normalized, 'maintenance')) {
            return [
                'key' => 'maintenance',
                'name' => 'Equipment Maintenance',
                'title' => 'EQUIPMENT MAINTENANCE & SERVICING AGREEMENT',
                'party_one_label' => 'First Party / Client',
                'party_two_label' => 'Second Party / Maintenance Provider',
                'sections' => [
                    'p1_s1' => '1. RECITALS & APPOINTMENT',
                    'p1_s2' => '2. COVERED EQUIPMENT & SERVICE SPECIFICATIONS',
                    'p1_s3' => '3. AGREEMENT TERM & COVERAGE SCHEDULE',
                    'p2_s1' => '4. PREVENTIVE MAINTENANCE SCHEDULE & ROUTINE SERVICING',
                    'p2_s2' => '5. CORRECTIVE SERVICING & 24-HOUR EMERGENCY RESPONSE',
                    'p2_s3' => '6. SPARE PARTS AUTHENTICITY & WORKMANSHIP WARRANTIES',
                    'p2_s4' => '7. SERVICE COMPENSATION & INVOICING SCHEDULE',
                    'p3_s1' => '8. TERMINATION, DEFAULT & PENALTIES',
                    'p3_s2' => '9. GOVERNING LAW & DISPUTE RESOLUTION',
                    'p3_s3' => '10. EXECUTION & FORMAL SIGNATURES',
                ],
            ];
        }

        if (str_contains($normalized, 'partnership')) {
            return [
                'key' => 'partnership',
                'name' => 'Partnership Agreement',
                'title' => 'STRATEGIC PARTNERSHIP & COLLABORATION AGREEMENT',
                'party_one_label' => 'First Party / First Partner',
                'party_two_label' => 'Second Party / Second Partner',
                'sections' => [
                    'p1_s1' => '1. RECITALS & MUTUAL PURPOSE',
                    'p1_s2' => '2. STRATEGIC SCOPE & ALLOCATION OF RESPONSIBILITIES',
                    'p1_s3' => '3. PARTNERSHIP DURATION & TIMELINE',
                    'p2_s1' => '4. CONTRIBUTIONS, REVENUE APPORTIONMENT & RESOURCE ALLOCATION',
                    'p2_s2' => '5. JOINT GOVERNANCE & COORDINATION COMMITTEE',
                    'p2_s3' => '6. INTELLECTUAL PROPERTY & BRAND RIGHTS',
                    'p2_s4' => '7. CONFIDENTIALITY & NON-SOLICITATION COVENANTS',
                    'p3_s1' => '8. DISSOLUTION & TERMINATION OF PARTNERSHIP',
                    'p3_s2' => '9. GOVERNING LAW & ARBITRATION',
                    'p3_s3' => '10. EXECUTION & FORMAL SIGNATURES',
                ],
            ];
        }

        // Default: Service Agreement
        return [
            'key' => 'service',
            'name' => 'Service Agreement',
            'title' => 'COMMERCIAL SERVICES AGREEMENT',
            'party_one_label' => 'First Party / Client',
            'party_two_label' => 'Second Party / Contractor',
            'sections' => [
                'p1_s1' => '1. RECITALS & APPOINTMENT',
                'p1_s2' => '2. SCOPE OF SERVICES & TECHNICAL SPECIFICATIONS',
                'p1_s3' => '3. CONTRACT TERM & DURATION',
                'p2_s1' => '4. SERVICE LEVEL AGREEMENT (SLA) & PERFORMANCE STANDARDS',
                'p2_s2' => '5. COMPENSATION, BILLING & PAYMENT SCHEDULE',
                'p2_s3' => '6. CONFIDENTIALITY & DATA PRIVACY (RA 10173)',
                'p2_s4' => '7. TERMINATION & DEFAULT REMEDIES',
                'p3_s1' => '8. GOVERNING LAW & DISPUTE RESOLUTION',
                'p3_s2' => '9. GENERAL PROVISIONS',
                'p3_s3' => '10. EXECUTION & FORMAL SIGNATURES',
            ],
        ];
    }

    /**
     * Fallback template builder producing a complete, formatted Philippine legal contract
     * tailored to the contract category.
     */
    protected function buildFallbackContract(array $data): array
    {
        $blueprint = $this->getCategoryBlueprint($data['category']);
        $partner   = htmlspecialchars($data['business_partner']);
        $itemCode  = htmlspecialchars($data['item_code']);
        $desc      = htmlspecialchars($data['description']);
        $serialNo  = htmlspecialchars($data['serial_number']);
        $sbuNo     = htmlspecialchars($data['sbu_number']);
        $region    = htmlspecialchars($data['region']);
        $startDate = htmlspecialchars($data['start_date']);
        $endDate   = htmlspecialchars($data['end_date']);

        switch ($blueprint['key']) {
            case 'supply':
                $html = $this->buildSupplyContractHtml($partner, $itemCode, $desc, $serialNo, $sbuNo, $region, $startDate, $endDate, $blueprint);
                break;
            case 'lease':
                $html = $this->buildEquipmentLeaseHtml($partner, $itemCode, $desc, $serialNo, $sbuNo, $region, $startDate, $endDate, $blueprint);
                break;
            case 'maintenance':
                $html = $this->buildEquipmentMaintenanceHtml($partner, $itemCode, $desc, $serialNo, $sbuNo, $region, $startDate, $endDate, $blueprint);
                break;
            case 'partnership':
                $html = $this->buildPartnershipAgreementHtml($partner, $itemCode, $desc, $serialNo, $sbuNo, $region, $startDate, $endDate, $blueprint);
                break;
            default:
                $html = $this->buildServiceAgreementHtml($partner, $itemCode, $desc, $serialNo, $sbuNo, $region, $startDate, $endDate, $blueprint);
                break;
        }

        return [
            'title'        => "{$blueprint['title']} - {$data['business_partner']}",
            'summary'      => "Formal {$blueprint['name']} for {$data['business_partner']} regarding {$data['description']} in {$data['region']}.",
            'html_content' => $html,
        ];
    }

    protected function buildServiceAgreementHtml($partner, $itemCode, $desc, $serialNo, $sbuNo, $region, $startDate, $endDate, $bp): string
    {
        return <<<HTML
<h1 style="text-align: center;"><strong>{$bp['title']}</strong></h1>
<p style="text-align: center; color: #555555;"><strong>CONTRACT NO. SBSI-{$sbuNo}-{$itemCode}</strong></p>
<hr />
<p>This <strong>{$bp['name']}</strong> (the &ldquo;Agreement&rdquo;) is entered into and made effective as of <strong>{$startDate}</strong>, by and between:</p>

<p><strong>SBSI CORPORATION</strong>, a corporation duly organized and existing under the laws of the Republic of the Philippines, having its principal business address in Metro Manila (hereinafter referred to as the &ldquo;First Party&rdquo; or &ldquo;Client&rdquo;);</p>

<p style="text-align: center;">- and -</p>

<p><strong>{$partner}</strong>, an authorized business entity operating in <strong>{$region}</strong>, Philippines (hereinafter referred to as the &ldquo;Second Party&rdquo; or &ldquo;Contractor&rdquo;).</p>

<p>The Client and Contractor may collectively be referred to as the &ldquo;Parties&rdquo; and individually as a &ldquo;Party&rdquo;.</p>

<h2><strong>1. RECITALS &amp; APPOINTMENT</strong></h2>
<p><strong>WHEREAS</strong>, the Client requires specialized technical engineering, deployment, and operational services for its Strategic Business Unit <strong>{$sbuNo}</strong>;</p>
<p><strong>WHEREAS</strong>, the Contractor possesses the professional qualifications, equipment, certified personnel, and technical resources to deliver the required services in compliance with industry benchmarks;</p>
<p><strong>NOW, THEREFORE</strong>, the Parties mutually agree to the terms set forth below:</p>

<h2><strong>2. SCOPE OF SERVICES &amp; TECHNICAL SPECIFICATIONS</strong></h2>
<p>The Contractor shall provide, execute, and maintain the following deliverables in accordance with approved milestone parameters:</p>
<table class="crms-quill-table" style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; margin-bottom: 0.75rem;">
  <tbody>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold; width: 35%;">Service Deliverable</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$desc}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Standard Service Code</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$itemCode}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Serial Identification</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$serialNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Allocated Strategic Unit</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">SBU {$sbuNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Operational Region</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$region}, Philippines</td>
    </tr>
  </tbody>
</table>

<h2><strong>3. CONTRACT TERM &amp; DURATION</strong></h2>
<p>This Agreement shall commence on <strong>{$startDate}</strong> (the &ldquo;Effective Date&rdquo;) and shall remain in full force and effect until <strong>{$endDate}</strong>, unless earlier terminated in accordance with Section 7.</p>

<div class="crms-page-break"></div>

<h2><strong>4. SERVICE LEVEL AGREEMENT (SLA) &amp; PERFORMANCE STANDARDS</strong></h2>
<ol>
  <li>The Contractor guarantees that all services meet standard commercial performance benchmarks, ensuring minimum ninety-nine and nine-tenths percent (99.9%) system availability.</li>
  <li>In the event of an operational defect or discrepancy under Item Code <strong>{$itemCode}</strong>, Contractor shall initiate remedial action within twenty-four (24) business hours upon formal notice.</li>
  <li>All deliverables are subject to formal verification, testing, and acceptance sign-off by the Client's authorized representative.</li>
</ol>

<h2><strong>5. COMPENSATION, BILLING &amp; PAYMENT SCHEDULE</strong></h2>
<p>In consideration of the satisfactory completion of deliverables, the Client shall compensate the Contractor according to approved milestone progress billings. All invoices must be accompanied by verified work completion receipts and shall be subject to standard withholding taxes under the Philippine National Internal Revenue Code.</p>

<h2><strong>6. CONFIDENTIALITY &amp; DATA PRIVACY (RA 10173)</strong></h2>
<p>Both Parties shall protect proprietary trade secrets, customer records, and operational databases in strict compliance with Republic Act No. 10173 (Philippine Data Privacy Act of 2012). This obligation of non-disclosure survives the termination of this Agreement for five (5) consecutive years.</p>

<h2><strong>7. TERMINATION &amp; DEFAULT REMEDIES</strong></h2>
<p>Either Party may terminate this Agreement upon thirty (30) days prior written notice in the event of default or uncured breach by the other Party, provided such breach remains unrectified during the cure period.</p>

<div class="crms-page-break"></div>

<h2><strong>8. GOVERNING LAW &amp; DISPUTE RESOLUTION</strong></h2>
<p>This Agreement shall be governed by and construed in accordance with the laws of the Republic of the Philippines. Any legal action or dispute arising from this Agreement shall be brought exclusively before the proper courts of Metro Manila, Philippines.</p>

<h2><strong>9. GENERAL PROVISIONS</strong></h2>
<p>This Agreement represents the entire understanding between the Parties regarding the subject matter and supersedes all prior negotiations or covenants. Any amendment shall only be effective if executed in writing and signed by both Parties.</p>

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
        {$partner}<br />
        Date: ________________________
      </td>
    </tr>
  </tbody>
</table>
HTML;
    }

    protected function buildSupplyContractHtml($partner, $itemCode, $desc, $serialNo, $sbuNo, $region, $startDate, $endDate, $bp): string
    {
        return <<<HTML
<h1 style="text-align: center;"><strong>{$bp['title']}</strong></h1>
<p style="text-align: center; color: #555555;"><strong>CONTRACT NO. SBSI-{$sbuNo}-{$itemCode}</strong></p>
<hr />
<p>This <strong>{$bp['name']}</strong> (the &ldquo;Agreement&rdquo;) is entered into and made effective as of <strong>{$startDate}</strong>, by and between:</p>

<p><strong>SBSI CORPORATION</strong>, a corporation duly organized and existing under the laws of the Republic of the Philippines, having its principal office in Metro Manila (hereinafter referred to as the &ldquo;First Party&rdquo; or &ldquo;Purchaser&rdquo;);</p>

<p style="text-align: center;">- and -</p>

<p><strong>{$partner}</strong>, an authorized manufacturer and distributor operating in <strong>{$region}</strong>, Philippines (hereinafter referred to as the &ldquo;Second Party&rdquo; or &ldquo;Supplier&rdquo;).</p>

<p>The Purchaser and Supplier may collectively be referred to as the &ldquo;Parties&rdquo; and individually as a &ldquo;Party&rdquo;.</p>

<h2><strong>1. RECITALS &amp; ENGAGEMENT</strong></h2>
<p><strong>WHEREAS</strong>, the Purchaser requires reliable commercial supply and timely fulfillment of specialized goods for Strategic Business Unit <strong>{$sbuNo}</strong>;</p>
<p><strong>WHEREAS</strong>, the Supplier has represented that it manufactures, holds inventory, and possesses distribution capacity to supply said items in full accordance with commercial standards;</p>
<p><strong>NOW, THEREFORE</strong>, the Parties mutually agree to the terms set forth below:</p>

<h2><strong>2. GOODS SPECIFICATIONS &amp; DELIVERY COMMITMENTS</strong></h2>
<p>The Supplier agrees to deliver the following designated goods conforming strictly to manufacturer standards:</p>
<table class="crms-quill-table" style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; margin-bottom: 0.75rem;">
  <tbody>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold; width: 35%;">Designated Goods Description</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$desc}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Inventory / SKU Code</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$itemCode}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Batch / Serial Identifier</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$serialNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Consignee Strategic Unit</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">SBU {$sbuNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Designated Delivery Region</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$region}, Philippines</td>
    </tr>
  </tbody>
</table>

<h2><strong>3. CONTRACT TERM &amp; SUPPLY TIMELINE</strong></h2>
<p>This Supply Contract takes effect on <strong>{$startDate}</strong> and remains in effect until <strong>{$endDate}</strong>, covering all designated purchase orders issued by Purchaser within this duration.</p>

<div class="crms-page-break"></div>

<h2><strong>4. INSPECTION, ACCEPTANCE &amp; QUALITY TESTING</strong></h2>
<ol>
  <li>The Purchaser reserves the right to inspect all delivered goods upon arrival at the designated warehouse or deployment site in {$region}.</li>
  <li>Non-conforming, damaged, or expired items under Item Code <strong>{$itemCode}</strong> shall be rejected and must be replaced by Supplier within seven (7) business days at Supplier's sole cost.</li>
  <li>Acceptance shall be formalized only upon written endorsement of the Receiving Inspection Report (RIR) by Purchaser's quality auditor.</li>
</ol>

<h2><strong>5. PURCHASE PRICE, BILLING &amp; PAYMENT TERMS</strong></h2>
<p>Prices agreed upon shall remain fixed for the duration of this Agreement. Invoices must be submitted with accompanying signed delivery receipts and shall be paid within thirty (30) days from verified receipt, less applicable statutory withholding taxes.</p>

<h2><strong>6. TITLE RETENTION, PACKAGING &amp; RISK OF LOSS</strong></h2>
<p>All items shall be delivered F.O.B. Destination, properly packaged against moisture and transit hazards. Risk of loss and title to the goods shall pass to the Purchaser only upon formal physical acceptance at the designated site.</p>

<h2><strong>7. PRODUCT WARRANTIES &amp; REPLACEMENT REMEDIES</strong></h2>
<p>The Supplier warrants that all goods supplied are genuine, free from liens, encumbrances, and manufacturing defects for a minimum warranty period of twelve (12) months from formal acceptance.</p>

<div class="crms-page-break"></div>

<h2><strong>8. TERMINATION &amp; DEFAULT</strong></h2>
<p>Failure by the Supplier to deliver goods within agreed timelines constitutes material breach, entitling Purchaser to cancel orders and source from alternate vendors at Supplier's expense.</p>

<h2><strong>9. GOVERNING LAW &amp; DISPUTE RESOLUTION</strong></h2>
<p>This Supply Contract shall be governed by the laws of the Republic of the Philippines. Disputes shall be settled through amicable mediation, failing which actions shall be lodged in Metro Manila courts.</p>

<h2><strong>10. EXECUTION &amp; FORMAL SIGNATURES</strong></h2>
<p>IN WITNESS WHEREOF, the Parties have executed this Supply Contract as of the date first above written.</p>

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
        {$partner}<br />
        Date: ________________________
      </td>
    </tr>
  </tbody>
</table>
HTML;
    }

    protected function buildEquipmentLeaseHtml($partner, $itemCode, $desc, $serialNo, $sbuNo, $region, $startDate, $endDate, $bp): string
    {
        return <<<HTML
<h1 style="text-align: center;"><strong>{$bp['title']}</strong></h1>
<p style="text-align: center; color: #555555;"><strong>CONTRACT NO. SBSI-{$sbuNo}-{$itemCode}</strong></p>
<hr />
<p>This <strong>{$bp['name']}</strong> (the &ldquo;Lease&rdquo;) is entered into and made effective as of <strong>{$startDate}</strong>, by and between:</p>

<p><strong>SBSI CORPORATION</strong>, a corporate entity having its principal office in Metro Manila, Philippines (hereinafter referred to as the &ldquo;First Party&rdquo; or &ldquo;Lessee&rdquo;);</p>

<p style="text-align: center;">- and -</p>

<p><strong>{$partner}</strong>, an authorized equipment lessor operating in <strong>{$region}</strong>, Philippines (hereinafter referred to as the &ldquo;Second Party&rdquo; or &ldquo;Lessor&rdquo;).</p>

<p>The Lessee and Lessor may collectively be referred to as the &ldquo;Parties&rdquo; and individually as a &ldquo;Party&rdquo;.</p>

<h2><strong>1. RECITALS &amp; LEASE DEMISE</strong></h2>
<p><strong>WHEREAS</strong>, the Lessor owns and has the lawful right to lease the designated industrial and technical equipment described herein;</p>
<p><strong>WHEREAS</strong>, the Lessee desires to lease said equipment for operations within Strategic Business Unit <strong>{$sbuNo}</strong> under the terms set forth below;</p>
<p><strong>NOW, THEREFORE</strong>, the Parties mutually agree as follows:</p>

<h2><strong>2. EQUIPMENT IDENTIFICATION &amp; SPECIFICATIONS</strong></h2>
<p>The Lessor hereby leases to the Lessee the following specific machinery and apparatus:</p>
<table class="crms-quill-table" style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; margin-bottom: 0.75rem;">
  <tbody>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold; width: 35%;">Leased Equipment Description</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$desc}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Asset Item Code</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$itemCode}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Chassis / Serial Identifier</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$serialNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Assigned Operational Unit</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">SBU {$sbuNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Authorised Location</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$region}, Philippines</td>
    </tr>
  </tbody>
</table>

<h2><strong>3. LEASE TERM &amp; COMMENCEMENT</strong></h2>
<p>The lease period takes effect on <strong>{$startDate}</strong> and terminates on <strong>{$endDate}</strong>, unless renewed by mutual written instrument executed prior to expiration.</p>

<div class="crms-page-break"></div>

<h2><strong>4. RENTAL CHARGES, SECURITY DEPOSIT &amp; BILLING</strong></h2>
<ol>
  <li>The Lessee shall pay agreed periodic lease rental fees subject to receipt of formal billing and statutory withholding tax deduction.</li>
  <li>Security deposits shall be applied to any outstanding lease charges or refunded upon surrender of equipment in good working order.</li>
</ol>

<h2><strong>5. TITLE RETENTION, OWNERSHIP &amp; CARE OF PROPERTY</strong></h2>
<p>The equipment shall at all times remain the sole personal property of the Lessor. The Lessee shall not mortgage, pledge, sublease, or encumber the equipment or permit any lien to attach thereto.</p>

<h2><strong>6. PERMITTED OPERATION, MAINTENANCE &amp; REPAIRS</strong></h2>
<p>The Lessee shall ensure that the equipment is operated solely by qualified, trained personnel strictly in accordance with manufacturer operating handbooks. Routine consumable maintenance shall be observed diligently.</p>

<h2><strong>7. RETURN OF EQUIPMENT &amp; SURRENDER CONDITION</strong></h2>
<p>Upon expiration or termination, Lessee shall return the equipment to Lessor in {$region} in identical operating condition as delivered, ordinary wear and tear from normal usage excepted.</p>

<div class="crms-page-break"></div>

<h2><strong>8. DEFAULT, REPOSSESSION &amp; REMEDIES</strong></h2>
<p>In the event of uncured default in rental payments, the Lessor may immediately terminate this Lease and repossess the equipment without court intervention, reserving all claims for damages.</p>

<h2><strong>9. GOVERNING LAW &amp; JURISDICTION</strong></h2>
<p>This Equipment Lease Agreement is governed by Philippine civil and commercial law. Any dispute shall be submitted to the competent courts of Metro Manila, Philippines.</p>

<h2><strong>10. EXECUTION &amp; FORMAL SIGNATURES</strong></h2>
<p>IN WITNESS WHEREOF, the Parties have caused this Equipment Lease Agreement to be duly signed.</p>

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
        {$partner}<br />
        Date: ________________________
      </td>
    </tr>
  </tbody>
</table>
HTML;
    }

    protected function buildEquipmentMaintenanceHtml($partner, $itemCode, $desc, $serialNo, $sbuNo, $region, $startDate, $endDate, $bp): string
    {
        return <<<HTML
<h1 style="text-align: center;"><strong>{$bp['title']}</strong></h1>
<p style="text-align: center; color: #555555;"><strong>CONTRACT NO. SBSI-{$sbuNo}-{$itemCode}</strong></p>
<hr />
<p>This <strong>{$bp['name']}</strong> (the &ldquo;Maintenance Agreement&rdquo;) is entered into and made effective as of <strong>{$startDate}</strong>, by and between:</p>

<p><strong>SBSI CORPORATION</strong>, a corporate entity having its principal office in Metro Manila, Philippines (hereinafter referred to as the &ldquo;First Party&rdquo; or &ldquo;Client&rdquo;);</p>

<p style="text-align: center;">- and -</p>

<p><strong>{$partner}</strong>, a licensed technical servicing provider operating in <strong>{$region}</strong>, Philippines (hereinafter referred to as the &ldquo;Second Party&rdquo; or &ldquo;Maintenance Provider&rdquo;).</p>

<p>The Client and Maintenance Provider may collectively be referred to as the &ldquo;Parties&rdquo; and individually as a &ldquo;Party&rdquo;.</p>

<h2><strong>1. RECITALS &amp; APPOINTMENT</strong></h2>
<p><strong>WHEREAS</strong>, the Client owns and operates technical equipment requiring structured preventive maintenance and prompt emergency repair services;</p>
<p><strong>WHEREAS</strong>, the Maintenance Provider employs certified technical specialists and maintains parts logistics to provide comprehensive maintenance support;</p>
<p><strong>NOW, THEREFORE</strong>, the Parties mutually agree to the terms set forth below:</p>

<h2><strong>2. COVERED EQUIPMENT &amp; SERVICE SPECIFICATIONS</strong></h2>
<p>The Maintenance Provider shall service the following covered machinery:</p>
<table class="crms-quill-table" style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; margin-bottom: 0.75rem;">
  <tbody>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold; width: 35%;">Covered Equipment</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$desc}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Asset Item Code</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$itemCode}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Serial Identification</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$serialNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Assigned Strategic Unit</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">SBU {$sbuNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Servicing Region</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$region}, Philippines</td>
    </tr>
  </tbody>
</table>

<h2><strong>3. AGREEMENT TERM &amp; COVERAGE SCHEDULE</strong></h2>
<p>This Maintenance Agreement takes effect on <strong>{$startDate}</strong> and remains active until <strong>{$endDate}</strong>, unless earlier terminated in accordance with Section 8.</p>

<div class="crms-page-break"></div>

<h2><strong>4. PREVENTIVE MAINTENANCE SCHEDULE &amp; ROUTINE SERVICING</strong></h2>
<ol>
  <li>Maintenance Provider shall conduct mandatory preventive maintenance routines on a structured quarterly basis with formal service certification.</li>
  <li>All routine maintenance tasks (diagnostics, calibration, lubrication, firmware verification) shall be documented in on-site maintenance logs.</li>
</ol>

<h2><strong>5. CORRECTIVE SERVICING &amp; 24-HOUR EMERGENCY RESPONSE</strong></h2>
<p>Upon notification of unscheduled equipment failure or operational disruption, Maintenance Provider guarantees on-site technical response within twenty-four (24) business hours in {$region}. Failure to respond within agreed SLAs shall subject Provider to agreed liquidated damages.</p>

<h2><strong>6. SPARE PARTS AUTHENTICITY &amp; WORKMANSHIP WARRANTIES</strong></h2>
<p>All replacement parts furnished shall be brand new, genuine manufacturer components. All corrective servicing labor carries a minimum ninety (90) day warranty against workmanship defects.</p>

<h2><strong>7. SERVICE COMPENSATION &amp; INVOICING SCHEDULE</strong></h2>
<p>Client shall pay agreed preventive maintenance retainers and approved parts replacement costs upon verified service sign-off, subject to Philippine withholding taxes.</p>

<div class="crms-page-break"></div>

<h2><strong>8. TERMINATION, DEFAULT &amp; PENALTIES</strong></h2>
<p>Repeated SLA breaches or recurring equipment failures due to servicing negligence entitle Client to terminate this Agreement immediately with thirty (30) days prior written notice.</p>

<h2><strong>9. GOVERNING LAW &amp; DISPUTE RESOLUTION</strong></h2>
<p>This Agreement shall be construed in accordance with Philippine law. Legal actions shall be brought before the competent courts of Metro Manila, Philippines.</p>

<h2><strong>10. EXECUTION &amp; FORMAL SIGNATURES</strong></h2>
<p>IN WITNESS WHEREOF, the Parties have caused this Equipment Maintenance Agreement to be duly executed.</p>

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
        {$partner}<br />
        Date: ________________________
      </td>
    </tr>
  </tbody>
</table>
HTML;
    }

    protected function buildPartnershipAgreementHtml($partner, $itemCode, $desc, $serialNo, $sbuNo, $region, $startDate, $endDate, $bp): string
    {
        return <<<HTML
<h1 style="text-align: center;"><strong>{$bp['title']}</strong></h1>
<p style="text-align: center; color: #555555;"><strong>CONTRACT NO. SBSI-{$sbuNo}-{$itemCode}</strong></p>
<hr />
<p>This <strong>{$bp['name']}</strong> is entered into and made effective as of <strong>{$startDate}</strong>, by and between:</p>

<p><strong>SBSI CORPORATION</strong>, a corporate entity having its principal office in Metro Manila, Philippines (hereinafter referred to as the &ldquo;First Party&rdquo; or &ldquo;First Partner&rdquo;);</p>

<p style="text-align: center;">- and -</p>

<p><strong>{$partner}</strong>, a business enterprise operating in <strong>{$region}</strong>, Philippines (hereinafter referred to as the &ldquo;Second Party&rdquo; or &ldquo;Second Partner&rdquo;).</p>

<p>The First Partner and Second Partner may collectively be referred to as the &ldquo;Parties&rdquo; and individually as a &ldquo;Party&rdquo;.</p>

<h2><strong>1. RECITALS &amp; MUTUAL PURPOSE</strong></h2>
<p><strong>WHEREAS</strong>, the Parties desire to collaborate in the development, distribution, and commercialization of specialized technical deliverables;</p>
<p><strong>WHEREAS</strong>, both Parties intend to combine strategic unit capabilities and market reach within Strategic Business Unit <strong>{$sbuNo}</strong>;</p>
<p><strong>NOW, THEREFORE</strong>, the Parties agree as follows:</p>

<h2><strong>2. STRATEGIC SCOPE &amp; ALLOCATION OF RESPONSIBILITIES</strong></h2>
<p>The Parties commit to execute the collaborative program according to the following specifications:</p>
<table class="crms-quill-table" style="width: 100%; border-collapse: collapse; margin-top: 0.5rem; margin-bottom: 0.75rem;">
  <tbody>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold; width: 35%;">Partnership Deliverable / Focus</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$desc}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Project Reference Code</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$itemCode}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Serial Identification</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$serialNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Allocated Strategic Unit</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">SBU {$sbuNo}</td>
    </tr>
    <tr>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1; background: #f8fafc; font-weight: bold;">Operating Region</td>
      <td style="padding: 6px 10px; border: 1px solid #cbd5e1;">{$region}, Philippines</td>
    </tr>
  </tbody>
</table>

<h2><strong>3. PARTNERSHIP DURATION &amp; TIMELINE</strong></h2>
<p>This Partnership takes effect on <strong>{$startDate}</strong> and terminates on <strong>{$endDate}</strong>, unless earlier dissolved or extended by mutual written consent.</p>

<div class="crms-page-break"></div>

<h2><strong>4. CONTRIBUTIONS, REVENUE APPORTIONMENT &amp; RESOURCE ALLOCATION</strong></h2>
<ol>
  <li>Each Partner shall contribute capital, personnel, and technical resources as designated in Schedule A.</li>
  <li>Net commercial revenues generated from joint initiatives shall be apportioned strictly in accordance with written commercial annexes.</li>
</ol>

<h2><strong>5. JOINT GOVERNANCE &amp; COORDINATION COMMITTEE</strong></h2>
<p>A Joint Steering Committee comprising two designated representatives from each Partner shall govern strategic milestones, quarterly reviews, and operational approvals.</p>

<h2><strong>6. INTELLECTUAL PROPERTY &amp; BRAND RIGHTS</strong></h2>
<p>Pre-existing intellectual property remains the sole property of the contributing Partner. Jointly developed technology or work products shall be co-owned under separate licensing protocols.</p>

<h2><strong>7. CONFIDENTIALITY &amp; NON-SOLICITATION COVENANTS</strong></h2>
<p>Both Partners shall protect proprietary business strategies, financial models, and customer data in compliance with Republic Act No. 10173 (Philippine Data Privacy Act of 2012).</p>

<div class="crms-page-break"></div>

<h2><strong>8. DISSOLUTION &amp; TERMINATION OF PARTNERSHIP</strong></h2>
<p>Either Partner may initiate dissolution upon sixty (60) days written notice. Accounts and deliverables shall be reconciled equitably upon dissolution.</p>

<h2><strong>9. GOVERNING LAW &amp; ARBITRATION</strong></h2>
<p>This Partnership Agreement is governed by Philippine law. Any dispute shall be resolved through binding arbitration in Metro Manila under Republic Act No. 9285 (Alternative Dispute Resolution Act of 2004).</p>

<h2><strong>10. EXECUTION &amp; FORMAL SIGNATURES</strong></h2>
<p>IN WITNESS WHEREOF, the Partners have executed this Strategic Partnership Agreement.</p>

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
        {$partner}<br />
        Date: ________________________
      </td>
    </tr>
  </tbody>
</table>
HTML;
    }
}
