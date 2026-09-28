import { useState } from 'react';
import { BoxDetail } from '@/components/popnow/BoxDetail';
import { BoxGrid, BoxGridLegend } from '@/components/popnow/BoxGrid';
import { SetList } from '@/components/popnow/SetList';
import { SetOdds } from '@/components/popnow/SetOdds';
import { usePopNow } from '../fixtures/usePopNow';
import { LabHeader, Specimen, SpecimenGrid } from '../Specimen';

export function PopNowLab() {
    const popNow = usePopNow();
    const [live, fresh, soldOut] = popNow.sets;
    const [selected, setSelected] = useState<number | null>(null);

    // One box per state for the detail specimens.
    const pick = (predicate: (b: (typeof live.boxes)[number]) => boolean) => live.boxes.find(predicate)!;
    const detailCases = [
        { label: 'Free, verified hint', box: pick((b) => b.state === 'available' && b.hints.some((h) => h.source === 'verified_api')) },
        { label: 'Free, competing user hints', box: pick((b) => b.hints.length > 1) },
        { label: 'Held for you', box: pick((b) => b.state === 'locked_mine') },
        { label: 'Locked by someone', box: pick((b) => b.state === 'locked_other') },
        { label: 'Sold, revealed', box: pick((b) => b.state === 'sold') },
        { label: 'Free, no hints', box: pick((b) => b.state === 'available' && b.hints.length === 0) },
    ];
    const handlers = {
        onLock: popNow.lockBox,
        onRelease: popNow.releaseBox,
        onReportHint: popNow.reportHint,
        onConfirmHint: popNow.confirmHint,
    };

    return (
        <div className="flex flex-col gap-12">
            <LabHeader
                title="POP NOW"
                description="Set picker, box grid (free / held for you / locked by someone / sold, with hint trust and live lock timers), box detail with lock and hint actions, and odds for what's left."
                source="components/popnow/"
            />

            <Specimen label="SetList" note="Free / locked / sold split; 'Secret in' while the secret is unrevealed">
                <SetList sets={popNow.sets} selectedId={selected ?? live.set.id} onSelect={(s) => setSelected(s.id)} />
            </Specimen>

            <div className="grid gap-8 lg:grid-cols-3">
                <Specimen label="BoxGrid: live set" note="Every state and hint type">
                    <BoxGrid set={live.set} boxes={live.boxes} />
                </Specimen>
                <Specimen label="BoxGrid: fresh set" note="All free, a couple of hints">
                    <BoxGrid set={fresh.set} boxes={fresh.boxes} />
                </Specimen>
                <Specimen label="BoxGrid: nearly sold out" note="Secret revealed">
                    <BoxGrid set={soldOut.set} boxes={soldOut.boxes} />
                </Specimen>
            </div>

            <Specimen label="Legend">
                <BoxGridLegend />
            </Specimen>

            <section className="flex flex-col gap-6">
                <h2 className="text-lg font-bold">BoxDetail</h2>
                <SpecimenGrid>
                    {detailCases.map(({ label, box }) => (
                        <Specimen key={label} label={label}>
                            <BoxDetail set={live.set} box={box} {...handlers} />
                        </Specimen>
                    ))}
                </SpecimenGrid>
            </section>

            <div className="grid gap-8 lg:grid-cols-2">
                <Specimen label="SetOdds: live set">
                    <SetOdds set={live.set} boxes={live.boxes} />
                </Specimen>
                <Specimen label="SetOdds: nearly sold out" note="Gone figures greyed out">
                    <SetOdds set={soldOut.set} boxes={soldOut.boxes} />
                </Specimen>
            </div>
        </div>
    );
}
