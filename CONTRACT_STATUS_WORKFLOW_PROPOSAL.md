# Contract Status, Stage Workflow & E-Signature / Signed Document Proposal

## 1. Executive Summary

Currently, the Contract Management System (CMS) tracks two separate status fields on a contract:
1. **Approval Status (`approval_status`)**: `Pending`, `Approved`, `Rejected`
2. **Workflow Stage (`workflow_status`)**: `SBSI Review`, `Client Review`, `Notarized PDF`

**Current Gap:**
- `workflow_status` can be changed freely without step-by-step confirmation.
- A contract becomes `Active` as soon as `approval_status` is set to `Approved`, even if the contract has not completed `SBSI Review`, `Client Review`, or been signed.
- Unsigned draft documents and signed/executed documents share the same storage path and metadata structure, with no built-in flow to replace a draft with a scanned signed copy or e-signature.

**Proposed Solution:**
- Turn **Workflow Stage** into a gated, step-by-step pipeline (`SBSI Review` -> `Client Review` -> `For Signing` -> `Signed` -> `Notarized PDF`) where each step requires explicit user confirmation.
- Add a **Signing Gate** (`For Signing` -> `Signed`) that requires either:
  1. **Uploading a Scanned Signed Document**, or
  2. **Applying an In-App E-Signature**
- Store signed documents in a dedicated storage prefix (`contracts/signed/`) with metadata linking them to the draft document they replace.

---

## 2. Current System vs. Proposed Stage Pipeline

```mermaid
flowchart LR
    subgraph Current["Current Behavior (Unrestricted)"]
        C1["Draft Created (Pending)"] --> C2["Manager Clicks Approve"]
        C2 --> C3["Contract Immediately Active"]
        C4["SBSI Review / Client Review / Notarized PDF"] -. "Can be changed in any order without confirmation" .-> C3
    end

    subgraph Proposed["Proposed Stage Pipeline (Confirmation Required at Each Step)"]
        P1["1. SBSI Review"] -- "Confirm Review Passed" --> P2["2. Client Review"]
        P2 -- "Confirm Client Accepted" --> P3["3. For Signing"]
        P3 -- "Upload Scanned PDF or Apply E-Signature" --> P4["4. Signed"]
        P4 -- "Optional: Upload Notarized Copy" --> P5["5. Notarized PDF"]
    end
```

---

## 3. Decision 1: Order of Manager Approval vs. Review & Signing Stages

Please select which workflow matches our business process (**Option 1A** or **Option 1B**).

### Option 1A: Review & Sign While `Pending`, Final Manager Approval Unlocks `Active`
In this flow, the contract stays in `Pending` while Sales and Legal/SBSI move it through `SBSI Review`, `Client Review`, and `For Signing`. Once the signed document is uploaded/e-signed (`Signed`), the Manager gives final approval to make the contract `Active`.

```mermaid
flowchart TD
    Start(["Sales / Employee Creates Contract & Uploads Draft PDF"]) --> Init["Approval Status: Pending<br/>Stage: SBSI Review"]

    Init --> Step1{"Confirm SBSI Review Complete?"}
    Step1 -- "Needs Changes" --> Reject1["Approval Status: Rejected<br/>(Creator Revises & Resubmits)"]
    Reject1 --> Init
    Step1 -- "Confirmed" --> Stage2["Stage: Client Review<br/>(Approval Status: Pending)"]

    Stage2 --> Step2{"Confirm Client Review Complete?"}
    Step2 -- "Client Requested Edits" --> UpdateDraft["Upload Revised Draft PDF"] --> Init
    Step2 -- "Confirmed" --> Stage3["Stage: For Signing<br/>(Approval Status: Pending)"]

    Stage3 --> SignGate{"Complete Signing Requirement"}
    SignGate -- "Option A: E-Sign in App" --> StoreSigned["Save to contracts/signed/<br/>Stage: Signed"]
    SignGate -- "Option B: Upload Scanned Signed PDF" --> StoreSigned

    StoreSigned --> FinalGate{"Manager / Workflow Final Approval"}
    FinalGate -- "Approve (Gate Passed: Stage is Signed)" --> Active(["Approval Status: Approved<br/>Lifecycle Status: Active"])
    FinalGate -- "Reject" --> Reject1

    Active -. "Optional Post-Sign Step" .-> Notarized["Upload Notarized Copy<br/>Stage: Notarized PDF"]
```

