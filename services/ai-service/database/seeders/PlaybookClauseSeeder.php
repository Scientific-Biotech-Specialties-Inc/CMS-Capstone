<?php

namespace Database\Seeders;

use App\Models\PlaybookClause;
use Illuminate\Database\Seeder;

/**
 * US-026: AI Risk Assessment — standard clause library.
 *
 * DRAFT content based on general contract-risk best practices plus SBSI's
 * known business (IVD/lab distribution, service/SLA agreements, LIS/BBIS/MIS
 * licensing — see SBSI_COMPANY_PROFILE.md). This is NOT legal advice and has
 * not been reviewed by SBSI's legal/compliance function. Revisit with a real
 * SBSI contract template once available — see
 * IMPLEMENTATION_PLAN_AI_RISK_VENDOR_ANALYTICS.md, Feature 1.
 */
class PlaybookClauseSeeder extends Seeder
{
    public function run(): void
    {
        $clauses = [
            [
                'clause_code' => 'PAY-01',
                'category' => 'Payment Terms',
                'title' => 'Standard Payment Terms',
                'standard_text' => 'Payment is due within 30 days of invoice date. Late payment interest, if applicable, is capped at a reasonable statutory or contractually agreed rate. Payment terms may not be unilaterally extended or altered by either party without prior written consent from the other party.',
            ],
            [
                'clause_code' => 'TERM-01',
                'category' => 'Termination',
                'title' => 'Termination for Breach / Convenience',
                'standard_text' => 'Either party may terminate this agreement for material breach by the other party, provided a 30-day cure period is given after written notice of the breach. Termination for convenience (without cause) requires at least 60 days\' prior written notice.',
            ],
            [
                'clause_code' => 'LIAB-01',
                'category' => 'Limitation of Liability',
                'title' => 'Liability Cap',
                'standard_text' => 'Each party\'s aggregate liability under this agreement is capped at the total fees paid or payable in the twelve (12) months preceding the claim. This cap does not apply to liability arising from gross negligence, willful misconduct, or breach of confidentiality obligations.',
            ],
            [
                'clause_code' => 'IND-01',
                'category' => 'Indemnification',
                'title' => 'Mutual Indemnification',
                'standard_text' => 'Each party shall indemnify the other against third-party claims arising from its own breach of this agreement, infringement of intellectual property rights, or negligent or willful acts. Indemnification obligations are mutual and not one-sided or uncapped.',
            ],
            [
                'clause_code' => 'WAR-01',
                'category' => 'Warranty',
                'title' => 'Equipment / Reagent Warranty',
                'standard_text' => 'The supplier warrants that all delivered goods (equipment, reagents, consumables) conform to the agreed specifications and hold any required regulatory approvals (e.g., FDA Philippines or equivalent clearance for in-vitro diagnostic products) for a stated warranty period. Defective goods are subject to replacement or repair at no additional cost within the warranty period.',
            ],
            [
                'clause_code' => 'REG-01',
                'category' => 'Regulatory Compliance',
                'title' => 'IVD / Import Regulatory Compliance',
                'standard_text' => 'The supplier represents that all imported in-vitro diagnostic (IVD) or diagnostic products hold valid FDA Philippines (or equivalent) registration and clearance, and that the supplier will maintain ongoing compliance with Philippine import and customs regulations for the duration of this agreement.',
            ],
            [
                'clause_code' => 'SLA-01',
                'category' => 'Service Level Agreement',
                'title' => 'Technical Support / Service Response Times',
                'standard_text' => 'The agreement defines specific response and resolution time commitments for technical support, maintenance, and calibration services. Remedies for missed service levels (e.g., service credits, escalation procedures) are clearly defined.',
            ],
            [
                'clause_code' => 'DATA-01',
                'category' => 'Data Handling & Confidentiality',
                'title' => 'Data Privacy & Confidentiality',
                'standard_text' => 'Confidentiality obligations apply to any patient, laboratory, or business data processed through information systems (e.g., LIS, BBIS, MIS). Data retention periods and breach-notification timelines are specified, and compliance with the Philippine Data Privacy Act (RA 10173) is referenced where personal data is processed.',
            ],
            [
                'clause_code' => 'IP-01',
                'category' => 'Intellectual Property',
                'title' => 'Background IP Ownership',
                'standard_text' => 'Each party retains ownership of its pre-existing intellectual property. Neither party\'s background IP is assigned or licensed to the other party absent an explicit clause stating otherwise.',
            ],
            [
                'clause_code' => 'RENEW-01',
                'category' => 'Renewal',
                'title' => 'Renewal & Auto-Renewal Notice Window',
                'standard_text' => 'Any auto-renewal clause must include a defined non-renewal notice window (typically 60-90 days before the renewal date) during which either party may elect not to renew. Silent or indefinite auto-renewal without a notice window is considered a deviation from this clause.',
            ],
            [
                'clause_code' => 'EXCL-01',
                'category' => 'Exclusivity',
                'title' => 'Exclusivity / Non-Compete Scope',
                'standard_text' => 'Any exclusivity or non-compete obligation is narrowly scoped in territory, product category, and duration, and does not unreasonably restrict SBSI\'s ability to source alternative suppliers or partners outside the specific exclusive relationship.',
            ],
            [
                'clause_code' => 'FORCE-01',
                'category' => 'Force Majeure',
                'title' => 'Force Majeure',
                'standard_text' => 'A standard force majeure clause is present, excusing performance delays caused by events beyond a party\'s reasonable control, and is applied mutually to both parties rather than favoring only one side.',
            ],
            [
                'clause_code' => 'DISP-01',
                'category' => 'Dispute Resolution',
                'title' => 'Governing Law & Venue',
                'standard_text' => 'The agreement specifies governing law and venue, preferably Philippine law and courts (or a mutually agreed arbitration body with a Philippine venue option). Foreign governing law with no Philippine venue option is considered a deviation from this clause.',
            ],
            [
                'clause_code' => 'ASSIGN-01',
                'category' => 'Assignment',
                'title' => 'Assignment & Subcontracting Consent',
                'standard_text' => 'Assignment of the agreement or subcontracting of obligations requires prior written consent from the other party. Unrestricted assignment rights granted to the counterparty without consent requirements are considered a deviation from this clause.',
            ],
        ];

        foreach ($clauses as $clause) {
            PlaybookClause::updateOrCreate(
                ['clause_code' => $clause['clause_code']],
                [
                    'title'         => $clause['title'],
                    'standard_text' => $clause['standard_text'],
                    'category'      => $clause['category'],
                    'is_active'     => true,
                ]
            );
        }
    }
}
