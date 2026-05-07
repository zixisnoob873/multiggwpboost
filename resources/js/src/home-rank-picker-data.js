export const rankTierDefinitions = [
  {
    tier: 'Iron',
    divisions: ['I', 'II', 'III'],
    accent: '#8d96aa',
    glow: 'rgba(141, 150, 170, 0.3)',
    iconValue: 'Iron III',
    description: 'Foundational queues with a clean climb profile.',
  },
  {
    tier: 'Bronze',
    divisions: ['I', 'II', 'III'],
    accent: '#ca8a5b',
    glow: 'rgba(202, 138, 91, 0.3)',
    iconValue: 'Bronze III',
    description: 'Early momentum tiers with steady progress.',
  },
  {
    tier: 'Silver',
    divisions: ['I', 'II', 'III'],
    accent: '#bcc8d8',
    glow: 'rgba(188, 200, 216, 0.34)',
    iconValue: 'Silver III',
    description: 'Reliable mid-tier pacing for cleaner gains.',
  },
  {
    tier: 'Gold',
    divisions: ['I', 'II', 'III'],
    accent: '#f2ca72',
    glow: 'rgba(242, 202, 114, 0.34)',
    iconValue: 'Gold III',
    description: 'Balanced premium queues with strong momentum.',
  },
  {
    tier: 'Platinum',
    divisions: ['I', 'II', 'III'],
    accent: '#5fd7c7',
    glow: 'rgba(95, 215, 199, 0.34)',
    iconValue: 'Platinum III',
    description: 'Sharper polish for more refined progression.',
  },
  {
    tier: 'Diamond',
    divisions: ['I', 'II', 'III'],
    accent: '#63c8ff',
    glow: 'rgba(99, 200, 255, 0.34)',
    iconValue: 'Diamond III',
    description: 'High-precision pacing for serious climbs.',
  },
  {
    tier: 'Ascendant',
    divisions: ['I', 'II', 'III'],
    accent: '#71df90',
    glow: 'rgba(113, 223, 144, 0.34)',
    iconValue: 'Ascendant III',
    description: 'Elite delivery with a sharper competitive edge.',
  },
  {
    tier: 'Immortal',
    divisions: ['I', 'II', 'III'],
    accent: '#cf69ff',
    glow: 'rgba(207, 105, 255, 0.34)',
    iconValue: 'Immortal III',
    description: 'Top-end queues tuned for advanced lobbies.',
  },
  {
    tier: 'Radiant',
    divisions: [],
    accent: '#ff5b68',
    glow: 'rgba(255, 91, 104, 0.36)',
    iconValue: 'Radiant',
    description: 'Peak prestige with a single final destination.',
  },
];

const rankTierMap = new Map(rankTierDefinitions.map((item) => [item.tier, item]));

export function getRankTierMeta(tier) {
  return rankTierMap.get(tier) || rankTierDefinitions[0];
}

export function parseRankValue(value = '') {
  const trimmed = String(value || '').trim();

  if (!trimmed) {
    return {
      tier: '',
      division: '',
      value: '',
    };
  }

  if (trimmed === 'Radiant') {
    return {
      tier: 'Radiant',
      division: '',
      value: 'Radiant',
    };
  }

  const segments = trimmed.split(/\s+/);

  return {
    tier: segments[0] || '',
    division: segments.slice(1).join(' '),
    value: trimmed,
  };
}
