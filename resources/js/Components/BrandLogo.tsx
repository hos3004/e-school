interface BrandLogoProps {
  label: string;
  className?: string;
}

/** Display only the original academy mark from the supplied institution artwork. */
export default function BrandLogo({ label, className = "block h-auto w-full" }: BrandLogoProps) {
  return (
    <svg
      viewBox="505 40 402 145"
      preserveAspectRatio="xMidYMid slice"
      width="402"
      height="145"
      className={className}
      role="img"
      aria-label={label}
      focusable="false"
      style={{ overflow: "hidden" }}
    >
      <image href="/images/academy-brand.png" width="927" height="220" aria-hidden="true" />
    </svg>
  );
}
