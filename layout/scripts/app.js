
import '../icons/browserconfig.xml';
import '../icons/site.webmanifest';
import '../styles/app.scss';

import '../images/cyon-logo.svg'
import '../images/logo-contao-association.svg'

// automatically add all images to the manifest.json
// const imagesCtx = require.context('../images', false, /\.(png|jpg|jpeg|gif|ico|svg|webp)$/);
// imagesCtx.keys().forEach(imagesCtx);


const registration = document.querySelector('.mod_registration .membership, .mod_personalData .membership');
if (registration) {
    const amount = registration.querySelector('.widget-text');
    const selected = registration.querySelector('input[name="membership"]:checked');
    if (!selected || selected.value !== 'support') {
        amount.classList.add('invisible');
    }

    registration.querySelectorAll('input[name="membership"]').forEach((el) => {
        el.addEventListener('change', () => {
            if (el.value === 'support') {
                amount.classList.remove('invisible');
            } else {
                amount.classList.add('invisible');

                if (amount.querySelector('input').value < 200) {
                    amount.querySelector('input').value = 200;
                }
            }
        })
    })
}
