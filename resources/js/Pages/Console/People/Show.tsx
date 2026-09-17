import FinancialVisibility, {type FinancialVisibilityData} from "./FinancialVisibility";
import GuardianLinks, {
  type GuardianLinksData,
  type GuardianRole,
  type GuardianStudentLink,
} from "./GuardianLinks";
import LifecycleActions, { type LifecycleData } from "./LifecycleActions";
import PlacementActions, { type PlacementData } from "./PlacementActions";
import StudentPrograms, { type StudentProgramsData } from "./StudentPrograms";
import TeacherPrograms, { type TeacherPortfolioData } from "./TeacherPrograms";
import TeacherQualifications, {
  type TeacherQualificationsData,
} from "./TeacherQualifications";
import TeacherRates, { type TeacherRatesData } from "./TeacherRates";
import { Head, usePage } from "@inertiajs/react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import PersonMessaging, {
  type PersonMessagingData,
} from "@/Components/Console/PersonMessaging";
import ProfileView, {
  type ProfileHub,
  type ProfileWorkspace,
} from "@/Components/Console/ProfileView";
import { useI18n } from "@/lib/i18n";
import type { AppPageProps } from "@/types";
type GuardianHub = { students: GuardianStudentLink[]; roles: GuardianRole[] };
interface Props {
  financialVisibility?:FinancialVisibilityData|null;
  kind: "students" | "teachers" | "guardians";
  person: {
    id: string;
    code: string;
    full_name: string | null;
    status: string;
    status_tone?: string;
    email: string | null;
    phone: string | null;
    username: string | null;
    timezone: string;
    country_name: string | null;
    region_name: string | null;
    city?: string | null;
    date_of_birth: string | null;
    gender: string | null;
    bio?: string;
    notes?: string;
    specializations?: string[];
    nationality?: string | null;
    joined_at?: string | null;
    hired_at?: string | null;
    avatar_url?: string | null;
    national_id_last4?: string | null;
    occupation?: string | null;
    preferred_contact_channel?: string | null;
  };
  hub: ProfileHub | GuardianHub;
  profileWorkspace: ProfileWorkspace | null;
  backUrl: string;
  editUrl: string | null;
  displayTimezone: string;
  availabilityUrl: string | null;
  lifecycle: LifecycleData;
  placement?: PlacementData | null;
  programs?: StudentProgramsData | null;
  teaching?: TeacherPortfolioData | null;
  qualifications?: TeacherQualificationsData | null;
  rates?: TeacherRatesData | null;
  guardianLinks?: GuardianLinksData | null;
  messaging?: PersonMessagingData | null;
}
export default function PeopleShow({
  kind,
  person,
  hub,
  profileWorkspace,
  backUrl,
  editUrl,
  displayTimezone,
  availabilityUrl,
  financialVisibility,
  lifecycle,
  placement,
  programs,
  teaching,
  qualifications,
  rates,
  guardianLinks,
  messaging,
}: Props) {
  const t = useI18n();
  const { console: context } = usePage<
    AppPageProps & { console?: { navigation: { key: string; href: string }[] } }
  >().props;
  const followup = context?.navigation.find((link) => link.key === "followup");
  const dues = context?.navigation.find((link) => link.key === "teacher_dues");
  return (
    <ConsoleLayout
      title={t("console_people.profile_title")}
      section="records"
      hidePageHeading
    >
      <Head title={person.full_name || person.code} />
      {financialVisibility && <FinancialVisibility key={person.id+String(financialVisibility.visible)} setting={financialVisibility} />}
      {kind === "guardians" ? (
        <section className="rounded-xl border border-[var(--line)] bg-white p-5 my-5">
          <div className="flex items-center justify-between gap-3">
            <div>
              <h1 className="text-xl font-bold">
                {person.full_name || person.code}
              </h1>
              <p className="text-sm text-slate-500">
                {person.code} · {person.status}
              </p>
            </div>
            {editUrl && (
              <a href={editUrl} className="console-button">
                {t("console_people.edit")}
              </a>
            )}
          </div>
          <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
            {person.username && (
              <div>
                <dt className="text-slate-500">
                  {t("console_people.fields.username")}
                </dt>
                <dd>{person.username}</dd>
              </div>
            )}
            {person.email && (
              <div>
                <dt className="text-slate-500">
                  {t("console_people.fields.email")}
                </dt>
                <dd dir="ltr">{person.email}</dd>
              </div>
            )}
            {person.phone && (
              <div>
                <dt className="text-slate-500">
                  {t("console_people.fields.phone")}
                </dt>
                <dd dir="ltr">{person.phone}</dd>
              </div>
            )}
            <div>
              <dt className="text-slate-500">
                {t("console_people.fields.timezone")}
              </dt>
              <dd dir="ltr">{person.timezone}</dd>
            </div>
            {person.occupation && (
              <div>
                <dt className="text-slate-500">
                  {t("console_people.fields.occupation")}
                </dt>
                <dd>{person.occupation}</dd>
              </div>
            )}
            {person.preferred_contact_channel && (
              <div>
                <dt className="text-slate-500">
                  {t("console_people.fields.preferred_contact_channel")}
                </dt>
                <dd>{person.preferred_contact_channel}</dd>
              </div>
            )}
          </dl>
          <a href={backUrl} className="mt-4 inline-block underline text-sm">
            {t("console_people.back")}
          </a>
        </section>
      ) : (
        <ProfileView
          kind={kind === "students" ? "student" : "teacher"}
          audience="admin"
          person={{
            name: person.full_name || person.code,
            code: person.code,
            status: person.status,
            statusTone: person.status_tone,
            email: person.email,
            phone: person.phone,
            username: person.username,
            timezone: person.timezone,
            bio: person.bio,
            notes: person.notes,
            location: [person.country_name, person.region_name, person.city]
              .filter(Boolean)
              .join(" · "),
            joinedAt: person.joined_at || person.hired_at,
            avatarUrl: person.avatar_url,
            specializations: person.specializations,
            birthDate: person.date_of_birth,
            gender: person.gender
              ? t("console_people." + person.gender)
              : null,
            nationality: person.nationality,
          }}
          hub={hub as ProfileHub}
          workspace={profileWorkspace as ProfileWorkspace}
          timezone={displayTimezone}
          backUrl={backUrl}
          editUrl={editUrl}
          availabilityUrl={availabilityUrl}
          followupUrl={
            kind === "students" && followup
              ? followup.href +
                "?" +
                new URLSearchParams({
                  filter: "directory",
                  search: person.code,
                })
              : null
          }
          duesUrl={
            kind === "teachers" && dues
              ? dues.href +
                "?" +
                new URLSearchParams({
                  staff_profile_id: person.id,
                  teacher: person.id,
                })
              : null
          }
        />
      )}
      {kind === "guardians" && guardianLinks && (
        <GuardianLinks
          students={(hub as GuardianHub).students ?? []}
          roles={(hub as GuardianHub).roles ?? []}
          links={guardianLinks}
        />
      )}
      {programs && <StudentPrograms programs={programs} />}
      {teaching && <TeacherPrograms teaching={teaching} />}
      {qualifications && (
        <TeacherQualifications qualifications={qualifications} />
      )}
      {rates && <TeacherRates rates={rates} />}
      {messaging && (
        <PersonMessaging
          messaging={messaging}
          allowSchedule={kind !== "guardians"}
        />
      )}
      {placement && <PlacementActions placement={placement} />}
      <LifecycleActions lifecycle={lifecycle} />
    </ConsoleLayout>
  );
}
