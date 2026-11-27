import React from 'react';
import { interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { COLORS, FONTS, MOTION, SHADOWS } from '../theme/tokens';

type FloatingChipProps = {
  readonly label: string;
  readonly detail: string;
  readonly badge?: string;
  readonly tone?: 'brand' | 'accent' | 'success' | 'warning' | 'error';
  readonly icon?: React.ReactNode;
  readonly right?: number;
  readonly top?: number;
  readonly delayFrames?: number;
};

const TONE = {
  brand: { stripe: COLORS.brandPrimary, bg: COLORS.brandBg, text: COLORS.brandPrimary },
  accent: { stripe: COLORS.brandAccent, bg: COLORS.brandBg, text: COLORS.brandAccent },
  success: { stripe: COLORS.success, bg: COLORS.successBg, text: COLORS.success },
  warning: { stripe: COLORS.warning, bg: COLORS.warningBg, text: COLORS.warning },
  error: { stripe: COLORS.error, bg: COLORS.errorBg, text: COLORS.error },
} as const;

/** Compact toast-like floating callout that overlays corner without squeezing UI stage */
export const FloatingChip: React.FC<FloatingChipProps> = ({
  label,
  detail,
  badge,
  tone = 'accent',
  icon,
  right = 112,
  top = 118,
  delayFrames = 14,
}) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const f = Math.max(0, frame - delayFrames);

  const opacity = interpolate(f, [0, Math.round(0.24 * fps)], [0, 1], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
    easing: MOTION.enter,
  });
  const x = interpolate(f, [0, Math.round(0.36 * fps)], [32, 0], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
    easing: MOTION.overshoot,
  });

  const c = TONE[tone];

  return (
    <div
      style={{
        position: 'absolute',
        right,
        top,
        maxWidth: 410,
        padding: '12px 18px',
        borderRadius: 14,
        backgroundColor: 'rgba(255, 255, 255, 0.96)',
        border: '1px solid rgba(30, 41, 59, 0.08)',
        borderLeft: `4px solid ${c.stripe}`,
        boxShadow: SHADOWS.level3,
        opacity,
        translate: `${x}px 0px`,
        zIndex: 35,
      }}
    >
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          {icon}
          <span style={{ fontFamily: FONTS.primary, fontSize: 14, fontWeight: 700, color: COLORS.darkSlate }}>
            {label}
          </span>
        </div>
        {badge && (
          <span
            style={{
              padding: '2px 9px',
              borderRadius: 999,
              backgroundColor: c.bg,
              color: c.text,
              fontFamily: FONTS.primary,
              fontSize: 11,
              fontWeight: 600,
            }}
          >
            {badge}
          </span>
        )}
      </div>
      <p style={{ margin: '4px 0 0', fontFamily: FONTS.secondary, fontSize: 12, color: COLORS.mutedSlate }}>
        {detail}
      </p>
    </div>
  );
};
