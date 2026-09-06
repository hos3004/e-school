import type { ReactNode } from "react";
const paths: Record<string, ReactNode> = {
  calendar: (
    <>
      <rect x="3" y="5" width="18" height="16" rx="2" />
      <path d="M16 3v4M8 3v4M3 11h18" />
    </>
  ),
  book: (
    <>
      <path d="M12 7C9 4 5 4 2 5v14c3-1 7-1 10 2 3-3 7-3 10-2V5c-3-1-7-1-10 2ZM12 7v14" />
    </>
  ),
  shield: (
    <>
      <path d="m12 3 9 4v6c0 5-9 9-9 9s-9-4-9-9V7l9-4Z" />
      <path d="m8 12 3 3 5-6" />
    </>
  ),
  teacher: (
    <>
      <path d="m2 9 10-5 10 5-10 5L2 9Zm4 2v6c4 3 8 3 12 0v-6M22 9v7" />
    </>
  ),
  user: (
    <>
      <circle cx="12" cy="8" r="4" />
      <path d="M4 21v-2a8 8 0 0 1 16 0v2" />
    </>
  ),
  clock: (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 7v5l3 2" />
    </>
  ),
  arrow: <path d="m10 6-6 6 6 6M4 12h16" />,
  file: (
    <>
      <path d="M14 2H5v20h14V7l-5-5ZM14 2v5h5M8 12h8M8 16h6" />
    </>
  ),
  check: (
    <>
      <path d="m8 12 3 3 6-7" />
      <rect x="3" y="3" width="18" height="18" rx="4" />
    </>
  ),
  play: <path d="m8 4 12 8-12 8V4Z" />,
  wallet: (
    <>
      <rect x="3" y="5" width="18" height="16" rx="2" />
      <path d="M3 9h18M16 14h5M3 5l13-3v3" />
    </>
  ),
  bell: (
    <>
      <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
    </>
  ),
  close: <path d="m6 6 12 12M6 18 18 6" />,
  eye: (
    <>
      <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z" />
      <circle cx="12" cy="12" r="3" />
    </>
  ),
  lock: (
    <>
      <rect x="4" y="10" width="16" height="12" rx="2" />
      <path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v4" />
    </>
  ),
};
export default function Icon({
  name,
  size = 20,
}: {
  name: string;
  size?: number;
}) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.7"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      {paths[name] ?? paths.book}
    </svg>
  );
}
