import React from 'react';
import { interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { COLORS, FONTS, MOTION, SHADOWS } from '../theme/tokens';

type CalloutCardProps = {
  readonly title: string;
  readonly subtitle: string;
  readonly badge?: string;
  readonly tone?: 'brand' | 'accent' | 'success' | 'warning' | 'error';
  readonly icon?: React.ReactNode;
  readonly left?: number;
  readonly top?: number;
  readonly width?: number;
  readonly delayFrames?: number;
};

const TONE_MAP = {
  brand: { stripe: COLORS.brandPrimary, pillBg: COLORS.brandBg, pillText: COLORS.brandPrimary },
  accent: { stripe: COLORS.brandAccent, pillBg: COLORS.brandBg, pillText: COLORS.brandAccent },
  success: { stripe: COLORS.success, pillBg: COLORS.successBg, pillText: COLORS.success },
  warning: { stripe: COLORS.warning, pillBg: COLORS.warningBg, pillText: COLORS.warning },
  error: { stripe: COLORS.error, pillBg: COLORS.errorBg, pillText: COLORS.error },
} as const;

export const CalloutCard: React.FC<CalloutCardProps> = ({
  title,
  subtitle,
  badge,
  tone = 'accent',
  icon,
  left = 96,
  top = 180,
  width = 360,
  delayFrames = 12,
}) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const localFrame = Math.max(0, frame - delayFrames);
  const enterEnd = Math.round(0.42 * fps);

  const opacity = interpolate(localFrame, [0, Math.round(0.28 * fps)], [0, 1], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
    easing: MOTION.enter,
  });
  const translateY = interpolate(localFrame, [0, enterEnd], [18, 0], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
    easing: MOTION.enter,
  });
  const scale = interpolate(localFrame, [0, enterEnd], [0.94, 1], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
    easing: MOTION.overshoot,
  });

  const palette = TONE_MAP[tone];

  return (
    <div
      style={{
        position: 'absolute',
        left,
        top,
        width,
        padding: '16px 20px',
        borderRadius: 12,
        backgroundColor: COLORS.surfaceWhite,
        border: '1px solid rgba(30, 41, 59, 0.08)',
        borderLeft: `4px solid ${palette.stripe}`,
        boxShadow: SHADOWS.level3,
        opacity,
        translate: `0px ${translateY}px`,
        scale: String(scale),
        zIndex: 30,
      }}
    >
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          {icon}
          <span
            style={{
              fontFamily: FONTS.primary,
              fontSize: 15,
              fontWeight: 600,
              color: COLORS.darkSlate,
            }}
          >
            {title}
          </span>
        </div>
        {badge && (
          <span
            style={{
              padding: '3px 10px',
              borderRadius: 999,
              backgroundColor: palette.pillBg,
              color: palette.pillText,
              fontFamily: FONTS.primary,
              fontSize: 12,
              fontWeight: 600,
            }}
          >
            {badge}
          </span>
        )}
      </div>
      <p
        style={{
          margin: '8px 0 0',
          fontFamily: FONTS.secondary,
          fontSize: 13,
          fontWeight: 500,
          lineHeight: 1.45,
          color: COLORS.mutedSlate,
        }}
      >
        {subtitle}
      </p>
    </div>
  );
};
