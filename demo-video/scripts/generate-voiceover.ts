import 'dotenv/config';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import type { Caption } from '@remotion/captions';
import { DEMO_SCENES } from '../src/script/scenes.ts';

/**
 * Editable pronunciation overrides applied only to the TTS request text.
 * Burned-in captions always use the clean display text from DEMO_SCENES.
 */
const PRONUNCIATION_MAP: Array<[RegExp, string]> = [
  [/\bSBSI's\b/g, 'S-B-S-I-s'],
  [/\bSBSI\b/g, 'S-B-S-I'],
  [/\bCMS\b/g, 'C-M-S'],
  [/\bCRMS\b/g, 'C-M-S'],
  [/\bPRS\b/g, 'P-R-S'],
  [/\bOCR\b/g, 'O-C-R'],
  [/\bPDF\b/g, 'P-D-F'],
  [/\bDOCX\b/g, 'Doc-X'],
  [/\bRBAC\b/g, 'R-back'],
  [/\bQA\b/g, 'Q-A'],
  [/\bFDA\b/g, 'F-D-A'],
  [/\bCEO\b/g, 'C-E-O'],
  [/\bRAG\b/g, 'rag'],
  [/\bpgvector\b/g, 'P-G-vector'],
  [/\bRA 10173\b/g, 'R-A 10173'],
];

function applyPronunciation(text: string): string {
  return PRONUNCIATION_MAP.reduce((acc, [pattern, replacement]) => acc.replace(pattern, replacement), text);
}

type ElevenLabsAlignment = {
  characters: string[];
  character_start_times_seconds: number[];
  character_end_times_seconds: number[];
};

function alignmentToCaptions(alignment: ElevenLabsAlignment): Caption[] {
  const { characters, character_start_times_seconds: starts, character_end_times_seconds: ends } = alignment;
  const captions: Caption[] = [];
  let token = '';
  let tokenStartSec: number | null = null;
  let tokenEndSec = 0;

  const flush = () => {
    if (!token || tokenStartSec === null) return;
    const prefix = captions.length === 0 ? '' : ' ';
    const startMs = Math.round(tokenStartSec * 1000);
    const endMs = Math.max(startMs + 60, Math.round(tokenEndSec * 1000));
    captions.push({
      text: `${prefix}${token}`,
      startMs,
      endMs,
      timestampMs: Math.round((startMs + endMs) / 2),
      confidence: 1,
    });
    token = '';
    tokenStartSec = null;
  };

  for (let i = 0; i < characters.length; i++) {
    const ch = characters[i];
    if (/\s/.test(ch)) {
      flush();
      continue;
    }
    if (tokenStartSec === null) {
      tokenStartSec = starts[i] ?? tokenEndSec;
    }
    token += ch;
    tokenEndSec = ends[i] ?? tokenStartSec + 0.08;
  }
  flush();
  return captions;
}

async function listVoices(apiKey: string) {
  const res = await fetch('https://api.elevenlabs.io/v1/voices', {
    headers: { 'xi-api-key': apiKey },
  });
  if (!res.ok) {
    throw new Error(`Failed to list ElevenLabs voices (${res.status}): ${await res.text()}`);
  }
  const json = (await res.json()) as { voices: Array<{ voice_id: string; name: string; labels?: Record<string, string> }> };
  console.log('\nAvailable voices on your ElevenLabs account:');
  for (const v of json.voices.slice(0, 20)) {
    const desc = v.labels ? Object.values(v.labels).join(', ') : '';
    console.log(`  ${v.voice_id}  |  ${v.name}${desc ? ` (${desc})` : ''}`);
  }
}

async function main() {
  const args = process.argv.slice(2);
  const force = args.includes('--force');
  const listOnly = args.includes('--list-voices');
  const onlyArg = args.find((a) => a.startsWith('--scene='));
  const onlyScene = onlyArg ? onlyArg.split('=')[1] : null;

  const apiKey = process.env.ELEVENLABS_API_KEY?.trim();
  const voiceId = process.env.ELEVENLABS_VOICE_ID?.trim();
  const modelId = process.env.ELEVENLABS_MODEL?.trim() || 'eleven_multilingual_v2';

  if (!apiKey) {
    console.error('Missing ELEVENLABS_API_KEY in demo-video/.env');
    process.exit(1);
  }

  if (listOnly) {
    await listVoices(apiKey);
    return;
  }

  if (!voiceId) {
    console.error('Missing ELEVENLABS_VOICE_ID in demo-video/.env. Listing voices so you can pick one:');
    await listVoices(apiKey);
    process.exit(1);
  }

  const outDir = resolve(process.cwd(), 'public/voiceover');
  mkdirSync(outDir, { recursive: true });

  const targets = onlyScene ? DEMO_SCENES.filter((s) => s.id === onlyScene) : DEMO_SCENES;
  if (targets.length === 0) {
    console.error(`No scene matched --scene=${onlyScene}`);
    process.exit(1);
  }

  for (const scene of targets) {
    const mp3Path = resolve(outDir, `${scene.id}.mp3`);
    const capPath = resolve(outDir, `${scene.id}.captions.json`);

    if (!force && existsSync(mp3Path) && existsSync(capPath)) {
      console.log(`[skip] ${scene.id} already cached`);
      continue;
    }

    const speechText = applyPronunciation(scene.narration);
    console.log(`[tts] Generating ${scene.id} (${speechText.length} chars)...`);

    const response = await fetch(
      `https://api.elevenlabs.io/v1/text-to-speech/${encodeURIComponent(voiceId)}/with-timestamps`,
      {
        method: 'POST',
        headers: {
          'xi-api-key': apiKey,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          text: speechText,
          model_id: modelId,
          voice_settings: {
            stability: 0.55,
            similarity_boost: 0.78,
            style: 0.25,
            use_speaker_boost: true,
          },
        }),
      },
    );

    if (!response.ok) {
      throw new Error(`ElevenLabs error on ${scene.id} (${response.status}): ${await response.text()}`);
    }

    const data = (await response.json()) as {
      audio_base64: string;
      alignment?: ElevenLabsAlignment;
      normalized_alignment?: ElevenLabsAlignment;
    };

    writeFileSync(mp3Path, Buffer.from(data.audio_base64, 'base64'));
    const alignment = data.alignment ?? data.normalized_alignment;
    const captions = alignment ? alignmentToCaptions(alignment) : [];
    writeFileSync(capPath, JSON.stringify({ displayNarration: scene.narration, captions }, null, 2));
    console.log(`[ok]  ${scene.id} -> mp3 + ${captions.length} timed tokens`);
  }

  const voManifest: Record<string, { mp3: string; captions: string }> = {};
  for (const scene of DEMO_SCENES) {
    if (existsSync(resolve(outDir, `${scene.id}.mp3`))) {
      voManifest[scene.id] = {
        mp3: `voiceover/${scene.id}.mp3`,
        captions: `voiceover/${scene.id}.captions.json`,
      };
    }
  }
  writeFileSync(resolve(outDir, 'manifest.json'), JSON.stringify(voManifest, null, 2));
  console.log(`[ok]  voiceover/manifest.json updated (${Object.keys(voManifest).length} tracks)`);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
