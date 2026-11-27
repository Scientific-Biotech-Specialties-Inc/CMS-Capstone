import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { COLORS, MOTION } from '../theme/tokens';
import { SfxCue } from './SfxCue';

export const BrowserEntrance: React.FC<{ readonly children: React.ReactNode }> = ({ children }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const impact = Math.round(fps * 0.4);
  const end = Math.round(fps * 0.73);
  const clamp = { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' } as const;
  const open = interpolate(frame, [0, impact], [0, 1], { ...clamp, easing: MOTION.move });
  const settle = interpolate(frame, [impact, end], [0, 1], { ...clamp, easing: MOTION.move });
  const opacity = interpolate(frame, [0, Math.round(fps * 0.14)], [0, 1], clamp);
  const trail = interpolate(frame, [0, impact - 2, end], [0, 1, 0], clamp);

  return (
    <>
      <SfxCue name="whoosh" fromFrame={Math.round(fps * 0.05)} volume={0.18} />
      <SfxCue name="pop" fromFrame={impact} volume={0.28} />
      <AbsoluteFill style={{
        opacity,
        scale: 0.68 + 0.34 * open - 0.02 * settle,
        translate: `0 ${72 * (1 - open) - 6 * (open - settle)}px`,
        transformOrigin: '50% 44%',
      }}>
        {[1, 2].map((layer) => (
          <div key={layer} style={{
            position: 'absolute', inset: '120px 224px 152px', borderRadius: 24,
            background: layer === 1 ? COLORS.brandAccent : COLORS.brandPrimary,
            opacity: trail * (layer === 1 ? 0.28 : 0.15),
            translate: `0 ${layer * 18 * (1 - settle)}px`,
            scale: 1 + layer * 0.018 * trail,
          }} />
        ))}
        {children}
      </AbsoluteFill>
    </>
  );
};