---

### Option 1B: Internal Approval First, Then Review & Signing Before Becoming `Active`
In this flow, the Manager/Approval Workflow approves the internal contract request first (`Pending` -> `Approved`), which unlocks the external review and signing pipeline. However, the contract lifecycle remains `Pending Execution` (not `Active`) until it reaches `Signed`.

```mermaid
flowchart TD
    Start(["Sales / Employee Creates Contract Request & Draft PDF"]) --> Pending["Approval Status: Pending<br/>Stage: Not Started"]

    Pending --> InternalGate{"Internal Manager / Workflow Approval"}
    InternalGate -- "Reject" --> Rejected["Approval Status: Rejected<br/>(Creator Revises & Resubmits)"]
    Rejected --> Pending

    InternalGate -- "Approve Request" --> Stage1["Approval Status: Approved<br/>Stage: SBSI Review<br/>Lifecycle: Pending Execution"]

    Stage1 --> Step1{"Confirm SBSI Review Complete?"}
    Step1 -- "Confirmed" --> Stage2["Stage: Client Review"]

    Stage2 --> Step2{"Confirm Client Review Complete?"}
    Step2 -- "Confirmed" --> Stage3["Stage: For Signing"]

    Stage3 --> SignGate{"Complete Signing Requirement"}
    SignGate -- "Option A: E-Sign in App" --> Stage4["Save to contracts/signed/<br/>Stage: Signed"]
    SignGate -- "Option B: Upload Scanned Signed PDF" --> Stage4

    Stage4 --> Active(["Lifecycle Status: Active<br/>(Start/End Date Monitoring Enabled)"])
    Active -. "Optional Post-Sign Step" .-> Notarized["Upload Notarized Copy<br/>Stage: Notarized PDF"]
```

### Comparison Summary for Decision 1

| Criteria | Option 1A (Sign before Final Approval) | Option 1B (Internal Approval first, Sign before Active) |
| :--- | :--- | :--- |
| **When Manager Approves** | At the end, verifying the signed contract is complete before activating. | At the start, authorizing Sales to send the contract to SBSI/Client. |
| **What `Approved` Means** | Fully executed and `Active`. | Internally approved to proceed with external review & signing. |
| **Key Gate for `Active`** | Cannot click `Approve` until stage is `Signed` with a signed file. | `Approved` unlocks stages; contract only turns `Active` once stage is `Signed`. |

---

## 4. Decision 2: Handling the Existing Draft Document When Signing

When a user is at the `For Signing` stage and either applies an **E-Signature** or uploads a **Scanned Signed PDF**, how should the system handle the original unsigned draft PDF?

### Option 2A: Archive / Supersede Original Draft (Recommended)
The new signed PDF becomes the **Primary Active Document** stored under `contracts/signed/`. The original draft PDF is kept in storage, marked as `Superseded / Previous Draft`, and moved to a collapsible history section for audit compliance.

```mermaid
flowchart LR
    subgraph Before["Before Signing (Stage: For Signing)"]
        D1["Draft_Contract_v1.pdf<br/>Category: Draft (Active)<br/>Path: contracts/documents/"]
    end

    Action{"User Uploads Scanned PDF<br/>OR Applies E-Signature"}

    subgraph After2A["After Signing — Option 2A (Supersede & Keep History)"]
        S1["Signed_Contract_Final.pdf<br/>Category: Signed (Primary Shown)<br/>Path: contracts/signed/<br/>Replaces: Draft_Contract_v1"]
        D1_Old["Draft_Contract_v1.pdf<br/>Category: Draft (Superseded / Archived)<br/>Kept in 'Previous Drafts' & Audit Log"]
        S1 -. "replaces_document_id" .-> D1_Old
    end

    Before --> Action --> After2A
```

