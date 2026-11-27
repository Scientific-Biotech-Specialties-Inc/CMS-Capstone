import { Input, ALL_FORMATS, UrlSource } from 'mediabunny';

export const getAudioDuration = async (src: string): Promise<number> => {
  const input = new Input({
    formats: ALL_FORMATS,
    source: new UrlSource(src),
  });
  const durationInSeconds = await input.computeDuration();
  input.dispose();
  return durationInSeconds;
};
