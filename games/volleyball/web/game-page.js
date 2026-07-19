(function () {
  'use strict';

  var stage = document.querySelector('[data-game-stage]');
  var frame = document.querySelector('[data-game-frame]');
  var fullscreenButton = document.querySelector('[data-fullscreen]');

  if (stage && frame) {
    stage.addEventListener('pointerdown', function () {
      frame.focus();
    });
  }

  if (stage && fullscreenButton && document.fullscreenEnabled) {
    fullscreenButton.addEventListener('click', function () {
      if (document.fullscreenElement) {
        document.exitFullscreen();
        return;
      }

      stage.requestFullscreen().then(function () {
        frame.focus();
      });
    });
  } else if (fullscreenButton) {
    fullscreenButton.hidden = true;
  }
})();
