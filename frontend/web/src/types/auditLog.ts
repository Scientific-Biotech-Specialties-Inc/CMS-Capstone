export type ActionType =
  | 'Contract Created'  | 'Contract Updated' | 'Contract Approved'
  | 'Contract Deleted'  | 'User Created'     | 'User Updated'
  | 'User Deleted'      | 'Partner Added'    | 'Partner Updated'
  | 'Role Updated'      | 'Settings Changed' | 'Login'
  | 'created'           | 'updated'          | 'deleted'
  | 'user_created'      | 'Login Success'    | 'Login Failed'
  | 'Logout'            | 'Document Uploaded'| 'Document Deleted'
  | 'Email Sent'        | 'Email Skipped'    | 'Email Failed'
  | 'profile_updated'   | 'Role_Permission_Updated' | 'permission_denied'

export interface LogEntry {
  id: string
  source: 'cms' | 'auth'
  user_id: number
  user_name: string
  role?: string
  user_email?: string
  action: ActionType
  entity_type: string
  description: string
  performed_at: string
  old_data?: any
  new_data?: any
}

export const actionOptions: Array<ActionType | 'All'> = [
  'All',
  'created',
  'updated',
  'deleted',
  'user_created',
  'Login Success',
  'Login Failed',
  'Logout',
  'Contract Created',
  'Contract Updated',
  'Contract Approved',
  'Contract Deleted',
  'User Created',
  'User Updated',
  'User Deleted',
  'Partner Added',
  'Partner Updated',
  'Role Updated',
  'Settings Changed',
  'Login',
  'Document Uploaded',
  'Document Deleted',
  'Email Sent',
  'Email Skipped',
  'Email Failed',
  'profile_updated',
  'Role_Permission_Updated',
  'permission_denied',
]

export const actionBadge: Record<ActionType, string> = {
  'Contract Created':  'bg-emerald-50 text-emerald-700 border-emerald-200',
  'Contract Updated':  'bg-brand-blue/8 text-brand-blue border-brand-blue/20',
  'Contract Approved': 'bg-brand-navy/8 text-brand-navy border-brand-navy/20',
  'Contract Deleted':  'bg-red-50 text-red-600 border-red-200',
  'User Created':      'bg-emerald-50 text-emerald-700 border-emerald-200',
  'User Updated':      'bg-brand-blue/8 text-brand-blue border-brand-blue/20',
  'User Deleted':      'bg-red-50 text-red-600 border-red-200',
  'Partner Added':     'bg-emerald-50 text-emerald-700 border-emerald-200',
  'Partner Updated':   'bg-brand-blue/8 text-brand-blue border-brand-blue/20',
  'Role Updated':      'bg-amber-50 text-amber-700 border-amber-200',
  'Settings Changed':  'bg-amber-50 text-amber-700 border-amber-200',
  'Login':             'bg-black/4 text-black/45 border-black/10',
  'created':           'bg-emerald-50 text-emerald-700 border-emerald-200',
  'updated':           'bg-brand-blue/8 text-brand-blue border-brand-blue/20',
  'deleted':           'bg-red-50 text-red-600 border-red-200',
  'user_created':      'bg-indigo-50 text-indigo-700 border-indigo-200',
  'Login Success':     'bg-teal-50 text-teal-700 border-teal-200',
  'Login Failed':      'bg-rose-50 text-rose-700 border-rose-200',
  'Logout':            'bg-slate-50 text-slate-700 border-slate-200',
  'Document Uploaded': 'bg-emerald-50 text-emerald-700 border-emerald-200',
  'Document Deleted':  'bg-red-50 text-red-600 border-red-200',
  'Email Sent':        'bg-emerald-50 text-emerald-700 border-emerald-200',
  'Email Skipped':     'bg-black/4 text-black/35 border-black/8',
  'Email Failed':      'bg-red-50 text-red-700 border-red-200',
  'profile_updated':   'bg-brand-blue/8 text-brand-blue border-brand-blue/20',
  'Role_Permission_Updated': 'bg-amber-50 text-amber-700 border-amber-200',
  'permission_denied': 'bg-red-50 text-red-600 border-red-200',
}

const actionMap: Record<string, string> = {
  profile_updated: 'Profile Updated',
  Role_Permission_Updated: 'Role Permission Updated',
  permission_denied: 'Permission Denied',
  user_created: 'User Created',
  created: 'Created',
  updated: 'Updated',
  deleted: 'Deleted'
}

export function formatAction(action: string): string {
  if (!action) return ''
  if (actionMap[action]) return actionMap[action]
  return action
    .replace(/_/g, ' ')
    .replace(/\b\w/g, c => c.toUpperCase())
}
