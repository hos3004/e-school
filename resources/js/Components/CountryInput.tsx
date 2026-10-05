import {
  useEffect,
  useId,
  useRef,
  useState,
  type InputHTMLAttributes,
} from "react";
import { useI18n } from "@/lib/i18n";

export type CountryOption = { value: string; label: string; iso2?: string };

type Props = Omit<
  InputHTMLAttributes<HTMLInputElement>,
  "value" | "onChange" | "list"
> & {
  options: readonly CountryOption[];
  value: string;
  onChange: (value: string) => void;
};

function normalized(value: string): string {
  return value
    .trim()
    .normalize("NFKD")
    .replace(/[\u064b-\u065f\u0670\u0640]/g, "")
    .toLocaleLowerCase("ar");
}

/** Typing stays human-readable; only a canonical country ID reaches the form. */
export default function CountryInput({
  options,
  value,
  onChange,
  id,
  ...inputProps
}: Props) {
  const t = useI18n();
  const input = useRef<HTMLInputElement>(null);
  const [query, setQuery] = useState(
    options.find((option) => option.value === value)?.label ?? "",
  );
  const expectedValue = useRef(value);
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const listId = inputId + "-countries";

  useEffect(() => {
    // A parent reset clears the label; local incomplete typing stays visible.
    if (value !== expectedValue.current) {
      setQuery(options.find((option) => option.value === value)?.label ?? "");
      expectedValue.current = value;
    }
  }, [value, options]);

  useEffect(() => {
    input.current?.setCustomValidity(
      query.trim() !== "" && value === ""
        ? t("console_people.country_choose_valid")
        : "",
    );
  }, [query, value, t]);

  return (
    <>
      <input
        {...inputProps}
        ref={input}
        id={inputId}
        type="text"
        list={listId}
        autoComplete="country-name"
        placeholder={t("console_people.country_search")}
        value={query}
        onChange={(event) => {
          const next = event.target.value;
          setQuery(next);
          const match = options.find(
            (option) =>
              normalized(option.label) === normalized(next) ||
              (option.iso2 && normalized(option.iso2) === normalized(next)),
          );
          expectedValue.current = match?.value ?? "";
          onChange(expectedValue.current);
        }}
        onBlur={() => {
          if (value)
            setQuery(
              options.find((option) => option.value === value)?.label ?? query,
            );
        }}
      />
      <datalist id={listId}>
        {options.map((option) => (
          <option key={option.value} value={option.label} />
        ))}
      </datalist>
    </>
  );
}
