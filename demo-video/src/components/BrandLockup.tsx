import React from 'react';
import { Img, interpolate, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { FONTS, MOTION } from '../theme/tokens';

type BrandLockupProps = {
  readonly variant?: 'full' | 'hero' | 'compact';
  readonly delayFrames?: number;
};

export const BrandLockup: React.FC<BrandLockupProps> = ({
  variant = 'full',
  delayFrames = 0,
}) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const f = Math.max(0, frame - delayFrames);

  const logoScale = interpolate(f, [0, Math.round(0.45 * fps)], [0.74, 1], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
    easing: MOTION.overshoot,
  });
  const logoOpacity = interpolate(f, [0, Math.round(0.28 * fps)], [0, 1], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });
  const textX = interpolate(f, [Math.round(0.14 * fps), Math.round(0.52 * fps)], [28, 0], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
    easing: MOTION.enter,
  });
  const textOpacity = interpolate(f, [Math.round(0.14 * fps), Math.round(0.46 * fps)], [0, 1], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });

  const dims =
    variant === 'hero'
      ? { boxW: 276, boxH: 132, pad: '18px 28px', radius: 26, gap: 36, sub: 20, title: 70, suffix: 52 }
      : variant === 'full'
        ? { boxW: 216, boxH: 104, pad: '14px 22px', radius: 22, gap: 28, sub: 16, title: 54, suffix: 40 }
        : { boxW: 112, boxH: 52, pad: '8px 12px', radius: 12, gap: 16, sub: 12, title: 28, suffix: 20 };

  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: dims.gap }}>
      <div
        style={{
          width: dims.boxW,
          height: dims.boxH,
          borderRadius: dims.radius,
          backgroundColor: 'rgba(255,255,255,0.98)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: dims.pad,
          boxShadow: '0 24px 48px rgba(0,0,0,0.28)',
          opacity: logoOpacity,
          scale: String(logoScale),
        }}
      >
        <Img
          src={staticFile('brand/sbsi-logo.png')}
          style={{ width: '100%', height: '100%', objectFit: 'contain' }}
        />
      </div>

      <div
        style={{
          display: 'flex',
          flexDirection: 'column',
          opacity: textOpacity,
          translate: `${textX}px 0px`,
        }}
      >
        <span
          style={{
            fontFamily: FONTS.secondary,
            fontSize: dims.sub,
            fontWeight: 500,
            letterSpacing: '0.12em',
            textTransform: 'uppercase',
            color: 'rgba(255,255,255,0.82)',
            marginBottom: 6,
          }}
        >
          Scientific Biotech Specialties, Inc.
        </span>
        <span
          style={{
            fontFamily: FONTS.primary,
            fontSize: dims.title,
            fontWeight: 700,
            lineHeight: 1.03,
            letterSpacing: '0.02em',
            textTransform: 'uppercase',
            color: '#FFFFFF',
          }}
        >
          CONTRACT
        </span>
        <span
          style={{
            fontFamily: FONTS.primary,
            fontSize: dims.suffix,
            fontWeight: 400,
            lineHeight: 1.05,
            letterSpacing: 0,
            color: 'rgba(255,255,255,0.92)',
          }}
        >
          MANAGEMENT SYSTEM
        </span>
      </div>
    </div>
  );
};
