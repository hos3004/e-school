export interface RegistrationCourse {
  id: string;
  name: string;
  program_id: string;
  program_name: string;
}
export interface RegistrationQuestion {
  id: string | null;
  question: string;
  type: string;
  options: string[];
  is_required: boolean;
  is_active: boolean;
  is_filterable: boolean;
}
export interface RegistrationFormData {
  id: string | null;
  title: string;
  description: string;
  slug: string;
  is_active: boolean;
  preferred_program_id: string | null;
  preferred_course_id: string | null;
  questions: RegistrationQuestion[];
  public_url?: string | null;
  applications_count?: number;
}
export interface RegistrationApplication {
  id: string;
  full_name: string;
  phone: string | null;
  email: string | null;
  country_id: string;
  region_id: string;
  date_of_birth: string;
  age: number;
  gender: string;
  notes: string | null;
  preferred_program_id: string | null;
  preferred_course_id: string | null;
  student_profile_id: string | null;
  user_id: string | null;
  status: string;
  submitted_at: string | null;
  source: string;
  registration_form_id: string | null;
  student_code: string | null;
  decision_reason: string | null;
  duplicate_of_application_id: string | null;
  can_review: boolean;
  can_decide: boolean;
  can_place: boolean;
  placement_url?: string | null;
  answers: {
    question_id: string;
    question: string;
    type?: string;
    answer?: string | string[];
  }[];
}
export interface RegistrationPage {
  data: RegistrationApplication[];
  total: number;
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
}
export interface Option {
  id: string;
  name: string;
}
