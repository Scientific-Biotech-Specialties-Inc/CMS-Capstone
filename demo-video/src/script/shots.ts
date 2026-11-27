import type { SfxName } from '../components/SfxCue';

export type FocusBounds = readonly [left: number, top: number, width: number, height: number];

export type Shot = {
  at: number;
  title: string;
  image: string;
  source: readonly [start: number, end?: number];
  focus?: FocusBounds;
  detail?: string;
  reveal?: 'outline' | 'scan' | 'chart';
  steps?: readonly { at: number; label: string }[];
  cue?: { sourceTime: number; sound: SfxName; point?: readonly [x: number, y: number] };
  rate?: number;
};

export type Milestone = { at: number; label: string };

export type FootageEdit = {
  clip: string;
  shots: readonly Shot[];
  milestones?: readonly Milestone[];
};

// `at` follows the narration clock. Source ranges are seconds in the untrimmed recording.
export const EDITS: Readonly<Record<string, FootageEdit>> = {
  'scene-02-auth-rbac': {
    clip: 'clip-01-auth-roles.webm',
    shots: [
      { at: 0, title: 'One shared sign-in', image: 'auth-login.png', source: [6.6, 9.2], focus: [1136, 256, 608, 560], reveal: 'outline' },
      { at: 5.9, title: 'Dedicated portals', detail: 'Admin, Manager, and Sales', image: 'auth-home.png', source: [14.4, 15.6], focus: [232, 120, 1392, 760] },
      { at: 11.1, title: 'Role permissions', image: 'admin-roles.png', source: [25.4], focus: [256, 608, 1632, 472] },
    ],
  },
  'scene-03-analytics': {
    clip: 'clip-02-analytics.webm',
    shots: [
      { at: 0, title: 'Live contract metrics', image: 'admin-dashboard.png', source: [31.1], reveal: 'outline' },
      { at: 3.8, title: 'Contract trends and status', image: 'admin-dashboard.png', source: [53.1], focus: [280, 440, 1616, 408], reveal: 'chart' },
      { at: 8.6, title: 'Burn rate and margin', detail: 'SERMS contract attribution view', image: 'admin-dashboard.png', source: [51.3], focus: [272, 248, 1632, 744], reveal: 'outline' },
    ],
  },
  'scene-04-vendors': {
    clip: 'clip-03-vendor-create.webm',
    shots: [
      { at: 0, title: 'Business partners', image: 'admin-partners.png', source: [15.5], reveal: 'outline' },
      { at: 3.1, title: 'Partner onboarding', detail: 'Branch, class tier, and contact details', image: 'admin-partners-create.png', source: [36.4, 42.5], focus: [256, 128, 1632, 744], rate: 1.75 },
      { at: 9.1, title: 'Back in the partner directory', image: 'admin-partners-after-create.png', source: [53.1], focus: [256, 296, 1632, 736], reveal: 'outline' },
    ],
  },
  'scene-05-ai-vendors': {
    clip: 'clip-04-ai-vendor.webm',
    shots: [
      { at: 0, title: 'AI vendor suggestions', image: 'admin-vendor-suggestions-live.png', source: [23.2], reveal: 'outline' },
      { at: 5.4, title: 'Find a relevant supplier', image: 'admin-vendor-suggestions-live.png', source: [24.5, 27.2], focus: [264, 312, 1608, 592], reveal: 'outline' },
      { at: 11.8, title: 'Review and onboard', detail: 'Check the prefilled details before saving.', image: 'admin-vendor-suggestions-review-live.png', source: [30.6, 38.1], focus: [264, 160, 1616, 904], rate: 1.5 },
    ],
  },
  'scene-06-create-contract': {
    clip: 'clip-05-contract-create-ai.webm',
    milestones: [
      { at: 0, label: 'Manual' },
      { at: 3.3, label: 'OCR Import' },
      { at: 13.65, label: 'AI Editor' },
    ],
    shots: [
      { at: 0, title: 'Start with the contract form', image: 'admin-contracts-create.png', source: [0.6], reveal: 'outline' },
      { at: 3.3, title: 'Import a document', detail: 'PDF, DOCX, or scan', image: 'manager-ocr-selected.png', source: [3.45], focus: [696, 392, 528, 296], reveal: 'outline' },
      { at: 7.0, title: 'Extract contract details', detail: 'Sample contract selected', image: 'manager-ocr-selected.png', source: [15.35, 16.7], focus: [688, 352, 544, 376], reveal: 'scan' },
      { at: 10.15, title: 'Review the populated fields', detail: 'Check extracted details before saving', image: 'admin-contracts-create.png', source: [17.15, 18.65], focus: [256, 128, 1632, 704], reveal: 'outline' },
      { at: 13.65, title: 'Draft in the AI editor', image: 'admin-contracts-editor.png', source: [19.25, 22.3], reveal: 'outline' },
      { at: 17.6, title: 'Attach the draft PDF', image: 'admin-contracts-editor.png', source: [29.4, 31.4], focus: [1392, 48, 528, 296], cue: { sourceTime: 30.86, point: [1824, 135], sound: 'mouse-click' } },
      { at: 20.15, title: 'Contract ready for review', image: 'manager-contract-detail.png', source: [53.1] },
    ],
  },
  'scene-07-ai-risk': {
    clip: 'clip-06-ai-risk.webm',
    milestones: [
      { at: 0, label: 'Summary' },
      { at: 10.8, label: 'Findings' },
      { at: 20.7, label: 'Export' },
    ],
    shots: [
      { at: 0, title: 'AI risk assessment', image: 'manager-risk-assessment-live.png', source: [23.25] },
      { at: 4.8, title: 'Check the playbook', detail: '14 clauses guide the assessment.', image: 'manager-risk-assessment-live.png', source: [24.6], focus: [272, 168, 1616, 224], reveal: 'scan' },
      { at: 10.8, title: 'Find the flagged clause', detail: 'Severity and source excerpt', image: 'manager-risk-assessment-live.png', source: [26.2], focus: [304, 440, 1552, 248], reveal: 'outline' },
      { at: 15.3, title: 'See why it matters', detail: 'Explanation and recommended remedy', image: 'manager-risk-assessment-live.png', source: [28.3], focus: [304, 608, 1552, 216], reveal: 'outline' },
      { at: 20.7, title: 'Export the assessment', image: 'manager-risk-assessment-live.png', source: [32.5, 33.6], focus: [1344, 72, 576, 240] },
    ],
  },
  'scene-08-workflows': {
    clip: 'clip-07-workflows.webm',
    shots: [
      { at: 0, title: 'Workflows by contract type', image: 'admin-roles-workflows.png', source: [26.9], reveal: 'outline' },
      { at: 4.1, title: 'Configure internal approval', detail: 'Sequential or parallel', image: 'admin-workflow-editor-live.png', source: [44.5, 47.9], focus: [528, 312, 576, 488], reveal: 'outline' },
      { at: 10.8, title: 'Assign execution reviewers', image: 'admin-workflow-editor-live.png', source: [48.7], focus: [1008, 336, 560, 672], reveal: 'outline' },
      { at: 18.7, title: 'Review the execution stages', detail: 'Manager or CEO confirms signing.', image: 'admin-workflow-editor-live.png', source: [49.1], focus: [912, 488, 680, 480] },
    ],
  },
  'scene-09-approval': {
    clip: 'clip-08-approval.webm',
    shots: [
      { at: 0, title: 'Incoming contract requests', image: 'manager-contract-requests.png', source: [23.6], reveal: 'outline' },
      { at: 6.1, title: 'Review the submission', image: 'manager-approval-detail-live.png', source: [38.25], focus: [256, 80, 1632, 616], reveal: 'outline' },
      { at: 12.1, title: 'Record the approval decision', image: 'manager-approval-detail-live.png', source: [39.1, 41.2], focus: [1112, 64, 808, 248], steps: [
        { at: 1.3, label: 'Approve to advance' },
        { at: 3.1, label: 'Give a reason to reject' },
      ] },
      { at: 19.9, title: 'Approval unlocks execution', detail: 'This request is still Pending.', image: 'manager-approval-detail-live.png', source: [43.1], focus: [280, 80, 1328, 240] },
    ],
  },
  'scene-10-execution-signing': {
    clip: 'clip-09-execution-signing.webm',
    milestones: [
      { at: 0, label: 'Execution Review' },
      { at: 17.5, label: 'For Signing' },
      { at: 29.8, label: 'Signed Example' },
    ],
    shots: [
      { at: 0, title: 'From approval to execution', image: 'admin-workflow-editor-live.png', source: [25.6], focus: [256, 72, 1632, 336] },
      { at: 4.2, title: 'Execution reviews', image: 'manager-approval-detail-live.png', source: [26.1], focus: [264, 648, 1632, 424], reveal: 'outline', steps: [
        { at: 0.4, label: 'Submit SBSI review' },
        { at: 3.4, label: 'Record client acceptance' },
        { at: 5.2, label: 'Reviewer confirms' },
      ] },
      { at: 11.8, title: 'Return a draft for revision', detail: 'Changes can send it back through internal approval.', image: 'manager-approval-detail-live.png', source: [28.3], focus: [256, 80, 1632, 376] },
      { at: 17.5, title: 'Open the signing workspace', image: 'manager-sign-workspace-live.png', source: [53.9], reveal: 'outline' },
      { at: 22.8, title: 'Place the signature', detail: 'Preview the mark on the draft.', image: 'manager-sign-workspace-live.png', source: [54.05, 56.8], focus: [976, 160, 760, 832], reveal: 'outline' },
      { at: 29.8, title: 'A signed contract', detail: 'Separate example record', image: 'manager-contract-detail.png', source: [71.1, 73.5], focus: [256, 64, 1632, 520], reveal: 'outline' },
      { at: 34, title: 'Keep the signed PDF', detail: 'The document stays attached to the contract.', image: 'manager-contract-detail.png', source: [84.2], focus: [272, 872, 1040, 208] },
    ],
  },
  'scene-11-ops': {
    clip: 'clip-10-ops.webm',
    shots: [
      { at: 0, title: 'Find a contract', image: 'admin-contracts.png', source: [19.2, 22.3], focus: [256, 344, 1632, 488], reveal: 'outline' },
      { at: 4.1, title: 'Track expiry alerts', image: 'admin-notifications.png', source: [34, 36.7], focus: [264, 200, 1616, 664], reveal: 'outline' },
      { at: 7.8, title: 'Trace activity in the audit log', image: 'admin-audit-log.png', source: [70, 73], focus: [256, 200, 1632, 792], reveal: 'outline' },
      { at: 11.15, title: 'Manage contract rules', image: 'admin-system-config.png', source: [90.2, 93.7], rate: 1.35 },
    ],
  },
};
