import assert from "node:assert/strict";
import { Buffer } from "node:buffer";
import { readFileSync } from "node:fs";
import test from "node:test";
import { URL } from "node:url";
import ts from "typescript";

const { outputText } = ts.transpileModule(
    readFileSync(new URL("./session-drafts.ts", import.meta.url), "utf8"),
    {
        compilerOptions: {
            module: ts.ModuleKind.ESNext,
            target: ts.ScriptTarget.ES2022,
        },
    },
);
const {
    rememberSessionDraft,
    restoreSessionDraft,
    clearSessionDraft,
    attendanceChanges,
} = await import(
    `data:text/javascript;base64,${Buffer.from(outputText).toString("base64")}`
);
const rows = [
    { studentId: "a", status: "pending" },
    { studentId: "b", status: "pending" },
];
const attendance = {
    statuses: { a: "present", b: "absent" },
    reason: "تصحيح موثق",
};
const report = {
    summary: "مسودة الحصة",
    notes: "ملاحظة",
    students: [
        {
            student_profile_id: "a",
            participation: "4",
            performance: "3",
            commitment: "5",
        },
        {
            student_profile_id: "b",
            participation: "2",
            performance: "1",
            commitment: "3",
        },
    ],
};

test("a fresh visit after the student profile restores both drafts", () => {
    rememberSessionDraft("teacher-one", "lesson-one", attendance, report);
    const restored = restoreSessionDraft("teacher-one", "lesson-one", rows);
    assert.deepEqual(restored, { attendance, report });
});
test("roster reorder retains scores by student identity, drops removed students, and leaves newcomers blank", () => {
    rememberSessionDraft("teacher-two", "lesson-one", attendance, report);
    const restored = restoreSessionDraft("teacher-two", "lesson-one", [
        rows[1],
        { studentId: "new", status: "pending" },
    ]);
    assert.deepEqual(restored.attendance.statuses, {
        b: "absent",
        new: "pending",
    });
    assert.deepEqual(restored.report.students, [
        report.students[1],
        {
            student_profile_id: "new",
            participation: "",
            performance: "",
            commitment: "",
        },
    ]);
});
test("drafts do not cross a lesson or authenticated account", () => {
    rememberSessionDraft("teacher-three", "lesson-one", attendance, report);
    assert.equal(
        restoreSessionDraft("teacher-three", "lesson-two", rows).report.summary,
        "",
    );
    assert.equal(
        restoreSessionDraft("different-teacher", "lesson-one", rows).report
            .summary,
        "",
    );
    assert.equal(
        restoreSessionDraft("teacher-three", "lesson-one", rows).report.summary,
        "",
    );
});
test("successful attendance save clears only attendance and keeps the unsent report", () => {
    rememberSessionDraft("teacher-four", "lesson-one", attendance, report);
    clearSessionDraft("teacher-four", "lesson-one", "attendance");
    const restored = restoreSessionDraft("teacher-four", "lesson-one", rows);
    assert.deepEqual(restored.attendance.statuses, {
        a: "pending",
        b: "pending",
    });
    assert.deepEqual(restored.report, report);
    clearSessionDraft("teacher-four", "lesson-one", "report");
    assert.equal(
        restoreSessionDraft("teacher-four", "lesson-one", rows).report.summary,
        "",
    );
});
test("snapshot cannot be changed by later form object mutations", () => {
    const source = JSON.parse(JSON.stringify(report));
    rememberSessionDraft("teacher-five", "lesson-one", attendance, source);
    source.students[0].performance = "1";
    assert.equal(
        restoreSessionDraft("teacher-five", "lesson-one", rows).report
            .students[0].performance,
        "3",
    );
});
test("attendance sends changed or unconfirmed rows without resubmitting unchanged confirmed rows", () => {
    const confirmedRows = [
        {
            studentId: "a",
            status: "present",
            confirmedAt: "2026-09-06T10:00:00Z",
        },
        {
            studentId: "b",
            status: "absent",
            confirmedAt: "2026-09-06T10:00:00Z",
        },
        { studentId: "c", status: "present", confirmedAt: null },
        { studentId: "d", status: "pending", confirmedAt: null },
    ];
    assert.deepEqual(
        attendanceChanges(confirmedRows, {
            a: "present",
            b: "late",
            c: "present",
            d: "pending",
            outsider: "present",
        }),
        { b: "late", c: "present" },
    );
});
