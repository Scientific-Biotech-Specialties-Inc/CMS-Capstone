# Database Tables Documentation

## Shared/Core Tables (All Services)

### Table: users
PK id bigint
name string
email string (unique)
email_verified_at timestamp (nullable)
password string
remember_token string (nullable)
created_at timestamp
updated_at timestamp

### Table: password_reset_tokens
PK email string
token string
created_at timestamp (nullable)

### Table: sessions
PK id string
user_id bigint (nullable, indexed, foreign key to users.id)
ip_address string(45) (nullable)
user_agent text (nullable)
payload longText
last_activity integer (indexed)

### Table: cache
PK key string
value mediumText
expiration integer

### Table: cache_locks
PK key string
owner string
expiration integer

### Table: jobs
PK id bigint
queue string (indexed)
payload longText
attempts unsignedTinyInteger
reserved_at unsignedInteger (nullable)
available_at unsignedInteger
created_at unsignedInteger

### Table: job_batches
PK id string
name string
total_jobs integer
pending_jobs integer
failed_jobs integer
failed_job_ids longText
options mediumText (nullable)
cancelled_at integer (nullable)
created_at integer
finished_at integer (nullable)

### Table: failed_jobs
PK id bigint
uuid string (unique)
connection text
queue text
payload longText
exception longText
failed_at timestamp

---

## Contract Management Service

### Table: contract_categories
PK category_id bigint
category_name string

### Table: contract_statuses
PK status_id bigint
status_name string
color_code string (nullable)

### Table: contract_regions
PK region_id bigint
region_name string

### Table: contract_approval_statuses
PK approval_status_id bigint
status_name string

### Table: contracts
PK contract_id bigint
category_id bigint (nullable, indexed, foreign key to contract_categories.category_id)
supplier_id bigint (nullable, indexed, foreign key to suppliers.supplier_id)
workflow_status_id bigint (nullable, indexed, foreign key to contract_statuses.status_id)
approval_status_id bigint (nullable, foreign key to contract_approval_statuses.approval_status_id)
region_id bigint (nullable, foreign key to contract_regions.region_id)
prs_activity_id bigint (nullable)
bp_name string (nullable)
sbu_number string (nullable)
item_code string (nullable)
description text (nullable)
serial_number string (nullable)
start_date date (nullable)
end_date date (nullable)
notify_manager_count integer (default: 0)
rejection_reason text (nullable)
created_by bigint (nullable)
created_at timestamp
updated_at timestamp

### Table: contract_amendments
PK amendment_id bigint
contract_id bigint (indexed, foreign key to contracts.contract_id onDelete cascade)
version integer
bp_name text
category string
item_code string
description text
serial_number string
sbu_number string
region string
start_date date
end_date date
reason text
status string (default: 'Pending')
request_date date
created_by bigint (unsigned)
approved_by string (nullable)
rejection_reason text (nullable)
document_ids json (nullable, MongoDB Document IDs)
created_at timestamp
updated_at timestamp

### Table: contract_version_snapshots
PK id bigint
contract_id bigint (indexed, foreign key to contracts.contract_id onDelete cascade)
version integer
bp_name text
category string
item_code string
description text
serial_number string
sbu_number string
region string
start_date date
end_date date
reason text (nullable)
amended_by string (nullable)
approved_by string (nullable)
approved_date date (nullable)
docs json (nullable)
created_at timestamp
updated_at timestamp

### Table: documents (Deprecated/Migrated)
*Note: The MySQL `documents` table was dropped. Document metadata is now stored in MongoDB. Associated documents for amendments are referenced via the `document_ids` JSON column.*

### Table: audit_logs (Contract Management)
PK audit_id bigint
action string(50) - 'created', 'updated', 'deleted'
entity_type string(100) - 'Contract', 'Document', etc.
entity_id bigint (unsigned)
user_id bigint (nullable, indexed)
old_data json (nullable)
new_data json (nullable)
performed_at timestamp
user_name string (nullable)
user_email string (nullable)
user_department string (nullable)

---

## Notification Service

### Table: notifications
PK notification_id bigint
contract_id bigint (nullable, indexed)
user_id bigint (indexed, from auth-service)
message text
notification_date timestamp
is_read boolean (default: false)
notification_type string (nullable)
target_roles string (nullable)
target_user_id bigint (nullable, indexed)

### Table: notification_reads
PK id bigint
notification_id bigint (indexed, unique with user_id)
user_id bigint (indexed)
is_read boolean (default: false)
is_archived boolean (default: false)
is_favorite boolean (default: false)
created_at timestamp
updated_at timestamp

### Table: email_send_logs
PK id bigint
notification_id bigint (indexed)
user_id bigint (indexed)
recipient_email text (encrypted)
subject string
status string(20) (indexed) - 'sent', 'failed', 'skipped'
error_message text (nullable)
sent_at timestamp (nullable)
created_at timestamp (indexed)

