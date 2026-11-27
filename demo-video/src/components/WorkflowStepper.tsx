import React from 'react';
import { Check, FileSignature, ShieldCheck } from 'lucide-react';
import { interpolate, useCurrentFrame, useVideoConfig } from 'remotion';
import { COLORS, FONTS, MOTION, SHADOWS } from '../theme/tokens';

type StepItem = {
  readonly label: string;
  readonly sub: string;
  readonly role: string;
};

const EXECUTION_STEPS: StepItem[] = [
  { label: 'SBSI Review', sub: 'Internal quality check', role: 'Manager' },
  { label: 'Client Review', sub: 'Client acceptance', role: 'Manager' },
  { label: 'For Signing', sub: 'Scanned PDF or E-Sign', role: 'Manager / CEO' },
  { label: 'Signed', sub: 'Executed & Active', role: 'Confirmed' },
  { label: 'Notarized PDF', sub: 'Optional archive', role: 'Manager' },
];

type WorkflowStepperProps = {
  readonly activeIndex: number;
  readonly left?: number;
  readonly top?: number;
  readonly width?: number;
};

export const WorkflowStepper: React.FC<WorkflowStepperProps> = ({
  activeIndex,
  left = 88,
  top = 128,
  width = 1744,
}) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  return (
    <div
      style={{
        position: 'absolute',
        left,
        top,
        width,
        padding: '18px 24px',
        borderRadius: 16,
        backgroundColor: COLORS.surfaceWhite,
        border: '1px solid rgba(37, 37, 120, 0.1)',
        boxShadow: SHADOWS.level3,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 12,
        zIndex: 25,
      }}
    >
      {EXECUTION_STEPS.map((step, idx) => {
        const delay = idx * Math.round(0.12 * fps);
        const itemOpacity = interpolate(frame - delay, [0, Math.round(0.25 * fps)], [0, 1], {
          extrapolateLeft: 'clamp',
          extrapolateRight: 'clamp',
          easing: MOTION.enter,
        });
        const done = idx < activeIndex;
        const current = idx === activeIndex;
        const pillBg = done
          ? COLORS.successBg
          : current
            ? COLORS.brandBg
            : COLORS.pageBg;
        const borderColor = done
          ? COLORS.success
          : current
            ? COLORS.brandAccent
            : 'rgba(148, 163, 184, 0.35)';

        return (
          <React.Fragment key={step.label}>
            <div
              style={{
                flex: 1,
                opacity: itemOpacity,
                padding: '12px 14px',
                borderRadius: 12,
                backgroundColor: pillBg,
                border: `1.5px solid ${borderColor}`,
                display: 'flex',
                alignItems: 'center',
                gap: 12,
              }}
            >
              <div
                style={{
                  width: 32,
                  height: 32,
                  borderRadius: '50%',
                  backgroundColor: done
                    ? COLORS.success
                    : current
                      ? COLORS.brandPrimary
                      : '#E2E8F0',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  flexShrink: 0,
                }}
              >
                {done ? (
                  <Check size={16} color="#FFFFFF" />
                ) : step.label === 'For Signing' ? (
                  <FileSignature size={15} color={current ? '#FFFFFF' : COLORS.mutedSlate} />
                ) : (
                  <ShieldCheck size={15} color={current ? '#FFFFFF' : COLORS.mutedSlate} />
                )}
              </div>
              <div style={{ minWidth: 0 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span
                    style={{
                      fontFamily: FONTS.primary,
                      fontSize: 14,
                      fontWeight: 700,
                      color: current ? COLORS.brandPrimary : COLORS.darkSlate,
                    }}
                  >
                    {step.label}
                  </span>
                </div>
                <div
                  style={{
                    fontFamily: FONTS.secondary,
                    fontSize: 11,
                    fontWeight: 500,
                    color: COLORS.mutedSlate,
                  }}
                >
                  {step.sub} · {step.role}
                </div>
              </div>
            </div>
            {idx < EXECUTION_STEPS.length - 1 && (
              <div
                style={{
                  width: 24,
                  height: 2,
                  backgroundColor: done ? COLORS.success : 'rgba(148,163,184,0.45)',
                  flexShrink: 0,
                }}
              />
            )}
          </React.Fragment>
        );
      })}
    </div>
  );
};
