import React from 'react';
import { Activity, Building, Wrench } from 'lucide-react';
import { interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import type { SceneProps } from '../components/EditedScene';
import { KineticTitle } from '../components/KineticTitle';
import { SaaSStage } from '../components/SaaSStage';
import { SceneAudioAndCaptions } from '../components/SceneAudioAndCaptions';
import { DEMO_SCENES } from '../script/scenes';
import { COLORS, FONTS, MOTION, SHADOWS } from '../theme/tokens';

const CONNECTIONS = [
  { name: 'SERMS', detail: 'Burn rate and vendor records', icon: Activity, at: 3.6 },
  { name: 'PRS', detail: 'Products, institutions, and closing outcomes', icon: Building, at: 6.7 },
  { name: 'Technical Service', detail: 'Contracted equipment and customer records', icon: Wrench, at: 10.7 },
];

export const Scene12Ecosystem: React.FC<SceneProps> = ({ voiceoverFile, captions }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  return (
    <SaaSStage variant="brand">
      <div style={{ position: 'absolute', left: 112, top: 80 }}>
        <KineticTitle size={56} color={COLORS.surfaceWhite}>Connected across SBSI</KineticTitle>
      </div>
      <div style={{
        position: 'absolute', left: 112, top: 460, width: 544, padding: 32,
        background: COLORS.surfaceWhite, borderRadius: 16, boxShadow: SHADOWS.level3,
        font: `700 36px ${FONTS.primary}`, color: COLORS.brandPrimary,
      }}>
        Contract Management
      </div>
      {CONNECTIONS.map(({ name, detail, icon: Icon, at }, index) => {
        const start = Math.round(at * fps);
        const progress = interpolate(frame, [start, start + 18], [0, 1], {
          extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: MOTION.move,
        });
        const y = 288 + index * 176;
        return (
          <React.Fragment key={name}>
            <svg width={1920} height={1080} style={{ position: 'absolute', inset: 0 }}>
              <path d={`M720 518 H824 V${y + 56} H992`} pathLength={1}
                fill="none" stroke={COLORS.brandBg} strokeWidth={4}
                strokeDasharray={1} strokeDashoffset={1 - progress} />
            </svg>
            <div style={{
              position: 'absolute', left: 992, top: y, width: 752, padding: 24,
              background: COLORS.surfaceWhite, borderRadius: 16, boxShadow: SHADOWS.level3,
              display: 'flex', gap: 24, alignItems: 'center',
              opacity: progress, translate: `${(1 - progress) * 24}px 0`,
            }}>
              <Icon size={40} color={COLORS.brandPrimary} />
              <div>
                <div style={{ font: `700 28px ${FONTS.primary}`, color: COLORS.brandPrimary }}>{name}</div>
                <div style={{ font: `500 22px ${FONTS.secondary}`, color: COLORS.darkSlate }}>{detail}</div>
              </div>
            </div>
          </React.Fragment>
        );
      })}
      <SceneAudioAndCaptions voiceoverFile={voiceoverFile} captions={captions} fallbackText={DEMO_SCENES[11].narration} darkBackground />
    </SaaSStage>
  );
};