---

### Option 2B: Hard-Replace (Overwrite / Delete Old Draft)
The new signed PDF is stored in `contracts/signed/`, and the old unsigned draft file is permanently deleted from storage so only the signed file remains on the contract.

```mermaid
flowchart LR
    subgraph BeforeB["Before Signing (Stage: For Signing)"]
        DB1["Draft_Contract_v1.pdf<br/>Category: Draft<br/>Path: contracts/documents/"]
    end

    ActionB{"User Uploads Scanned PDF<br/>OR Applies E-Signature"}

    subgraph After2B["After Signing — Option 2B (Hard Delete Draft)"]
        SB1["Signed_Contract_Final.pdf<br/>Category: Signed (Only File Kept)<br/>Path: contracts/signed/"]
        Del["Draft_Contract_v1.pdf<br/>Deleted from Storage<br/>(Metadata kept only in Audit Log JSON)"]
    end

    BeforeB --> ActionB --> After2B
```

### Comparison Summary for Decision 2

| Criteria | Option 2A: Supersede & Archive (Recommended) | Option 2B: Hard-Delete Draft |
| :--- | :--- | :--- |
| **Audit & Legal Traceability** | High — reviewers can compare the original draft against the scanned signed PDF. | Low — original draft file is deleted; only audit log text remains. |
| **UI Cleanliness** | Clean — primary view shows the **Signed Document**; drafts are tucked in a "Previous Drafts" drawer. | Clean — only one file exists. |
| **Storage Impact** | Minimal (1 extra PDF per contract). | Lowest (old file deleted). |

---

## 5. Signing & Document Storage Architecture

Regardless of whether E-Signature or Scanned Upload is chosen for a contract, both use the same unified signing gate and isolated storage path:

```mermaid
sequenceDiagram
    actor User as Sales / Manager
    participant UI as Vue 3 Frontend (SignContractModal)
    participant API as Contract Service (Laravel)
    participant Store as Object Storage (contracts/signed/)
    participant Mongo as MongoDB (documents)
    participant MySQL as MySQL (contracts + audit_logs)

    User->>UI: Click "Confirm & Sign Contract" (at Stage: For Signing)
    alt Method 1: Upload Scanned Signed Document
        User->>UI: Select scanned PDF + choose draft to replace
        UI->>API: POST /api/contracts/{id}/sign (file, method=scanned_upload, replaces_doc_id)
    else Method 2: In-App E-Signature
        User->>UI: Draw/Upload signature on PDF preview
        UI->>UI: Stamp signature onto PDF
        UI->>API: POST /api/contracts/{id}/sign (signed_file, method=e_signature, replaces_doc_id)
    end

    API->>Store: Save file to contracts/signed/{uuid}.pdf
    API->>Mongo: Create Document (doc_category='signed', signature_method, replaces_document_id)
    API->>Mongo: Mark old draft Document (is_superseded=true) [Option 2A] or Delete [Option 2B]
    API->>MySQL: Update workflow_status -> 'Signed' & write audit_logs entry
    API-->>UI: Return updated Contract + Signed Document
```

---

## 6. Sign-Off Checklist for Project Manager

- [ ] **Decision 1 (Workflow Order):**
  - [ ] **Option 1A:** Complete `SBSI Review` -> `Client Review` -> `For Signing` -> `Signed` *while* `Pending`, before final Manager `Approve`.
  - [ ] **Option 1B:** Manager `Approve` comes first, then contract steps through `SBSI Review` -> `Client Review` -> `For Signing` -> `Signed` before becoming `Active`.
- [ ] **Decision 2 (Draft Document Replacement):**
  - [ ] **Option 2A:** Archive/supersede the unsigned draft under "Previous Drafts" when the signed PDF is uploaded/e-signed.
  - [ ] **Option 2B:** Permanently delete the unsigned draft file when replaced by the signed PDF.
- [ ] **Stage List Confirmation:** Are the 5 stages (`SBSI Review` -> `Client Review` -> `For Signing` -> `Signed` -> `Notarized PDF`) exact, or should any stage name be added/renamed?

