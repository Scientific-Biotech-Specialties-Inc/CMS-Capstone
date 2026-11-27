import React from 'react';
import { interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { BrandLockup } from '../components/BrandLockup';
import type { SceneProps } from '../components/EditedScene';
import { KineticTitle } from '../components/KineticTitle';
import { SaaSStage } from '../components/SaaSStage';
import { SceneAudioAndCaptions } from '../components/SceneAudioAndCaptions';
import { SfxCue } from '../components/SfxCue';
import { DEMO_SCENES } from '../script/scenes';
import { COLORS, MOTION } from '../theme/tokens';

export const Scene13Outro: React.FC<SceneProps> = ({ voiceoverFile, captions }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const resolveAt = Math.round(4.1 * fps);
  const opacity = interpolate(frame, [resolveAt - 12, resolveAt], [1, 0], {
    extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: MOTION.exit,
  });

  return (
    <SaaSStage variant="brand">
      <div style={{ position: 'absolute', left: 112, top: 288, opacity }}>
        <KineticTitle size={76} color={COLORS.surfaceWhite}>Every vendor. Every clause.</KineticTitle>
        <KineticTitle size={76} color={COLORS.brandBg} delay={Math.round(fps * 1.65)}>
          Every approval and signature.
        </KineticTitle>
      </div>
      <div style={{ position: 'absolute', inset: '0 112px 80px', display: 'grid', placeItems: 'center' }}>
        <BrandLockup variant="hero" delayFrames={resolveAt} />
      </div>
      <SfxCue name="pop" fromFrame={resolveAt + 9} volume={0.2} />
      <SceneAudioAndCaptions voiceoverFile={voiceoverFile} captions={captions} fallbackText={DEMO_SCENES[12].narration} darkBackground />
    </SaaSStage>
  );
};
