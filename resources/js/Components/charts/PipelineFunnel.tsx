import { angka } from "@/lib/format";

export type PipelineStage = {
    stage: string;
    label: string;
    value: number;
};

// Tahapan seleksi punya urutan, jadi warnanya ramp ordinal satu hue
// (terang -> gelap mengikuti kemajuan tahap).
const STEPS = [
    "var(--color-stage-1)",
    "var(--color-stage-2)",
    "var(--color-stage-3)",
    "var(--color-stage-4)",
    "var(--color-stage-5)",
];

export default function PipelineFunnel({
    stages,
}: {
    stages: PipelineStage[];
}) {
    const max = Math.max(...stages.map((stage) => stage.value), 1);

    return (
        <ul className="space-y-3">
            {stages.map((stage, index) => (
                <li key={stage.stage} className="flex items-center gap-3">
                    <span className="w-20 shrink-0 text-xs text-ink-soft">
                        {stage.label}
                    </span>
                    <span className="flex min-w-0 flex-1 items-center gap-2">
                        <span
                            className="h-5 rounded-r"
                            style={{
                                width: `${Math.max((stage.value / max) * 100, 2)}%`,
                                background: STEPS[index] ?? STEPS[STEPS.length - 1],
                            }}
                        />
                        {/* Nilai selalu ditulis: step paling terang di bawah 3:1 */}
                        <span className="tabular shrink-0 text-xs font-medium text-ink">
                            {angka(stage.value)}
                        </span>
                    </span>
                </li>
            ))}
        </ul>
    );
}
