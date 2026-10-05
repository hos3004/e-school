export interface AttendanceDraft {
  statuses: Record<string, string>;
  reason: string;
}
export interface ReportStudentDraft {
  student_profile_id: string;
  participation: string;
  performance: string;
  commitment: string;
}
export interface ReportDraft {
  summary: string;
  notes: string;
  students: ReportStudentDraft[];
}
export interface LessonAttendance {
  studentId: string;
  status: string;
  confirmedAt?: string | null;
}
interface SessionDraft {
  attendance?: AttendanceDraft;
  report?: ReportDraft;
}
// Navigation-only memory: no student data is written to local/session storage.
// Changing the authenticated account clears the previous account's draft context.
let owner: string | undefined;
const drafts = new Map<string, SessionDraft>();
function forOwner(userId: string) {
  if (owner !== userId) {
    drafts.clear();
    owner = userId;
  }
}
export function rememberSessionDraft(
  userId: string,
  sessionId: string,
  attendance: AttendanceDraft,
  report: ReportDraft,
): void {
  forOwner(userId);
  drafts.set(sessionId, structuredClone({ attendance, report }));
}
export function restoreSessionDraft(
  userId: string,
  sessionId: string,
  rows: LessonAttendance[],
  initialReport?: { summary?: string; notes?: string } | null,
): Required<SessionDraft> {
  forOwner(userId);
  const saved = drafts.get(sessionId);
  const reportsByStudent = new Map(
    saved?.report?.students.map((row) => [row.student_profile_id, row]),
  );
  return {
    attendance: {
      statuses: Object.fromEntries(
        rows.map((row) => [
          row.studentId,
          saved?.attendance?.statuses[row.studentId] ?? row.status,
        ]),
      ),
      reason: saved?.attendance?.reason ?? "",
    },
    report: {
      summary: initialReport?.summary ?? saved?.report?.summary ?? "",
      notes: initialReport?.notes ?? saved?.report?.notes ?? "",
      students: rows.map((row) => ({
        ...(reportsByStudent.get(row.studentId) ?? {
          participation: "",
          performance: "",
          commitment: "",
        }),
        student_profile_id: row.studentId,
      })),
    },
  };
}
export function clearSessionDraft(
  userId: string,
  sessionId: string,
  part: keyof SessionDraft,
): void {
  forOwner(userId);
  const saved = drafts.get(sessionId);
  if (!saved) return;
  delete saved[part];
  if (!saved.attendance && !saved.report) drafts.delete(sessionId);
}
export function attendanceChanges(
  rows: LessonAttendance[],
  statuses: Record<string, string>,
): Record<string, string> {
  return Object.fromEntries(
    rows.flatMap((row) => {
      const value = statuses[row.studentId];
      return !value ||
        value === "pending" ||
        (row.confirmedAt && value === row.status)
        ? []
        : [[row.studentId, value]];
    }),
  );
}
