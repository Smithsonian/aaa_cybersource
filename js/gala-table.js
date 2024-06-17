(function(Drupal, drupalSettings) {
  Drupal.behaviors.aaaGalaTable = {
    attach: function(context) {
      if (context.querySelector('#edit-gala')) {
        Drupal.behaviors.aaaGalaTable.form = document.querySelector('form.webform-submission-form')
        Drupal.behaviors.aaaGalaTable.amount = Drupal.behaviors.aaaGalaTable.form.querySelector('input[name="amount"]')
        Drupal.behaviors.aaaGalaTable.amount.value = '0'

        Drupal.behaviors.aaaGalaTable.form.querySelectorAll('input[type="number"][data-drupal-selector$=quantity]').forEach(function(c) {
          c.addEventListener('change', Drupal.behaviors.aaaGalaTable.onQuanityChange)
        })

        Drupal.behaviors.aaaGalaTable.onQuanityChange()

        // Update the submit button event listener.
        const button = Drupal.behaviors.aaaGalaTable.form.querySelector('input[type="submit"]')
        button.addEventListener('click', Drupal.behaviors.aaaGalaTable.onSubmit)
      }
    },
    onQuanityChange: function(event) {
      let total = 0

      Drupal.behaviors.aaaGalaTable.form.querySelectorAll('input[type="number"][data-drupal-selector$=quantity]').forEach(function(c) {
        let value = c.value

        if (value.length === 0) {
          value = 0
        }

        total = total + (parseInt(c.getAttribute('data-amount') * value))
      })

      Drupal.behaviors.aaaGalaTable.amount.value = total
    },
    onSubmit: function (event) {
      Drupal.behaviors.aaaGalaTable.amount.removeAttribute('disabled')
    }
  }
})(Drupal, drupalSettings)
