function loadGame() {
  loadButton.classList.add('is-busy');
  loadButton.disabled = true;
  const gameUrlInput = document.querySelector('#game-link')
  const gameURL = gameUrlInput.value;

  var gameHash = '';
  var prefixHttps = 'https://lidarts.org/game/';
  var prefixHttp = 'http://lidarts.org/game/';
  var isCorrect = false;
  if (gameURL.includes(prefixHttps)) {
    gameHash = gameURL.replace(prefixHttps, '');
    isCorrect = true;
  } else if (gameURL.includes(prefixHttp)) {
    gameHash = gameURL.replace(prefixHttp, '');
    isCorrect = true;
  }

  if (isCorrect && gameHash.length === 8) {
    location.href += `?game=${gameHash}`;
  } else {
    loadButton.classList.remove('is-busy');
    loadButton.disabled = false;
    gameUrlInput.setAttribute('aria-invalid', 'true');
    document.querySelector('validation-error')?.remove();
    gameUrlInput.insertAdjacentHTML('afterend', /*html*/`
      <validation-error>Die eingegebene Lidarts-URL scheint fehlerhaft zu sein, bitte überprüfe deine Eingabe.</validation-error>
    `)
  }
}

function populateCanceledGameOptions() {
  const selectedOption = cancelledGameSelect.selectedOptions[0];
  const optionsFieldset = selectedOption
    .closest('form')
    .querySelector('fieldset');

  optionsFieldset.innerHTML = '';
  optionsFieldset.insertAdjacentHTML(
    'beforeend',
    /*html*/ `
  <legend>120 Punkte gehen an</legend>
  <label for="small">
    <input type="radio" id="cancelled-points-nobody" name="cancelledPoints" value="0" checked>
    Niemanden / 0:0
  </label>
  <label for="medium">
    <input type="radio" id="cancelled-points-player1" name="cancelledPoints" value="1">
    ${selectedOption.dataset.playerLeft}
  </label>
  <label for="large">
    <input type="radio" id="cancelled-points-player2" name="cancelledPoints" value="2">
    ${selectedOption.dataset.playerRight}
  </label>
  `
  );
}

const loadButton = document.querySelector('#get-game');
loadButton.addEventListener('click', loadGame);

const cancelledGameSelect = document.querySelector('#game-number');
cancelledGameSelect.addEventListener('change', populateCanceledGameOptions);