### Table: system_configurations
PK id bigint
email_notifs_enabled boolean (default: true)
in_app_notifs_enabled boolean (default: true)
contract_expiry_alerts boolean (default: true)
approval_alerts boolean (default: true)
renewal_reminders boolean (default: true)
created_at timestamp
updated_at timestamp

### Table: email_preferences
PK id bigint
user_id bigint (unique, indexed)
email_notifications_enabled boolean (default: true)
contract_expiry_alerts boolean (default: true)
system_alerts_enabled boolean (default: true)
sms_notifications_enabled boolean (default: false)
login_alerts_enabled boolean (default: true)
newsletter_enabled boolean (default: true)
promotional_emails_enabled boolean (default: false)
created_at timestamp
updated_at timestamp

### Table: audit_logs (Notification)
PK audit_id bigint
action string(50)
entity_type string(100)
entity_id bigint (unsigned)
user_id bigint (nullable, indexed)
old_data json (nullable)
new_data json (nullable)
performed_at timestamp
user_name string (nullable)
user_email string (nullable)
user_department string (nullable)

---

## Vendor Management Service

### Table: suppliers
PK supplier_id bigint
supplier_name string
contact_number string (nullable)
email string (nullable)
address text (nullable)
region string (nullable)
status string (nullable)
tin_number string (nullable, unique)
industry string (nullable)
contact_person string (nullable)
created_at timestamp
updated_at timestamp

### Table: business_partners
PK partner_id bigint
bp_code string(100) (unique, indexed)
partner_name string (indexed)
contact_number longText (nullable)
email longText (nullable)
address longText (nullable)
region string (nullable, indexed)
status string (nullable)
industry string (nullable)
contact_person string (nullable)
created_by bigint (nullable, indexed)
created_at timestamp
updated_at timestamp

### Table: vendor_contract_associations
PK id bigint
vendor_type enum('supplier', 'partner')
vendor_id bigint (unsigned)
contract_id bigint (unsigned, indexed, foreign key to contracts.contract_id onDelete cascade)
attached_by unsignedInteger (nullable, indexed)
created_at timestamp
updated_at timestamp

### Table: personal_access_tokens
PK id bigint
tokenable_type string (morphs)
tokenable_id bigint (morphs)
name text
token string(64) (unique)
abilities text (nullable)
last_used_at timestamp (nullable)
expires_at timestamp (nullable, indexed)
created_at timestamp
updated_at timestamp

### Table: audit_logs (Vendor Management)
PK audit_id bigint
action string(50)
entity_type string(100) - 'Supplier', 'BusinessPartner'
entity_id bigint (unsigned)
user_id bigint (nullable, indexed)
old_data json (nullable)
new_data json (nullable)
performed_at timestamp
user_name string (nullable)
user_email string (nullable)
user_department string (nullable, indexed)

---

## Analytics Service

### Table: aggregated_metrics
PK id bigint
metric_type string(100) (indexed)
source_service string(50) (indexed) - contract-management, vendor-management, notification, search, ai-service
source_record_id bigint (nullable, indexed) - soft reference, no FK constraint
metric_value decimal(18,4) (nullable)
metric_date date (nullable, indexed)
metadata json (nullable)
created_at timestamp
updated_at timestamp

### Table: reports
PK id bigint
report_type string(100) (indexed)
source_service string(50) (nullable, indexed)
source_record_id bigint (nullable, indexed) - soft reference, no FK constraint
generated_by bigint (nullable, indexed) - soft reference to users.id (auth-service)
parameters json (nullable)
file_path string (nullable)
status string(20) (indexed) - pending, generated, failed
generated_at timestamp (nullable)
created_at timestamp
updated_at timestamp

### Table: audit_logs (Analytics Service)
PK audit_id bigint
action string(50) - 'created', 'updated', 'deleted'
entity_type string(100) - 'AggregatedMetric', 'Report'
entity_id bigint (unsigned)
user_id bigint (nullable, indexed)
old_data json (nullable)
new_data json (nullable)
performed_at timestamp
user_name string (nullable)
user_email string (nullable)
user_role string (nullable)
user_department string (nullable, indexed)

---

## AI Service

*Database: PostgreSQL (`cms-ai-db`) with the pgvector extension, distinct from the MySQL `cms-db`/`cms-analytics-db` schemas used by other services.*

### Table: risk_assessment_results
PK id bigint
document_id bigint (nullable, indexed) - soft reference to contract-management documents.document_id
contract_id bigint (nullable, indexed) - soft reference to contract-management contracts.contract_id
risk_score decimal(5,2) (nullable)
risk_level string(20) (nullable, indexed) - low, medium, high, critical
findings jsonb (nullable)
status string(20) (indexed) - pending, completed, failed
scanned_at timestamp (nullable)
created_at timestamp
updated_at timestamp

