import React from 'react';
import { interpolate, useCurrentFrame } from 'remotion';
import { FONTS, MOTION } from '../theme/tokens';

export const KineticTitle: React.FC<{
  readonly children: string;
  readonly color: string;
  readonly size: number;
  readonly delay?: number;
}> = ({ children, color, size, delay = 0 }) => {
  const frame = useCurrentFrame();
  const progress = interpolate(frame, [delay, delay + 14], [0, 1], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
    easing: MOTION.enter,
  });

  return (
    <div style={{ overflow: 'hidden', paddingBottom: 8 }}>
      <div style={{
        color, fontFamily: FONTS.primary, fontWeight: 700, fontSize: size,
        lineHeight: 1.14, opacity: progress, translate: `0 ${(1 - progress) * 32}px`,
      }}>
        {children}
      </div>
    </div>
  );
};
