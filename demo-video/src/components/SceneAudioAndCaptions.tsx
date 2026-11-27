import React from 'react';
import { Audio } from '@remotion/media';
import type { Caption } from '@remotion/captions';
import { staticFile } from 'remotion';
import { CaptionBar } from './CaptionBar';

type SceneAudioAndCaptionsProps = {
  readonly voiceoverFile?: string;
  readonly captions?: Caption[];
  readonly fallbackText: string;
  readonly darkBackground?: boolean;
};

export const SceneAudioAndCaptions: React.FC<SceneAudioAndCaptionsProps> = ({
  voiceoverFile,
  captions,
  fallbackText,
  darkBackground = false,
}) => {
  return (
    <>
      {voiceoverFile && <Audio src={staticFile(voiceoverFile)} volume={1} />}
      <CaptionBar
        captions={captions}
        fallbackText={voiceoverFile ? undefined : fallbackText}
        darkBackground={darkBackground}
      />
    </>
  );
};
