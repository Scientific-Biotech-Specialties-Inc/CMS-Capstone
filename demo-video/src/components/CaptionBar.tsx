import React, { useMemo } from 'react';
import { createTikTokStyleCaptions, type Caption } from '@remotion/captions';
import { useCurrentFrame, useVideoConfig } from 'remotion';
import { COLORS, FONTS, SHADOWS } from '../theme/tokens';

type CaptionBarProps = {
  readonly captions?: Caption[];
  readonly fallbackText?: string;
  readonly darkBackground?: boolean;
};

export const CaptionBar: React.FC<CaptionBarProps> = ({
  captions,
  fallbackText,
  darkBackground = false,
}) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const currentMs = (frame / fps) * 1000;

  const pages = useMemo(() => {
    if (!captions || captions.length === 0) return [];
    const { pages: chunked } = createTikTokStyleCaptions({
      captions,
      combineTokensWithinMilliseconds: 2600,
    });
    return chunked;
  }, [captions]);

  const activePage = pages.find((page, index) => {
    const nextStart = pages[index + 1]?.startMs ?? Infinity;
    const end = Math.min(nextStart, page.startMs + page.durationMs + 240);
    return currentMs >= page.startMs && currentMs < end;
  });

  if (!activePage && (pages.length > 0 || !fallbackText || currentMs > 4500)) return null;

  return (
    <div
      style={{
        position: 'absolute',
        bottom: darkBackground ? 48 : 16,
        left: 220,
        right: 220,
        display: 'flex',
        justifyContent: 'center',
        pointerEvents: 'none',
        zIndex: 60,
      }}
    >
      <div
        style={{
          maxWidth: 1120,
          padding: '8px 24px',
          borderRadius: 12,
          backgroundColor: darkBackground ? 'rgba(18, 22, 58, 0.88)' : 'rgba(30, 41, 59, 0.90)',
          border: '1px solid rgba(255, 255, 255, 0.14)',
          boxShadow: SHADOWS.level3,
          textAlign: 'center',
        }}
      >
        {activePage ? (
          <p
            style={{
              margin: 0,
              fontFamily: FONTS.secondary,
              fontSize: 22,
              fontWeight: 500,
              lineHeight: 1.4,
              color: '#F8FAFC',
            }}
          >
            {activePage.tokens.map((tok, idx) => {
              const spoken = currentMs >= tok.fromMs;
              const active = currentMs >= tok.fromMs && currentMs <= tok.toMs;
              return (
                <span
                  key={`${tok.fromMs}-${idx}`}
                  style={{
                    color: active ? COLORS.brandBg : spoken ? COLORS.surfaceWhite : 'rgba(255,255,255,0.72)',
                    textDecoration: active ? `underline 2px ${COLORS.brandAccent}` : undefined,
                  }}
                >
                  {tok.text}
                </span>
              );
            })}
          </p>
        ) : (
          <p
            style={{
              margin: 0,
              fontFamily: FONTS.secondary,
              fontSize: 18,
              fontWeight: 500,
              lineHeight: 1.38,
              color: '#F8FAFC',
              display: '-webkit-box',
              WebkitLineClamp: 2,
              WebkitBoxOrient: 'vertical',
              overflow: 'hidden',
            }}
          >
            {fallbackText}
          </p>
        )}
      </div>
    </div>
  );
};
