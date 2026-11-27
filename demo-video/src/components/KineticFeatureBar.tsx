import React from 'react';
import { interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { COLORS, FONTS, MOTION, SHADOWS } from '../theme/tokens';

export type FeaturePill = {
  readonly label: string;
  readonly icon: React.ReactNode;
  readonly active?: boolean;
};

type KineticFeatureBarProps = {
  readonly items: FeaturePill[];
  readonly activeIndex?: number;
  readonly top?: number;
};

/**
 * Inspired by Example of Motion Graphics.mp4 top feature strip:
 * crisp staggered pills with active brand highlight above the full-stage UI frame.
 */
export const KineticFeatureBar: React.FC<KineticFeatureBarProps> = ({
  items,
  activeIndex = 0,
  top = 92,
}) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  return (
    <div
      style={{
        position: 'absolute',
        top,
        left: 88,
        right: 88,
        display: 'flex',
        justifyContent: 'center',
        gap: 16,
        zIndex: 28,
      }}
    >
      {items.map((item, idx) => {
        const start = idx * Math.round(0.1 * fps);
        const end = start + Math.round(0.32 * fps);
        const opacity = interpolate(frame, [start, end], [0, 1], {
          extrapolateLeft: 'clamp',
          extrapolateRight: 'clamp',
          easing: MOTION.enter,
        });
        const y = interpolate(frame, [start, end], [16, 0], {
          extrapolateLeft: 'clamp',
          extrapolateRight: 'clamp',
          easing: MOTION.enter,
        });
        const isActive = idx === activeIndex || item.active;

        return (
          <div
            key={item.label}
            style={{
              opacity,
              translate: `0px ${y}px`,
              display: 'inline-flex',
              alignItems: 'center',
              gap: 10,
              padding: '10px 20px',
              borderRadius: 999,
              backgroundColor: isActive ? COLORS.brandPrimary : COLORS.surfaceWhite,
              color: isActive ? '#FFFFFF' : COLORS.darkSlate,
              border: isActive
                ? `1px solid ${COLORS.brandPrimary}`
                : '1px solid rgba(37, 37, 120, 0.14)',
              boxShadow: isActive ? SHADOWS.level3 : SHADOWS.level1,
              fontFamily: FONTS.primary,
              fontSize: 14,
              fontWeight: 600,
            }}
          >
            {item.icon}
            <span>{item.label}</span>
          </div>
        );
      })}
    </div>
  );
};