### Table: vendor_suggestions
PK id bigint
vendor_type string(20) (nullable, indexed) - supplier, business_partner
vendor_id bigint (nullable, indexed) - soft reference to suppliers.supplier_id or business_partners.partner_id
contract_id bigint (nullable, indexed) - soft reference to contract-management contracts.contract_id
suggestion_score decimal(5,2) (nullable)
suggestion_reason text (nullable)
status string(20) (indexed) - pending, accepted, dismissed
suggested_at timestamp (nullable)
created_at timestamp
updated_at timestamp

### Table: ocr_extractions
PK id bigint
document_id bigint (nullable, indexed) - soft reference to contract-management documents.document_id
contract_id bigint (nullable, indexed) - soft reference to contract-management contracts.contract_id
extracted_fields jsonb (nullable)
confidence_score decimal(5,2) (nullable)
status string(20) (indexed) - pending, completed, failed
extracted_at timestamp (nullable)
created_at timestamp
updated_at timestamp

### Table: embeddings
PK id bigint
entity_type string(20) (indexed) - 'document' or 'vendor' (business_partner/supplier)
entity_id bigint (indexed) - soft reference to documents.document_id, suppliers.supplier_id, or business_partners.partner_id depending on entity_type
embedding vector(1536) - pgvector column, fixed 1536 dimensions, used for similarity search
model_name string(100) (nullable)
created_at timestamp
updated_at timestamp

### Table: audit_logs (AI Service)
PK audit_id bigint
action string(50) - 'created', 'updated', 'deleted'
entity_type string(100) - 'RiskAssessmentResult', 'VendorSuggestion', 'OcrExtraction', 'Embedding'
entity_id bigint (unsigned)
user_id bigint (nullable, indexed)
old_data jsonb (nullable)
new_data jsonb (nullable)
performed_at timestamp
user_name string (nullable)
user_email string (nullable)
user_role string (nullable)
user_department string (nullable, indexed)

---

## Relationships Summary

### Foreign Keys
- **contracts.category_id** → contract_categories.category_id
- **contracts.supplier_id** → suppliers.supplier_id
- **contracts.workflow_status_id** → contract_statuses.status_id
- **contracts.approval_status_id** → contract_approval_statuses.approval_status_id
- **contracts.region_id** → contract_regions.region_id
- **contract_amendments.contract_id** → contracts.contract_id (onDelete: cascade)
- **contract_version_snapshots.contract_id** → contracts.contract_id (onDelete: cascade)
- **vendor_contract_associations.contract_id** → contracts.contract_id (onDelete: cascade)
- **sessions.user_id** → users.id

### Cross-Service References (Soft/Logical)
- **contracts.created_by** → users.id (auth-service)
- **contract_amendments.created_by** → users.id (auth-service)
- **notifications.user_id** → users.id (auth-service)
- **email_send_logs.user_id** → users.id (auth-service)
- **email_preferences.user_id** → users.id (auth-service)
- **audit_logs.user_id** → users.id (auth-service)
- **business_partners.created_by** → users.id (auth-service)
- **vendor_contract_associations.attached_by** → users.id (auth-service)
- **aggregated_metrics.source_record_id** → contracts/suppliers/business_partners/notifications/AI result tables (cross-service, varies by source_service)
- **reports.source_record_id** → contracts/suppliers/business_partners/notifications/AI result tables (cross-service, varies by source_service)
- **reports.generated_by** → users.id (auth-service)
- **risk_assessment_results.document_id** → documents.document_id (contract-management)
- **risk_assessment_results.contract_id** → contracts.contract_id (contract-management)
- **vendor_suggestions.vendor_id** → suppliers.supplier_id or business_partners.partner_id (vendor-management)
- **vendor_suggestions.contract_id** → contracts.contract_id (contract-management)
- **ocr_extractions.document_id** → documents.document_id (contract-management)
- **ocr_extractions.contract_id** → contracts.contract_id (contract-management)
- **embeddings.entity_id** → documents.document_id, suppliers.supplier_id, or business_partners.partner_id (varies by entity_type)

---

## Notes

- All tables use `id()` or custom primary keys as noted
- Timestamps are typically `created_at` and `updated_at` for Laravel Eloquent models
- Several columns use encryption (marked as encrypted via CryptCast in migrations)
- Indexed columns are used to optimize query performance
- Foreign keys with `onDelete: cascade` automatically delete related records
- Foreign keys with `onDelete: set null` set the foreign key to NULL when parent is deleted
- Search Service currently uses only core tables (users, sessions, cache, jobs)
- Analytics Service and AI Service reference records from other services using soft/logical identifiers only — no cross-database foreign key constraints are defined, since Analytics/AI live in separate database engines/schemas (`cms-analytics-db` on MySQL, `cms-ai-db` on PostgreSQL) from the services that own those records.
