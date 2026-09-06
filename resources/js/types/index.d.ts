export type Locale = 'ar' | 'en' | 'fr';

export type Identifier = string;

export type IsoDateTime = string;

export type UserRole = 'student' | 'teacher' | 'guardian' | string;

export interface AuthUser {
    id: Identifier;
    name: string;
    email: string;
    locale: Locale;
    roles: UserRole[];
}

export interface FlashMessages {
    success?: string | null;
    error?: string | null;
}

export type TranslationValue = string | TranslationDictionary;

export interface TranslationDictionary {
    [key: string]: TranslationValue;
}

export interface AppPageProps extends Record<string, unknown> {
    auth: {
        user: AuthUser | null;
    };
    flash: FlashMessages;
    translations: TranslationDictionary;
    locale?: Locale;
    supportedLocales?: Locale[];
}

export interface LoadablePageProps {
    loading?: boolean;
    error?: string | null;
}

export interface PersonSummary {
    id: Identifier;
    name: string;
    avatarUrl?: string | null;
}

export interface Session {
    id: Identifier;
    title: string;
    subject?: string | null;
    teacher?: PersonSummary | null;
    startsAt: IsoDateTime;
    endsAt: IsoDateTime;
    timezone?: string;
    status: string;
    location?: string | null;
    joinUrl?: string | null;
    canJoinAt?: IsoDateTime | null;
    canJoinUntil?: IsoDateTime | null;
    canJoin?: boolean;
    recordingUrl?: string | null;
    attendanceConfirmed?: boolean;
    reportSubmitted?: boolean;
}

export interface Attendance {
    id: Identifier;
    sessionId: Identifier;
    studentId: Identifier;
    studentName: string;
    studentAvatarUrl?: string | null;
    status: string;
    note?: string | null;
    recordedAt?: IsoDateTime | null;
}

export interface Assignment {
    id: Identifier;
    title: string;
    instructions?: string;
    courseName: string;
    dueAt: IsoDateTime;
    status: string;
    submissionStatus: string;
    submittedAt?: IsoDateTime | null;
    submissionContent?: string | null;
    allowsLate?: boolean;
    latePenaltyPercent?: number;
    maxScore?: number;
    score?: number | null;
    feedback?: string | null;
    gradedAt?: IsoDateTime | null;
    canSubmit?: boolean;
    submitUrl?: string;
    url?: string | null;
}

export interface MonthlyReport {
    id: Identifier;
    month: string;
    title: string;
    status: string;
    issuedAt?: IsoDateTime | null;
    attendanceRate?: number | null;
    summary?: string | null;
    downloadUrl?: string | null;
}

export interface Child {
    id: Identifier;
    name: string;
    avatarUrl?: string | null;
    gradeLevel?: string | null;
    status: string;
}

export interface PostponementRequest {
    id: Identifier;
    session: Session;
    requestedBy: PersonSummary;
    reason: string;
    requestedStartAt: IsoDateTime;
    status: string;
    approveUrl: string;
    proposeAlternativeUrl: string;
    rejectUrl: string;
}

export type StatusColor = 'neutral' | 'brand' | 'success' | 'warning' | 'danger';

export type StatusColorMap<TStatus extends string = string> = Partial<Record<TStatus, StatusColor>>;
