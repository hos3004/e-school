export type Phase = "before" | "during" | "after";
export type Tab =
  "registration" | "students" | "teachers" | "schedule" | "policy";
export interface Slot {
  weekday: number;
  start_time: string;
}
export interface Placement {
  staff_profile_id: string;
  weekly_slots: Slot[];
  duration_minutes: number;
  interval_weeks: number;
  timezone: string;
  starts_on: string;
  ends_on: string | null;
}
export interface Schedule extends Placement {
  id: string;
}
export interface Student {
  id: string;
  code: string;
  name: string;
  student_timezone: string;
  teacher_name: string | null;
  schedule: Schedule | null;
  held: boolean;
  can_schedule: boolean;
  application_id: string | null;
  needs_followup: boolean;
  enrollments: {
    id: string;
    status: string;
    status_label: string;
    held: boolean;
    return_date: string | null;
  }[];
  insight: {
    attended: number;
    recorded: number;
    absences: number;
    sessions: number;
    next_session: { utc: string; local: string; teacher_id: string } | null;
    progress: {
      at: string;
      topics: string | null;
      next_plan: string | null;
      note?: string | null;
      strengths?: string | null;
      participation?: number | null;
      performance?: number | null;
      commitment?: number | null;
    } | null;
  } | null;
}
export interface Teacher {
  id: string;
  name: string;
  qualified: boolean;
  student_ids: string[];
  sessions: number;
  availability: {
    id: string;
    weekday: number;
    start_time: string;
    end_time: string;
    timezone: string;
    approval_status: string;
    effective_from: string;
    effective_to: string | null;
  }[];
}
export interface Session {
  id: string;
  student_id: string;
  teacher_id: string;
  original_teacher_id: string | null;
  status: string;
  status_label: string;
  start: string;
  end: string;
  duration: number;
  attendance: string | null;
  report_submitted: boolean;
  timezone: string;
}
export interface Page<T> {
  data: T[];
  total: number;
  current_page: number;
  last_page: number;
  prev_page_url: string | null;
  next_page_url: string | null;
}
export interface Defaults extends Omit<
  Placement,
  "staff_profile_id" | "weekly_slots"
> {
  durations: number[];
  max_interval: number;
  time_step: number;
}
export interface QuranProps {
  students: Page<Student>;
  directory: Student[];
  sessions: Page<Session>;
  teacherRows: Teacher[];
  teachers: Record<string, string>;
  counts: { all: number; assigned: number; pending: number; issues: number };
  filters: {
    phase: Phase;
    tab: Tab;
    status: "all" | "pending" | "assigned" | "issues";
    search: string;
    teacher: string | null;
    student: string | null;
    application: string | null;
    period: string;
    history: "0" | "1";
  };
  canPlace: boolean;
  can: {
    place: boolean;
    sessions: boolean;
    attendance: boolean;
    progress: boolean;
    registration: boolean;
    teacherProfile: boolean;
    availability: boolean;
    settings: boolean;
    dues: boolean;
    followup: boolean;
  };
  course: { id: string; name: string } | null;
  registration: {
    forms: {
      id: string;
      title: string;
      description: string;
      is_active: boolean;
      applications_count: number;
      public_url: string;
      questions: unknown[];
    }[];
    counts: { requests: number; accepted: number };
  } | null;
  policy: {
    edit_lock_hours: number;
    cancel_minutes: number;
    postpone_minutes: number;
    requires_declared: boolean;
    counter_days: number;
    ladder: { threshold: number; action: string }[];
  };
  defaults: Defaults;
}
