import React from 'react';
import { Audio } from '@remotion/media';
import { staticFile } from 'remotion';

export type SfxName = 'pop' | 'whoosh' | 'mouse-click';

type SfxCueProps = {
  readonly name: SfxName;
  readonly fromFrame?: number;
  readonly volume?: number;
  readonly playbackRate?: number;
};

export const SfxCue: React.FC<SfxCueProps> = ({
  name,
  fromFrame = 0,
  volume = 0.42,
  playbackRate = 1,
}) => {
  return (
    <Audio
      src={staticFile(`sfx/real-sfx/${name}.wav`)}
      from={fromFrame}
      volume={volume}
      playbackRate={playbackRate}
    />
  );
};
