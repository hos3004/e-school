export type DuesPeriod = {
  id: string;
  year: number;
  month: number;
  startsOn: string;
  endsOn: string;
  status: string;
  canAdjust: boolean;
  frozen: boolean;
  paidAt: string | null;
  lockedAt: string | null;
  approvedAt: string | null;
};
export type DuesTotals = {
  currency: string;
  amounts: Record<string, string>;
  minorUnits: Record<string, string>;
};
export type DuesCounts = {
  total: number;
  delivered: number;
  approved: number;
  pending: number;
  cancelled: number;
  minutes: number;
};
export type DuesEntry = {
  id: string;
  sessionId: string | null;
  entryType: string;
  outcomeKey: string;
  status: string;
  amount: string;
  currency: string;
  snapshotAmount: string | null;
  recordedAt: string | null;
  rateSnapshot: {
    resolved_via?: string;
    captured_at?: string;
    session_time?: { start?: string; end?: string };
  };
};
export type DuesAdjustment = {
  id: string;
  type: string;
  amount: string;
  currency: string;
  reason: string;
  referencePeriodId: string | null;
  status: string;
  proposedByName: string;
  proposedAt: string;
  approvedByName: string | null;
  approvedAt: string | null;
  rejectionReason: string | null;
  approveUrl: string | null;
  rejectUrl: string | null;
};
export type DuesLesson = {
  id: string;
  title: string;
  startsAt: string;
  course: string;
  study: string;
  track: string;
  studentCount: number;
  durationMinutes: number;
  actualDuration: boolean;
  status: string;
  statusLabel: string;
  approved: boolean;
  awaitingReview: boolean;
  actualTeacher: string;
  isActualTeacher: boolean;
  entries: DuesEntry[];
};
export type DuesDetail = {
  limitExceeded: boolean;
  canPropose: boolean;
  id: string;
  name: string;
  counts: DuesCounts | null;
  totals: DuesTotals[];
  lessons: DuesLesson[];
  entries: DuesEntry[];
  adjustments: DuesAdjustment[];
  proposeUrl: string;
  profileUrl: string | null;
  closeUrl: string;
};
export type DuesTeacher = {
  id: string;
  name: string;
  counts: DuesCounts | null;
  totals: DuesTotals[];
  detailUrl: string;
};
export type DuesFilters = {
  period?: string;
  teacher?: string;
  staff_profile_id?: string;
  track?: string;
  search?: string;
};
