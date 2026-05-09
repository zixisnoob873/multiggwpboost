import { query } from './dom';

const fallbackRankIcon = '/assets/game-assets/fallbacks/rank-icon.svg';
const serverRankIcons = window.appState?.rankIconMap || {};

const valorantDivisionIcons = Object.fromEntries([
  ['unranked', serverRankIcons['unranked'] || fallbackRankIcon],
  ['iron i', serverRankIcons['iron i'] || fallbackRankIcon],
  ['iron ii', serverRankIcons['iron ii'] || fallbackRankIcon],
  ['iron iii', serverRankIcons['iron iii'] || fallbackRankIcon],
  ['bronze i', serverRankIcons['bronze i'] || fallbackRankIcon],
  ['bronze ii', serverRankIcons['bronze ii'] || fallbackRankIcon],
  ['bronze iii', serverRankIcons['bronze iii'] || fallbackRankIcon],
  ['silver i', serverRankIcons['silver i'] || fallbackRankIcon],
  ['silver ii', serverRankIcons['silver ii'] || fallbackRankIcon],
  ['silver iii', serverRankIcons['silver iii'] || fallbackRankIcon],
  ['gold i', serverRankIcons['gold i'] || fallbackRankIcon],
  ['gold ii', serverRankIcons['gold ii'] || fallbackRankIcon],
  ['gold iii', serverRankIcons['gold iii'] || fallbackRankIcon],
  ['platinum i', serverRankIcons['platinum i'] || fallbackRankIcon],
  ['platinum ii', serverRankIcons['platinum ii'] || fallbackRankIcon],
  ['platinum iii', serverRankIcons['platinum iii'] || fallbackRankIcon],
  ['diamond i', serverRankIcons['diamond i'] || fallbackRankIcon],
  ['diamond ii', serverRankIcons['diamond ii'] || fallbackRankIcon],
  ['diamond iii', serverRankIcons['diamond iii'] || fallbackRankIcon],
  ['ascendant i', serverRankIcons['ascendant i'] || fallbackRankIcon],
  ['ascendant ii', serverRankIcons['ascendant ii'] || fallbackRankIcon],
  ['ascendant iii', serverRankIcons['ascendant iii'] || fallbackRankIcon],
  ['immortal i', serverRankIcons['immortal i'] || fallbackRankIcon],
  ['immortal ii', serverRankIcons['immortal ii'] || fallbackRankIcon],
  ['immortal iii', serverRankIcons['immortal iii'] || fallbackRankIcon],
  ['radiant', serverRankIcons['radiant'] || fallbackRankIcon]
]);

function normalizeDivisionName(value) {
  if (!value) {
    return 'unranked';
  }

  const cleaned = value.toLowerCase().trim().replace(/\s+/g, ' ');
  if (valorantDivisionIcons[cleaned]) {
    return cleaned;
  }

  const numeric = cleaned
    .replace(/\b1\b/g, 'i')
    .replace(/\b2\b/g, 'ii')
    .replace(/\b3\b/g, 'iii');

  if (valorantDivisionIcons[numeric]) {
    return numeric;
  }

  if (cleaned.includes('radiant')) {
    return 'radiant';
  }

  return 'unranked';
}

function bindRankIconPreview(selectId, previewId) {
  const select = query(`#${selectId}`);
  const preview = query(`#${previewId}`);
  if (!select || !preview) {
    return;
  }

  const render = () => {
    const key = normalizeDivisionName(select.value);
    const icon = valorantDivisionIcons[key] || valorantDivisionIcons.unranked;
    const wrapper = document.createElement('div');
    wrapper.className = 'col d-flex align-items-center justify-content-center';

    const image = document.createElement('img');
    image.src = icon;
    image.alt = `${select.value} icon`;
    image.className = 'rank-icon-img';

    wrapper.appendChild(image);
    preview.replaceChildren(wrapper);
  };

  select.addEventListener('change', render);
  render();
}

export { bindRankIconPreview, normalizeDivisionName, valorantDivisionIcons };
