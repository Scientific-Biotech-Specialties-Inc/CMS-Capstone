import React from 'react';
import type { Caption } from '@remotion/captions';
import { Sequence, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { EDITS, type Shot } from '../script/shots';
import { DEMO_SCENES } from '../script/scenes';
import { COLORS, FONTS, MOTION } from '../theme/tokens';
import { BrowserFrame, BrowserShot } from './BrowserFrame';
import { KineticTitle } from './KineticTitle';
import { SaaSStage } from './SaaSStage';
import { SceneAudioAndCaptions } from './SceneAudioAndCaptions';
import { SfxCue } from './SfxCue';

export type SceneProps = {
  readonly voiceoverFile?: string;
  readonly captions?: Caption[];
  readonly sourceEnd?: number;
};

const ShotView: React.FC<{ readonly shot: Shot; readonly clip?: string; readonly sourceEnd?: number }> = ({
  shot, clip, sourceEnd,
}) => {
  const frame = useCurrentFrame();
  const { fps, durationInFrames } = useVideoConfig();
  const cue = shot.cue;
  const cueFrame = cue ? Math.round((cue.sourceTime - shot.source[0]) / (shot.rate ?? 1) * fps) : -1;
  const showCue = clip && cue && cueFrame >= 0 && cueFrame < durationInFrames && cue.sourceTime < (sourceEnd ?? Infinity);
  const step = shot.steps?.filter(({ at }) => frame >= Math.round(at * fps)).at(-1);
  const stepStart = Math.round((step?.at ?? 0) * fps);
  const detailProgress = interpolate(frame, [stepStart, stepStart + 12], [0, 1], {
    extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: MOTION.enter,
  });

  return (
    <>
      <BrowserShot shot={shot} clip={clip} sourceEnd={sourceEnd} />
      <div style={{
        position: 'absolute', top: -72, left: 0, right: 0, height: 64, overflow: 'hidden',
        display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 24,
      }}>
        <KineticTitle color={COLORS.brandPrimary} size={44}>{shot.title}</KineticTitle>
        {(step || shot.detail) && (
          <div style={{
            flexShrink: 0, maxWidth: 560, borderLeft: `4px solid ${COLORS.brandAccent}`,
            padding: '8px 16px', background: COLORS.brandBg, borderRadius: 8,
            fontFamily: FONTS.secondary, fontSize: 22, lineHeight: 1.2, fontWeight: 600,
            color: COLORS.brandPrimary, opacity: detailProgress, translate: `0 ${(1 - detailProgress) * 12}px`,
          }}>
            {step?.label ?? shot.detail}
          </div>
        )}
      </div>
      {shot.reveal === 'scan' && <SfxCue name="whoosh" fromFrame={8} volume={0.1} />}
      {shot.at > 0 && (shot.reveal === 'outline' || shot.reveal === 'chart') && (
        <SfxCue name="pop" fromFrame={Math.round(fps * 0.18)} volume={0.14} />
      )}
      {showCue && <SfxCue name={cue.sound} fromFrame={cueFrame} volume={0.26} />}
    </>
  );
};

export const EditedScene: React.FC<SceneProps & { readonly id: string }> = ({
  id, sourceEnd, voiceoverFile, captions,
}) => {
  const { fps, durationInFrames } = useVideoConfig();
  const edit = EDITS[id];
  const narration = DEMO_SCENES.find((scene) => scene.id === id)?.narration ?? '';

  return (
    <SaaSStage>
      <BrowserFrame milestones={edit.milestones}>
        {edit.shots.map((shot, index) => {
          const from = Math.round(shot.at * fps);
          const next = edit.shots[index + 1];
          const end = Math.min(durationInFrames, next ? Math.round(next.at * fps) : durationInFrames);
          if (end <= from) return null;
          const clip = sourceEnd !== undefined && shot.source[0] < sourceEnd - 2 / fps
            ? `clips/${edit.clip}`
            : undefined;

          return (
            <Sequence key={shot.at} name={shot.title} from={from} durationInFrames={end - from} premountFor={Math.round(0.75 * fps)}>
              <ShotView shot={shot} clip={clip} sourceEnd={sourceEnd} />
            </Sequence>
          );
        })}
      </BrowserFrame>
      <SceneAudioAndCaptions voiceoverFile={voiceoverFile} captions={captions} fallbackText={narration} />
    </SaaSStage>
  );
};
