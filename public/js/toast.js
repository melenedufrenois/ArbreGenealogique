const showToast = (classToast, time) => {
  $(`.${classToast}`).show('slow', () => {
    let progressBar = $(`.${classToast} #progress-bar`);
    let progressInterval = 10 // Intervalle de mise à jour de la barre de progression en millisecondes
    let progressIncrement = 100 / (time / progressInterval)
    let progress = 100

    let progressTimer = setInterval(function() {
      progress -= progressIncrement
      progressBar.css('width', progress + '%')

      if (progress <= 0) {
        clearInterval(progressTimer)
      }
    }, progressInterval);
  }).delay(time).fadeOut()
}