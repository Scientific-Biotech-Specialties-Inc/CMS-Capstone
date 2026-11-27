import React from 'react';
import { interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { COLORS, MOTION } from '../theme/tokens';
import { SfxCue } from './SfxCue';

type CursorClickProps = {
  readonly fromX: number;
  readonly fromY: number;
  readonly toX: number;
  readonly toY: number;
  readonly startFrame?: number;
  readonly clickFrame?: number;
  readonly exitFrame?: number;
  readonly playSfx?: boolean;
};

export const CursorClick: React.FC<CursorClickProps> = ({
  fromX,
  fromY,
  toX,
  toY,
  startFrame = 15,
  clickFrame = 42,
  exitFrame = clickFrame + 18,
  playSfx = true,
}) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const moveEnd = Math.max(startFrame + 8, clickFrame - 6);
  const x = interpolate(frame, [startFrame, moveEnd], [fromX, toX], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
    easing: MOTION.move,
  });
  const arcOffset = interpolate(frame, [startFrame, (startFrame + moveEnd) / 2, moveEnd], [0, -28, 0], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });
  const y =
    interpolate(frame, [startFrame, moveEnd], [fromY, toY], {
      extrapolateLeft: 'clamp',
      extrapolateRight: 'clamp',
      easing: MOTION.move,
    }) + arcOffset;

  const opacity = interpolate(frame, [startFrame - 6, startFrame, exitFrame - 6, exitFrame], [0, 1, 1, 0], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });

  // Click press anticipation + release
  const pressScale = interpolate(frame, [clickFrame - 2, clickFrame, clickFrame + 6], [1, 0.84, 1], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });

  const rippleEnd = clickFrame + Math.round(0.45 * fps);
  const rippleScale = interpolate(frame, [clickFrame, rippleEnd], [0.2, 2.2], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
    easing: MOTION.enter,
  });
  const rippleOpacity = interpolate(frame, [clickFrame, rippleEnd], [0.75, 0], {
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });

  return (
    <>
      {playSfx && <SfxCue name="mouse-click" fromFrame={clickFrame} volume={0.48} />}
      <div
        style={{
          position: 'absolute',
          left: x,
          top: y,
          opacity,
          pointerEvents: 'none',
          zIndex: 50,
        }}
      >
        {frame >= clickFrame && frame <= rippleEnd && (
          <div
            style={{
              position: 'absolute',
              left: -18,
              top: -18,
              width: 40,
              height: 40,
              borderRadius: '50%',
              border: `3px solid ${COLORS.brandAccent}`,
              scale: String(rippleScale),
              opacity: rippleOpacity,
            }}
          />
        )}
        <svg
          width="28"
          height="28"
          viewBox="0 0 24 24"
          style={{
            scale: String(pressScale),
            filter: 'drop-shadow(0 4px 8px rgba(30,41,59,0.28))',
          }}
        >
          <path
            d="M4 3L20 11.5L12.5 13.5L9.5 21L4 3Z"
            fill={COLORS.brandPrimary}
            stroke="#FFFFFF"
            strokeWidth="2"
            strokeLinejoin="round"
          />
        </svg>
      </div>
    </>
  );
};
