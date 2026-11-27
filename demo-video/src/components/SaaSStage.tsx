import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { COLORS, FONTS } from '../theme/tokens';

type SaaSStageProps = {
  readonly variant?: 'light' | 'brand';
  readonly children: React.ReactNode;
};

export const SaaSStage: React.FC<SaaSStageProps> = ({ variant = 'light', children }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const drift = interpolate(frame, [0, 20 * fps], [-32, 48], { extrapolateRight: 'clamp' });
  const brand = variant === 'brand';

  return (
    <AbsoluteFill style={{
      background: brand
        ? `linear-gradient(135deg, ${COLORS.brandPrimary}, ${COLORS.brandAccent})`
        : COLORS.pageBg,
      fontFamily: FONTS.primary,
      overflow: 'hidden',
    }}>
      <div style={{
        position: 'absolute', top: -280, right: -176, width: 960, height: 960,
        borderRadius: '50%', translate: `${drift}px 0`,
        background: `radial-gradient(circle, rgba(46,133,216,${brand ? 0.36 : 0.11}), transparent 72%)`,
      }} />
      {children}
    </AbsoluteFill>
  );
};
