import React from 'react';
import type { Caption } from '@remotion/captions';
import { Composition, Folder, staticFile, type CalculateMetadataFunction } from 'remotion';
import {
  CrmsFullDemo, DEFAULT_SCENE_TIMINGS, FPS, SCENE_COMPONENTS, type CrmsFullDemoProps, type SceneTiming,
} from './CrmsFullDemo';
import type { SceneProps } from './components/EditedScene';
import { DEMO_SCENES } from './script/scenes';
import { EDITS } from './script/shots';
import { getAudioDuration } from './utils/getAudioDuration';

type VoiceManifest = Record<string, { mp3: string; captions: string }>;
type ClipManifest = { file: string; trimStartSec: number; durationSec: number }[];

const readJson = async <T,>(path: string): Promise<T | undefined> => {
  try {
    const response = await fetch(staticFile(path));
    return response.ok ? (await response.json() as T) : undefined;
  } catch {
    return undefined;
  }
};

const loadTimings = async (ids: readonly string[]): Promise<Record<string, SceneTiming>> => {
  const [voiceover, footage] = await Promise.all([
    readJson<VoiceManifest>('voiceover/manifest.json'),
    readJson<ClipManifest>('clips/manifest.json'),
  ]);

  return Object.fromEntries(await Promise.all(ids.map(async (id) => {
    const clip = footage?.find(({ file }) => file === EDITS[id]?.clip);
    const sourceEnd = clip ? clip.trimStartSec + clip.durationSec : undefined;
    const fallback = { ...DEFAULT_SCENE_TIMINGS[id], sourceEnd };
    const entry = voiceover?.[id];
    if (!entry) return [id, fallback] as const;

    try {
      const [duration, transcript] = await Promise.all([
        getAudioDuration(staticFile(entry.mp3)),
        readJson<{ captions?: Caption[] }>(entry.captions),
      ]);
      const lastShot = EDITS[id]?.shots.at(-1)?.at ?? 0;
      return [id, {
        durationInFrames: Math.ceil(Math.max(3, duration + 0.8, lastShot + 1.5) * FPS),
        voiceoverFile: entry.mp3,
        captions: transcript?.captions,
        sourceEnd,
      }] as const;
    } catch {
      return [id, fallback] as const;
    }
  })));
};

const sumDuration = (scenes: Record<string, SceneTiming>) =>
  Object.values(scenes).reduce((sum, scene) => sum + scene.durationInFrames, 0);

const DIMENSIONS = { fps: FPS, width: 1920, height: 1080 };

const calculateFullDemo: CalculateMetadataFunction<CrmsFullDemoProps> = async () => {
  const scenes = await loadTimings(DEMO_SCENES.map(({ id }) => id));
  return { durationInFrames: sumDuration(scenes), props: { scenes } };
};

const calculateScene = (id: string): CalculateMetadataFunction<SceneProps> => async () => {
  const { durationInFrames, ...props } = (await loadTimings([id]))[id];
  return { durationInFrames, props };
};

export const RemotionRoot: React.FC = () => (
  <>
    <Composition
      {...DIMENSIONS}
      id="CrmsFullDemo"
      component={CrmsFullDemo}
      durationInFrames={sumDuration(DEFAULT_SCENE_TIMINGS)}
      defaultProps={{ scenes: DEFAULT_SCENE_TIMINGS }}
      calculateMetadata={calculateFullDemo}
    />
    <Folder name="Scenes">
      {DEMO_SCENES.map(({ id, compositionId }) => {
        const Scene = SCENE_COMPONENTS[id];
        return (
          <Composition
            key={id}
            {...DIMENSIONS}
            id={compositionId}
            component={Scene}
            durationInFrames={DEFAULT_SCENE_TIMINGS[id].durationInFrames}
            defaultProps={{}}
            calculateMetadata={calculateScene(id)}
          />
        );
      })}
    </Folder>
  </>
);
