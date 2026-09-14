import type { ReactNode } from "react";

const paths: Record<string, ReactNode> = {
  message: (
    <>
      <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9.9 9.9 0 0 1-4-.9L3 21l1.9-4.6A8.4 8.4 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5Z" />
    </>
  ),
  check: (
    <>
      <path d="m3 12 5 5L20 5" />
      <path d="m13 16 2 2 7-8" />
    </>
  ),
  live: (
    <>
      <circle cx="12" cy="12" r="2" />
      <path d="M7 7a7 7 0 0 0 0 10m10-10a7 7 0 0 1 0 10M4 4a11 11 0 0 0 0 16m16-16a11 11 0 0 1 0 16" />
    </>
  ),
  directory: (
    <>
      <rect x="3" y="3" width="7" height="7" rx="1" />
      <rect x="14" y="3" width="7" height="7" rx="1" />
      <rect x="3" y="14" width="7" height="7" rx="1" />
      <rect x="14" y="14" width="7" height="7" rx="1" />
    </>
  ),
  placement: (
    <>
      <path d="M4 5h16v15H4zM4 10h16M10 5v15" />
      <path d="M7 2v5m10-5v5" />
    </>
  ),
  portal: (
    <>
      <rect x="3" y="3" width="18" height="14" rx="2" />
      <path d="M8 21h8m-4-4v4" />
    </>
  ),

  today: (
    <>
      <rect x="3" y="4" width="18" height="16" rx="2" />
      <path d="M3 9h18" />
    </>
  ),
  courses: (
    <>
      <path d="M12 7c-3-3-7-3-10-2v14c3-1 7-1 10 2 3-3 7-3 10-2V5c-3-1-7-1-10 2Z" />
      <path d="M12 7v14" />
    </>
  ),
  quran: (
    <>
      <path d="M12 7c-3-3-7-3-10-2v14c3-1 7-1 10 2 3-3 7-3 10-2V5c-3-1-7-1-10 2Z" />
      <path d="M12 7v14" />
    </>
  ),
  students: (
    <>
      <circle cx="9" cy="8" r="3" />
      <path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M21 21v-2a6 6 0 0 0-4-5.65" />
    </>
  ),
  teachers: (
    <>
      <path d="m2 9 10-5 10 5-10 5L2 9Zm4 2v6c4 3 8 3 12 0v-6M22 9v7" />
    </>
  ),
  groups: (
    <>
      <path d="m12 3 10 5-10 5L2 8l10-5Zm-10 9 10 5 10-5M2 16l10 5 10-5" />
    </>
  ),
  reports: (
    <>
      <path d="M3 3v18h18M8 16v-3M13 16V8M18 16v-6" />
    </>
  ),
  followup: (
    <>
      <path d="M12 3 2 21h20L12 3Z" />
      <path d="M12 9v5m0 3h.01" />
    </>
  ),
  registration: (
    <>
      <path d="M9 4H5a2 2 0 0 0-2 2v14h18V6a2 2 0 0 0-2-2h-4" />
      <rect x="9" y="2" width="6" height="4" rx="1" />
      <path d="M7 11h10m-10 5h7" />
    </>
  ),
  teacher_dues: (
    <>
      <rect x="2" y="4" width="20" height="16" rx="2" />
      <path d="M2 8h20m-5 5h3m-13 3h5" />
    </>
  ),
  settings: (
    <>
      <path d="M4 4v5m0 4v7M12 4v9m0 4v3M20 4v2m0 4v10M1 9h6m2 8h6m2-11h6" />
    </>
  ),
};
export default function ConsoleIcon({ name }: { name: string }) {
  return (
    <svg
      className="console-nav-icon"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.7"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      {paths[name] ?? paths.today}
    </svg>
  );
}
