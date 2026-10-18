import http from './http'

export interface OaNode {
  id: number
  process_id: number
  node_order: number
  approver_id: number
  status: 'pending' | 'approved' | 'rejected' | 'skipped'
  comment?: string | null
  acted_at?: string | null
  approver?: { id: number; name: string }
  process?: OaProcess
}

export interface OaProcess {
  id: number
  type: 'leave' | 'leave_cancel' | 'shift_change' | 'append_checkin'
  applicant_id: number
  title: string
  status: 'pending' | 'approved' | 'rejected' | 'canceled'
  current_node: number
  created_at: string
  applicant?: { id: number; name: string; type: string }
  nodes?: OaNode[]
  leave_detail?: {
    leave_type: string
    start_date: string
    end_date: string
    reason: string
  } | null
  leave_cancel_detail?: { leave_process_id: number; reason: string } | null
  shift_change_detail?: {
    my_schedule_id: number
    target_schedule_id: number
    reason: string
    my_schedule?: { id: number; duty_date: string; shift_label: string }
    target_schedule?: { id: number; duty_date: string; shift_label: string; user?: { id: number; name: string } }
  } | null
  append_checkin_detail?: {
    work_date: string
    check_type: 'in' | 'out'
    check_time: string
    reason: string
  } | null
}

export interface ApproverOption {
  id: number
  name: string
  type: string
}

export const listApprovers = () =>
  http.get<never, { list: ApproverOption[] }>('/oa/approvers')
export const myOaProcesses = (status?: string) =>
  http.get<never, { list: OaProcess[] }>('/oa/my', { params: { status } })
export const pendingOa = () => http.get<never, { list: OaNode[] }>('/oa/pending')
export const actedOa = () => http.get<never, { list: OaNode[] }>('/oa/acted')

export const submitLeave = (data: {
  leave_type: string
  start_date: string
  end_date: string
  reason: string
  approver_ids: number[]
}) => http.post('/oa/leave', data)

export const submitLeaveCancel = (data: {
  leave_process_id: number
  reason: string
  approver_ids: number[]
}) => http.post('/oa/leave-cancel', data)

export const submitShiftChange = (data: {
  my_schedule_id: number
  target_schedule_id: number
  reason: string
  approver_ids: number[]
}) => http.post('/oa/shift-change', data)

export const submitAppendCheckin = (data: {
  work_date: string
  check_type: 'in' | 'out'
  check_time: string
  reason: string
  approver_ids: number[]
}) => http.post('/oa/append-checkin', data)

export const approveNode = (nodeId: number, comment?: string) =>
  http.post(`/oa/nodes/${nodeId}/approve`, { comment })
export const rejectNode = (nodeId: number, comment: string) =>
  http.post(`/oa/nodes/${nodeId}/reject`, { comment })
export const cancelProcess = (processId: number) =>
  http.post(`/oa/${processId}/cancel`)
