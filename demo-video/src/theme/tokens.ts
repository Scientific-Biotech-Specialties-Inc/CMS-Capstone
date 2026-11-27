import { Easing } from 'remotion';
import { loadFont as loadPoppins } from '@remotion/google-fonts/Poppins';
import { loadFont as loadMontserrat } from '@remotion/google-fonts/Montserrat';

const poppins = loadPoppins('normal', {
  weights: ['400', '500', '600', '700'],
  subsets: ['latin'],
});

const montserrat = loadMontserrat('normal', {
  weights: ['400', '500', '600'],
  subsets: ['latin'],
});

export const FONTS = {
  primary: poppins.fontFamily,
  secondary: montserrat.fontFamily,
} as const;

/** Canonical ONE UI tokens from DESIGN.md */
export const COLORS = {
  brandPrimary: '#252578',
  brandAccent: '#2E85D8',
  brandDeep: '#3A3A85',
  brandDeepBorder: '#4E4E91',
  brandDark: '#2F2F73',
  brandHover: '#2F2F8E',
  brandPressed: '#1E1E66',
  pageBg: '#F8F9FA',
  surfaceWhite: '#FFFFFF',
  darkSlate: '#1E293B',
  mutedSlate: '#8D8B8F',
  brandBg: '#EFF6FF',
  eyebrowSlate: '#8086A3',
  tableHeader: '#94A3B8',
  avatarCircle: '#A7A7C8',

  error: '#EF4444',
  errorBg: '#FEF2F2',
  errorBorder: '#FCA5A5',
  warning: '#F59E0B',
  warningBg: '#FFFBEB',
  warningBorder: '#FCD34D',
  success: '#10B981',
  successBg: '#ECFDF5',
  successBorder: '#6EE7B7',
} as const;

export const SHADOWS = {
  level1: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
  level2: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.06)',
  level3: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05)',
  level4: '0 20px 25px -5px rgba(30, 41, 59, 0.16), 0 8px 10px -6px rgba(30, 41, 59, 0.08)',
} as const;

/** Easing curves from animation-principles skill */
export const MOTION = {
  enter: Easing.bezier(0.16, 1, 0.3, 1),
  exit: Easing.bezier(0.7, 0, 0.84, 0),
  move: Easing.bezier(0.65, 0, 0.35, 1),
  overshoot: Easing.bezier(0.34, 1.56, 0.64, 1),
} as const;
