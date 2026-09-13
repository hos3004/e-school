import FinancialVisibility, {type FinancialVisibilityData} from "./FinancialVisibility";
import LifecycleActions, { type LifecycleData } from "./LifecycleActions";
import PlacementActions, { type PlacementData } from "./PlacementActions";
import { Head, usePage } from "@inertiajs/react";
import ConsoleLayout from "@/Layouts/ConsoleLayout";
import ProfileView, {
  type ProfileHub,
  type ProfileWorkspace,
} from "@/Components/Console/ProfileView";
import { useI18n } from "@/lib/i18n";
import type { AppPageProps } from "@/types";
interface Props {
  financialVisibility?:FinancialVisibilityData|null;
  kind: "students" | "teachers";
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
  };
  hub: ProfileHub;
  profileWorkspace: ProfileWorkspace;
  backUrl: string;
  editUrl: string | null;
  displayTimezone: string;
  availabilityUrl: string | null;
  lifecycle: LifecycleData;
  placement?: PlacementData | null;
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
          gender: person.gender ? t("console_people." + person.gender) : null,
          nationality: person.nationality,
        }}
        hub={hub}
        workspace={profileWorkspace}
        timezone={displayTimezone}
        backUrl={backUrl}
        editUrl={editUrl}
        availabilityUrl={availabilityUrl}
        followupUrl={
          kind === "students" && followup
            ? followup.href +
              "?" +
              new URLSearchParams({ filter: "directory", search: person.code })
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
      {placement && <PlacementActions placement={placement} />}
      <LifecycleActions lifecycle={lifecycle} />
    </ConsoleLayout>
  );
}
