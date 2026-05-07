import { query } from './dom';

const valorantDivisionIcons = {
  unranked: 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/0/largeicon.png',
  'iron i': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/3/largeicon.png',
  'iron ii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/4/largeicon.png',
  'iron iii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/5/largeicon.png',
  'bronze i': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/6/largeicon.png',
  'bronze ii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/7/largeicon.png',
  'bronze iii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/8/largeicon.png',
  'silver i': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/9/largeicon.png',
  'silver ii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/10/largeicon.png',
  'silver iii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/11/largeicon.png',
  'gold i': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/12/largeicon.png',
  'gold ii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/13/largeicon.png',
  'gold iii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/14/largeicon.png',
  'platinum i': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/15/largeicon.png',
  'platinum ii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/16/largeicon.png',
  'platinum iii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/17/largeicon.png',
  'diamond i': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/18/largeicon.png',
  'diamond ii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/19/largeicon.png',
  'diamond iii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/20/largeicon.png',
  'ascendant i': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/21/largeicon.png',
  'ascendant ii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/22/largeicon.png',
  'ascendant iii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/23/largeicon.png',
  'immortal i': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/24/largeicon.png',
  'immortal ii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/25/largeicon.png',
  'immortal iii': 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/26/largeicon.png',
  radiant: 'https://media.valorant-api.com/competitivetiers/03621f52-342b-cf4e-4f86-9350a49c6d04/27/largeicon.png',
};

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
