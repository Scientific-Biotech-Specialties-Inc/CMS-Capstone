export type SceneSpec = {
  id: string;
  compositionId: string;
  number: string;
  chapter: string;
  title: string;
  narration: string;
  estimatedSeconds: number;
};

export const DEMO_SCENES: SceneSpec[] = [
  {
    id: 'scene-01-intro',
    compositionId: 'Scene01Intro',
    number: '01',
    chapter: 'SBSI Platform',
    title: 'Brand Intro & Architecture',
    narration:
      'Scientific Biotech Specialties, Inc. powers diagnostic and industrial laboratories nationwide. Meet the SBSI Contract Management System — a unified, microservices-backed platform built to manage every vendor, contract, risk check, and approval from one workspace.',
    estimatedSeconds: 20,
  },
  {
    id: 'scene-02-auth-rbac',
    compositionId: 'Scene02AuthRbac',
    number: '02',
    chapter: 'Access Control',
    title: 'Central Auth, RBAC & Portals',
    narration:
      "Access starts through SBSI's shared authentication gateway. Role-based isolation automatically routes Admins, Managers, and Sales staff to their dedicated portals — with granular permission controls across users and roles.",
    estimatedSeconds: 22,
  },
  {
    id: 'scene-03-analytics',
    compositionId: 'Scene03Analytics',
    number: '03',
    chapter: 'Live Insights',
    title: 'Dashboards & Analytics',
    narration:
      'Every role opens to live analytics: active contracts, regional distribution across Luzon, Visayas, and Mindanao, renewal pipelines, workflow bottlenecks, and real-time SERMS burn rate and net margin tracking.',
    estimatedSeconds: 26,
  },
  {
    id: 'scene-04-vendors',
    compositionId: 'Scene04Vendors',
    number: '04',
    chapter: 'Partner Hub',
    title: 'Vendor & Partner Management',
    narration:
      'Onboarding suppliers and business partners is fast and structured. Manage branches, class tiers, encrypted contact records, linked contracts, and synced reimbursement history in a single partner profile.',
    estimatedSeconds: 24,
  },
  {
    id: 'scene-05-ai-vendors',
    compositionId: 'Scene05AiVendors',
    number: '05',
    chapter: 'AI Discovery',
    title: 'AI Vendor Suggestions & SERMS Discovery',
    narration:
      'Need new suppliers? AI Vendor Suggestions uses Gemini and pgvector to recommend Philippine diagnostic and QA partners tailored to SBSI — alongside verified merchants forwarded from SERMS. Select, review, autofill, and onboard in clicks.',
    estimatedSeconds: 26,
  },
  {
    id: 'scene-06-create-contract',
    compositionId: 'Scene06CreateContract',
    number: '06',
    chapter: 'Contract Authoring',
    title: 'Creating Contracts (Manual, OCR, AI Editor)',
    narration:
      'Create contracts three ways: enter structured terms manually with malware-scanned document uploads, drop any PDF, DOCX, or scan into OCR Import to autofill fields instantly, or launch the AI Contract Generator to draft, format margins and headers, and attach a print-ready PDF right inside the editor.',
    estimatedSeconds: 34,
  },
  {
    id: 'scene-07-ai-risk',
    compositionId: 'Scene07AiRisk',
    number: '07',
    chapter: 'Risk Intelligence',
    title: 'AI Risk Assessment (RAG Playbook)',
    narration:
      "Every uploaded contract can be scanned by AI Risk Assessment. Using RAG over SBSI's 14-clause playbook — from FDA compliance to Data Privacy Act RA 10173 — it flags Low to Critical deviations with exact citations, plain-language reasons, remediation advice, and one-click PDF export.",
    estimatedSeconds: 28,
  },
  {
    id: 'scene-08-workflows',
    compositionId: 'Scene08Workflows',
    number: '08',
    chapter: 'Workflow Engine',
    title: 'Approval & Execution Workflow Builder',
    narration:
      'Governance stays flexible per contract type. First, Admins configure Internal Approval steps — sequential or parallel — assigning the exact roles that must sign off before execution. Next, in Execution Reviewers, they assign who confirms each post-approval stage: SBSI Review, Client Review, For Signing — restricted to Manager or CEO — and optional Notarized PDF.',
    estimatedSeconds: 30,
  },
  {
    id: 'scene-09-approval',
    compositionId: 'Scene09Approval',
    number: '09',
    chapter: 'Approval Gate',
    title: 'Contract Requests & Internal Approval',
    narration:
      'Every submission flows into Contract Requests — including recurring expenses escalated from SERMS. Assigned reviewers check the draft and AI risk flags, then record their decision in Your Internal Approval Tasks: approve to advance the chain, or reject with a mandatory reason. High-risk contracts stay gated until final internal approval clears — unlocking execution.',
    estimatedSeconds: 28,
  },
  {
    id: 'scene-10-execution-signing',
    compositionId: 'Scene10ExecutionSigning',
    number: '10',
    chapter: 'Execution & E-Sign',
    title: 'Acceptance to Signature & Amendments',
    narration:
      'Once internal approval finishes, the Execution Workflow takes over. The owner submits SBSI Review completion, then Client Acceptance; an eligible reviewer confirms each step — or returns it for revision, which pauses the flow and restarts internal approval if the draft changes. At For Signing, the owner either uploads a scanned signed PDF or launches In-App E-Signing to place a signature directly onto the approved draft. A Manager or CEO confirms to mark it Signed, with optional Notarized PDF upload, full Execution History, and post-signing Contract Amendments.',
    estimatedSeconds: 34,
  },
  {
    id: 'scene-11-ops',
    compositionId: 'Scene11Ops',
    number: '11',
    chapter: 'Platform Ops',
    title: 'Search, Notifications, Audit Log & Config',
    narration:
      'Find any record instantaneously with Meilisearch global search. Stay ahead of expirations with real-time notifications and email logs, maintain full accountability in the cross-service audit log, and tune contract rules in System Config.',
    estimatedSeconds: 22,
  },
  {
    id: 'scene-12-ecosystem',
    compositionId: 'Scene12Ecosystem',
    number: '12',
    chapter: 'Connected Suite',
    title: 'SBSI Ecosystem Integration',
    narration:
      'CMS connects the entire SBSI suite: syncing burn rates and vendors with SERMS, sharing products, institutions, and closing outcomes with PRS, and feeding live contracted equipment and customer data straight to Technical Service.',
    estimatedSeconds: 20,
  },
  {
    id: 'scene-13-outro',
    compositionId: 'Scene13Outro',
    number: '13',
    chapter: 'SBSI CMS',
    title: 'Outro & Brand Lockup',
    narration:
      'Every vendor. Every clause. Every approval and signature. The SBSI Contract Management System.',
    estimatedSeconds: 12,
  },
];
