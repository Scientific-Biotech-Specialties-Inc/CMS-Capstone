import React from 'react';
import { TransitionSeries } from '@remotion/transitions';
import { AbsoluteFill, interpolate, useCurrentFrame } from 'remotion';
import type { SceneProps } from './components/EditedScene';
import { DEMO_SCENES } from './script/scenes';
import { Scene01Intro } from './scenes/Scene01Intro';
import { Scene02AuthRbac } from './scenes/Scene02AuthRbac';
import { Scene03Analytics } from './scenes/Scene03Analytics';
import { Scene04Vendors } from './scenes/Scene04Vendors';
import { Scene05AiVendors } from './scenes/Scene05AiVendors';
import { Scene06CreateContract } from './scenes/Scene06CreateContract';
import { Scene07AiRisk } from './scenes/Scene07AiRisk';
import { Scene08Workflows } from './scenes/Scene08Workflows';
import { Scene09Approval } from './scenes/Scene09Approval';
import { Scene10ExecutionSigning } from './scenes/Scene10ExecutionSigning';
import { Scene11Ops } from './scenes/Scene11Ops';
import { Scene12Ecosystem } from './scenes/Scene12Ecosystem';
import { Scene13Outro } from './scenes/Scene13Outro';
import { COLORS, MOTION } from './theme/tokens';

export const FPS = 30;

export type SceneTiming = SceneProps & {
  readonly durationInFrames: number;
};

export type CrmsFullDemoProps = {
  readonly scenes: Record<string, SceneTiming>;
};

export const SCENE_COMPONENTS: Record<string, React.ComponentType<SceneProps>> = {
  'scene-01-intro': Scene01Intro,
  'scene-02-auth-rbac': Scene02AuthRbac,
  'scene-03-analytics': Scene03Analytics,
  'scene-04-vendors': Scene04Vendors,
  'scene-05-ai-vendors': Scene05AiVendors,
  'scene-06-create-contract': Scene06CreateContract,
  'scene-07-ai-risk': Scene07AiRisk,
  'scene-08-workflows': Scene08Workflows,
  'scene-09-approval': Scene09Approval,
  'scene-10-execution-signing': Scene10ExecutionSigning,
  'scene-11-ops': Scene11Ops,
  'scene-12-ecosystem': Scene12Ecosystem,
  'scene-13-outro': Scene13Outro,
};

export const DEFAULT_SCENE_TIMINGS = Object.fromEntries(DEMO_SCENES.map((spec) => [
  spec.id, { durationInFrames: spec.estimatedSeconds * FPS },
]));

// This overlay spans a cut without asking both scenes to play at the same time.
const ChapterWipe: React.FC = () => {
  const frame = useCurrentFrame();
  const radius = interpolate(frame, [0, 10, 20], [0, 1220, 0], {
    extrapolateRight: 'clamp', easing: MOTION.move,
  });
  return (
    <AbsoluteFill style={{
      background: COLORS.brandPrimary, clipPath: `circle(${radius}px at 50% 50%)`,
    }} />
  );
};

export const CrmsFullDemo: React.FC<CrmsFullDemoProps> = ({ scenes }) => (
  <TransitionSeries>
    {DEMO_SCENES.flatMap(({ id, title }) => {
      const Scene = SCENE_COMPONENTS[id];
      const { durationInFrames, ...props } = scenes[id] ?? DEFAULT_SCENE_TIMINGS[id];
      return [
        ...(id === 'scene-12-ecosystem' ? [
          <TransitionSeries.Overlay key={`${id}-wipe`} durationInFrames={20}>
            <ChapterWipe />
          </TransitionSeries.Overlay>,
        ] : []),
        <TransitionSeries.Sequence key={id} name={title} durationInFrames={durationInFrames}>
          <Scene {...props} />
        </TransitionSeries.Sequence>,
      ];
    })}
  </TransitionSeries>
);
