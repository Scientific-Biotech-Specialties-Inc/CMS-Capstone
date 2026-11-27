import React from 'react';
import { Building2, FileText, ShieldCheck, FileSignature } from 'lucide-react';
import { interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { BrandLockup } from '../components/BrandLockup';
import type { SceneProps } from '../components/EditedScene';
import { KineticTitle } from '../components/KineticTitle';
import { SaaSStage } from '../components/SaaSStage';
import { SceneAudioAndCaptions } from '../components/SceneAudioAndCaptions';
import { SfxCue } from '../components/SfxCue';
import { DEMO_SCENES } from '../script/scenes';
import { COLORS, FONTS, MOTION, SHADOWS } from '../theme/tokens';

const STEPS = [
  { title: 'Vendors', icon: Building2, time: 12.8 },
  { title: 'Contracts', icon: FileText, time: 13.7 },
  { title: 'Risk Review', icon: ShieldCheck, time: 14.35 },
  { title: 'Approvals', icon: FileSignature, time: 15.1 },
];

export const Scene01Intro: React.FC<SceneProps> = ({ voiceoverFile, captions }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const titleEnd = Math.round(5.2 * fps);
  const titleOpacity = interpolate(frame, [titleEnd - 10, titleEnd], [1, 0], {
    extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: MOTION.exit,
  });

  return (
    <SaaSStage variant="brand">
      <div style={{ position: 'absolute', left: 112, right: 112, top: 304, opacity: titleOpacity }}>
        <KineticTitle size={80} color={COLORS.surfaceWhite}>Built for SBSI</KineticTitle>
        <KineticTitle size={42} color={COLORS.brandBg} delay={8}>Diagnostic and industrial laboratories</KineticTitle>
      </div>
      <div style={{ position: 'absolute', left: 112, top: 248 }}>
        <BrandLockup variant="hero" delayFrames={titleEnd} />
        <SfxCue name="whoosh" fromFrame={titleEnd} volume={0.24} />
      </div>
      <div style={{
        position: 'absolute', left: 112, right: 112, top: 552,
        display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 24,
      }}>
        {STEPS.map(({ title, icon: Icon, time }) => {
          const start = Math.round(time * fps);
          const progress = interpolate(frame, [start, start + 14], [0, 1], {
            extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: MOTION.enter,
          });
          return (
            <div key={title} style={{
              background: COLORS.surfaceWhite, borderRadius: 16, padding: 24,
              display: 'flex', alignItems: 'center', gap: 16,
              boxShadow: SHADOWS.level3, color: COLORS.brandPrimary,
              opacity: progress, translate: `0 ${(1 - progress) * 48}px`,
              fontFamily: FONTS.primary, fontWeight: 700, fontSize: 28,
            }}>
              <Icon size={32} />
              {title}
            </div>
          );
        })}
      </div>
      <SceneAudioAndCaptions voiceoverFile={voiceoverFile} captions={captions} fallbackText={DEMO_SCENES[0].narration} darkBackground />
    </SaaSStage>
  );
};
