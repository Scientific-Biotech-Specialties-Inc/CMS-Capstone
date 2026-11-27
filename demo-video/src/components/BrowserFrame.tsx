import React from 'react';
import { Video } from '@remotion/media';
import { Lock } from 'lucide-react';
import { Freeze, Img, interpolate, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import type { Milestone, Shot } from '../script/shots';
import { COLORS, FONTS, MOTION, SHADOWS } from '../theme/tokens';
import { BrowserEntrance } from './BrowserEntrance';
import { CursorClick } from './CursorClick';

const SCALE = 1504 / 1920;
const CHROME_HEIGHT = 40;
const WIDTH = 1920 * SCALE;
const HEIGHT = 1080 * SCALE;

const Milestones: React.FC<{ readonly items: readonly Milestone[] }> = ({ items }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const active = items.filter(({ at }) => frame >= Math.round(at * fps)).length - 1;

  return (
    <div style={{ position: 'absolute', right: 16, top: 0, height: CHROME_HEIGHT, display: 'flex', gap: 16 }}>
      {items.map(({ at, label }, index) => {
        const start = Math.round(at * fps);
        const progress = interpolate(frame, [start, start + 14], [0, 1], {
          extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: MOTION.enter,
        });
        return (
          <div key={label} style={{
            position: 'relative', display: 'flex', alignItems: 'center',
            fontFamily: FONTS.primary, fontSize: 17, fontWeight: 600,
            color: index === active ? COLORS.brandPrimary : COLORS.darkSlate,
          }}>
            {label}
            <div style={{
              position: 'absolute', left: 0, right: 0, bottom: 4, height: 2, borderRadius: 2,
              background: COLORS.brandAccent, scale: `${progress} 1`, transformOrigin: 'left',
              opacity: index === active ? 1 : 0.28,
            }} />
          </div>
        );
      })}
    </div>
  );
};

export const BrowserFrame: React.FC<{
  readonly children: React.ReactNode;
  readonly milestones?: readonly Milestone[];
}> = ({ children, milestones }) => (
  <BrowserEntrance>
    <div style={{
      position: 'absolute', top: 96, left: (1920 - WIDTH) / 2, width: WIDTH, height: HEIGHT + CHROME_HEIGHT,
      borderRadius: 16, background: COLORS.surfaceWhite, boxShadow: SHADOWS.level4,
      outline: `1px solid ${COLORS.brandDeepBorder}40`,
    }}>
      <div style={{
        height: CHROME_HEIGHT, padding: '0 16px', display: 'flex', alignItems: 'center',
        borderBottom: `1px solid ${COLORS.brandDeepBorder}30`, boxSizing: 'border-box',
      }}>
        <div style={{ display: 'flex', gap: 8 }}>
          {[COLORS.brandAccent, COLORS.avatarCircle, COLORS.tableHeader].map((color) => (
            <div key={color} style={{ width: 8, height: 8, borderRadius: '50%', background: color }} />
          ))}
        </div>
        <div style={{
          position: 'absolute', left: '50%', translate: '-50% 0', display: 'flex', alignItems: 'center', gap: 8,
          background: COLORS.pageBg, padding: '4px 24px', borderRadius: 24,
          color: COLORS.darkSlate, fontFamily: FONTS.secondary, fontSize: 16, fontWeight: 600,
        }}>
          <Lock size={14} color={COLORS.brandPrimary} />
          sbsi.cms
        </div>
        {milestones && <Milestones items={milestones} />}
      </div>
      {children}
    </div>
  </BrowserEntrance>
);

const FocusGraphic: React.FC<{ readonly shot: Shot }> = ({ shot }) => {
  const frame = useCurrentFrame();
  const { fps, durationInFrames } = useVideoConfig();
  const [x, y, width, height] = shot.focus ?? [4, 4, 1912, 1072];
  const start = shot.reveal === 'scan' ? 8 : 5;
  const animationFrames = shot.reveal === 'scan' || shot.reveal === 'chart' ? 36 : 20;
  const ease = { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: MOTION.move } as const;
  const progress = interpolate(frame, [start, start + animationFrames], [0, 1], ease);
  const end = Math.min(durationInFrames, Math.round(fps * 2));
  const opacity = interpolate(frame, [start, start + 8], [0, 1], ease)
    * interpolate(frame, [end - 12, end], [1, 0], ease);
  const borderProgress = 1 - progress;

  return (
    <div style={{
      position: 'absolute', left: x, top: y, width, height, opacity,
      pointerEvents: 'none', overflow: 'hidden', borderRadius: 12,
    }}>
      <div style={{
        position: 'absolute', inset: 0, borderRadius: 'inherit', boxSizing: 'border-box',
        border: `3px solid ${COLORS.brandAccent}`,
        clipPath: `inset(0 ${borderProgress * 50}% 0 ${borderProgress * 50}%)`,
      }} />
      <div style={{
        position: 'absolute', inset: 4, border: `1px solid ${COLORS.brandAccent}`, borderRadius: 8,
        opacity: Math.sin(Math.PI * progress) * 0.72, scale: 0.94 + progress * 0.06,
      }} />
      {shot.reveal === 'scan' && (
        <div style={{
          position: 'absolute', left: 0, right: 0, top: `${progress * 100}%`, height: 4,
          background: COLORS.brandAccent, boxShadow: `0 0 24px 4px ${COLORS.brandAccent}`,
        }} />
      )}
      {shot.reveal === 'chart' && (
        <div style={{
          position: 'absolute', top: 0, bottom: 0, width: 128,
          left: `${progress * 100}%`, translate: '-100% 0',
          background: `linear-gradient(90deg, transparent, ${COLORS.brandAccent}30)`,
          borderRight: `3px solid ${COLORS.brandAccent}`,
          opacity: Math.sin(progress * Math.PI),
        }} />
      )}
    </div>
  );
};

export const BrowserShot: React.FC<{
  readonly shot: Shot;
  readonly clip?: string;
  readonly sourceEnd?: number;
}> = ({ shot, clip, sourceEnd }) => {
  const frame = useCurrentFrame();
  const { fps, durationInFrames } = useVideoConfig();
  const reveal = interpolate(frame, [0, Math.round(fps * 0.33)], [0.958, 1], {
    extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: MOTION.enter,
  });
  const rate = shot.rate ?? 1;
  const start = shot.source[0];
  const end = Math.min(shot.source[1] ?? start, sourceEnd ?? Infinity);
  const lastMotionFrame = Math.max(0, Math.floor((end - start) / rate * fps) - 2);
  const cueFrame = shot.cue ? Math.round((shot.cue.sourceTime - start) / rate * fps) : -1;
  const point = clip && cueFrame >= 0 && cueFrame <= lastMotionFrame && cueFrame < durationInFrames
    ? shot.cue?.point
    : undefined;

  return (
    <div style={{
      position: 'absolute', top: CHROME_HEIGHT, left: 0, width: WIDTH, height: HEIGHT,
      overflow: 'hidden', borderRadius: '0 0 16px 16px', background: COLORS.surfaceWhite,
      scale: shot.at > 0 ? reveal : 1, transformOrigin: '50% 46%',
    }}>
      <div style={{
        position: 'absolute', top: 0, left: 0, width: 1920, height: 1080,
        scale: SCALE, transformOrigin: 'top left',
      }}>
        {clip ? (
          <Freeze frame={lastMotionFrame} active={frame >= lastMotionFrame}>
            <Video
              src={staticFile(clip)}
              trimBefore={Math.round(start * fps)}
              playbackRate={rate}
              muted
              objectFit="contain"
              style={{ width: '100%', height: '100%', display: 'block' }}
            />
          </Freeze>
        ) : (
          <Img src={staticFile(`captures/${shot.image}`)}
            style={{ width: '100%', height: '100%', objectFit: 'contain', display: 'block' }} />
        )}
        {(shot.focus || shot.reveal) && <FocusGraphic shot={shot} />}
        {point && (
          <CursorClick
            fromX={point[0] - 104} fromY={point[1] - 64} toX={point[0]} toY={point[1]}
            startFrame={Math.max(0, cueFrame - 14)} clickFrame={cueFrame} exitFrame={cueFrame + 18} playSfx={false}
          />
        )}
      </div>
    </div>
  );
};
