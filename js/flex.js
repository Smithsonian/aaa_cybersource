(function(Drupal, drupalSettings, Flex) {
  Drupal.behaviors.aaaWebformTemplates = {
    attach: async function(context, settings) {
      // Sets up the Microform.
      Drupal.behaviors.aaaWebformTemplates.formName = context.querySelector('.webform-submission-form').getAttribute('title')
      const json = await Drupal.behaviors.aaaWebformTemplates.fetchToken()
      const flex = new Flex(json);
      const myStyles = {
        'input': {
          'font-size': '16px',
          'font-family': '"GT Walsheim", Helvetica, Arial, sans-serif',
          'color': '#495057'
        },
        ':focus': { 'color': 'black' },
        ':disabled': { 'cursor': 'not-allowed' },
        'valid': { 'color': '#495057' },
        'invalid': { 'color': '#EE2D24' }
      }
      const microform = flex.microform({ styles: myStyles })
      const number = microform.createField('number', { placeholder: 'Enter card number' })
      // If Credit Card number loads correctly, remove message element.
      number.on('load', function(err) {
        button.removeAttribute('disabled')
        button.classList.toggle('disabled', false)
        document.querySelector('#not-loaded-warning').remove()
        Drupal.behaviors.aaaWebformTemplates.log(`Flex microform on ${Drupal.behaviors.aaaWebformTemplates.formName} loaded`)
      })
      const securityCode = microform.createField('securityCode', { placeholder: '•••' })

      // Use some logic to prevent form from submitting when Cybersource fails to load.
      const button = document.querySelector('.webform-submission-form input[type="submit"]')
      button.setAttribute('disabled', true)
      button.classList.toggle('disabled', true)

      // Create message element.
      if (!document.querySelector('#not-loaded-warning')) {
        const notLoadedElement = document.createElement('div')
        notLoadedElement.setAttribute('id', 'not-loaded-warning')
        const notLoadedElementTextChild = document.createTextNode('Payment processor did not load correctly. Unable to submit this form.')
        const buttonParent = button.parentElement
        notLoadedElement.appendChild(notLoadedElementTextChild)
        buttonParent.appendChild(notLoadedElement)
      }

      // Check for .is-required fields
      if (context.querySelectorAll('.is-invalid').length > 0) {
        Drupal.behaviors.aaaWebformTemplates.log(`Submit ${Drupal.behaviors.aaaWebformTemplates.formName} error, reason ${context.querySelectorAll('.is-invalid').length} required fields missing`)
      }
      // Identify the containers to replace.
      number.load('#edit-card-number')
      securityCode.load('#edit-cvn')

      number.on('change', function(data) {
        const couldBeValid = data.couldBeValid
        const empty = data.empty

        if (empty === false && couldBeValid === false) {
          document.querySelector('#edit-card-number').parentElement.querySelector('#card-number-notification').innerHTML = 'Credit card number is invalid.'
          document.querySelector('#edit-card-number').classList.toggle('is-invalid', true)
        }
        else {
          document.querySelector('#edit-card-number').parentElement.querySelector('#card-number-notification').innerHTML = ''
        }

        if (couldBeValid === true) {
          document.querySelector('#edit-card-number').classList.toggle('is-invalid', false)
        }

        if (data.card.length === 1) {
          const type = data.card[0].name === 'amex' ? 'american express' : data.card[0].name
          const currentTypeInputValue = document.querySelector('input[name="card_type"]:checked')?.value
          const majorTypes = ['american express', 'discover', 'mastercard', 'visa']

          if (majorTypes.includes(type) === true && type !== currentTypeInputValue) {
            document.querySelector('input[name="card_type"][value="' + type + '"]').checked = true
          }
        }
      })

      // Update the submit button event listener.
      button.addEventListener('click', Drupal.behaviors.aaaWebformTemplates.payButton)

      Drupal.behaviors.aaaWebformTemplates.microform = microform
      Drupal.behaviors.aaaWebformTemplates.number = number
      Drupal.behaviors.aaaWebformTemplates.securityCode = securityCode

      // Add aria-invalid to all fields which are marked as required.
      if (context.querySelectorAll('input[required]').length > 0) {
        context.querySelectorAll('.form-control[required="required"]').forEach(function (i) {
          i.setAttribute('aria-invalid', false)
        })
      }

      // If after 15s the not-loaded-warning persists, log it.
      setTimeout(function() {
        if (document.querySelector('#not-loaded-warning') !== null) {
          Drupal.behaviors.aaaWebformTemplates.log(`Flex microform on ${Drupal.behaviors.aaaWebformTemplates.formName} did not load after 15s.`)
        }
      }, 15000)
    },
    fetchToken: async function() {
      let webform_id = drupalSettings.aaa_cybersource.webform

      // Alternative look-up. May not be necessary.
      if (!webform_id) {
        const webform = document.querySelector('.webform-submission-form')
        const id = webform.getAttribute('id')
        webform_id = id.replace('webform-submission-', '').replace('-add-form', '').replaceAll('-', '_')
      }

      const token = await fetch(`/api/cybersource/token/${webform_id}`)
      .then(function(res) {
        return res.json()
      })
      .catch(function(error) {
        console.error(error)
      })

      return token
    },
    payButton: function(event) {
      event.preventDefault()

      event.target.classList.toggle('disabled', true)
      event.target.setAttribute('disabled', true)
      event.target.classList.toggle('submitting', true)

      const submittingElement = document.createElement('div')
      submittingElement.setAttribute('id', 'submitting')
      submittingElement.setAttribute('role', 'alert')
      const submittingElementTextChild = document.createTextNode('Submitting payment, please wait.')
      const buttonParent = event.target.parentElement
      submittingElement.appendChild(submittingElementTextChild)
      buttonParent.appendChild(submittingElement)

      const expirationMonth = parseInt(document.querySelector('[data-drupal-selector="edit-expiration-month"]').value)
        .toLocaleString('en-US', {
          minimumIntegerDigits: 2,
          useGrouping: false
        })

      const expirationYear = parseInt(document.querySelector('[data-drupal-selector="edit-expiration-year"]').value)
        .toLocaleString('en-US', {
          minimumIntegerDigits: 4,
          useGrouping: false
        })

      const options = {
        expirationMonth: expirationMonth,
        expirationYear: expirationYear
      }

      Drupal.behaviors.aaaWebformTemplates.log(`Submitting ${Drupal.behaviors.aaaWebformTemplates.formName}`)

      Drupal.behaviors.aaaWebformTemplates.microform.createToken(options, function(error, token) {
        if (error) {
          event.target.classList.toggle('disabled', false)
          event.target.removeAttribute('disabled')
          event.target.classList.toggle('submitting', false)

          if (document.querySelector('#submitting')) {
            document.querySelector('#submitting').remove()
          }

          // https://developer.cybersource.com/docs/cybs/en-us/digital-accept-flex/developer/all/rest/digital-accept-flex/microform-integ-v2/api-reference-v2/class-microform-error-v2.html
          if (error.reason && error.reason === 'CREATE_TOKEN_VALIDATION_FIELDS') {
            const details = error.details

            Drupal.behaviors.aaaWebformTemplates.log(`Submit ${Drupal.behaviors.aaaWebformTemplates.formName} error, reason ${error.reason}`)
            Drupal.behaviors.aaaWebformTemplates.log(`User Agent ${navigator.userAgent}`)

            // Handle errors.
            details.forEach(function(d) {
              if (d.location === 'number') {
                document.querySelector('#card-number-notification').innerHTML = 'Validation error. Check that the credit card number is valid.'
                Drupal.behaviors.aaaWebformTemplates.number._container.classList.toggle('is-invalid', true)
                Drupal.behaviors.aaaWebformTemplates.log(`reason ${error.reason}, Validation error. Check that the credit card number is valid.`)
              }

              if (d.location === 'securityCode') {
                document.querySelector('#cvn-notification').innerHTML = 'Validation error. Check that the credit card CVN is valid.'
                Drupal.behaviors.aaaWebformTemplates.securityCode._container.classList.toggle('is-invalid', true)
                Drupal.behaviors.aaaWebformTemplates.log(`reason ${error.reason}, Validation error. Check that the credit card CVN is valid.`)
              }
            })
          }
          else if (error.reason && error.reason === 'CREATE_TOKEN_NO_FIELDS_LOADED') {
            Drupal.behaviors.aaaWebformTemplates.log(`Submit ${Drupal.behaviors.aaaWebformTemplates.formName} error, reason ${error.reason}`)
            Drupal.behaviors.aaaWebformTemplates.log(`User Agent ${navigator.userAgent}`)
            document.querySelector('#card-number-notification').innerHTML = 'Payment platform has not loaded.'
            Drupal.behaviors.aaaWebformTemplates.number._container.classList.toggle('is-invalid', true)
            document.querySelector('#cvn-notification').innerHTML = 'Payment platform has not loaded.'
            Drupal.behaviors.aaaWebformTemplates.securityCode._container.classList.toggle('is-invalid', true)
          }
          else if (error.reason && error.reason === 'CREATE_TOKEN_VALIDATION_SERVERSIDE') {
            Drupal.behaviors.aaaWebformTemplates.log(`Submit ${Drupal.behaviors.aaaWebformTemplates.formName} error, reason ${error.reason}`)
            Drupal.behaviors.aaaWebformTemplates.log(`User Agent ${navigator.userAgent}`)

            error.details.forEach(function(detail) {
              const location = detail.location
              const message = detail.message

              if (location === 'expirationYear') {
                const elementParent = document.querySelector('.form-item-expiration-year')
                const notify = document.createElement('div')
                notify.setAttribute('role', 'alert')
                notify.innerHTML = message
                notify.style.color = 'red'
                elementParent.appendChild(notify)
                Drupal.behaviors.aaaWebformTemplates.log(`reason ${error.reason}, message ${message}`)
              } else if (location === 'expirationMonth') {
                const elementParent = document.querySelector('.form-item-expiration-month')
                const notify = document.createElement('div')
                notify.setAttribute('role', 'alert')
                notify.innerHTML = message
                notify.style.color = 'red'
                elementParent.appendChild(notify)
                Drupal.behaviors.aaaWebformTemplates.log(`reason ${error.reason}, message ${message}`)
              }
            })
          }
          else {
            console.error(error)
            Drupal.behaviors.aaaWebformTemplates.log(`Submit ${Drupal.behaviors.aaaWebformTemplates.formName} error, reason ${error.reason}`)
            Drupal.behaviors.aaaWebformTemplates.log(`User Agent ${navigator.userAgent}`)
          }
        } else {
          document.querySelector('input[data-drupal-selector="token"]').value = token

          Drupal.behaviors.aaaWebformTemplates.log(`Submit Form Flex Microform SUCCESS.`)
          if (document.querySelector('input[data-drupal-selector="token"]').value.length > 0) {
            document.querySelector('form.webform-submission-form').submit()
          }
        }
      })
    },
    log: function(message) {
      if (drupalSettings.aaa_cybersource.logging === true) {
        fetch('/api/cybersource/logging', {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            message: message
          })
        })
      }
    },
    formName: '',
  }
})(Drupal, drupalSettings, Flex)
