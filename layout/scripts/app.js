
import '../styles/app.scss';

// automatically add all images to the manifest.json
const imagesCtx = require.context('../images', false, /\.(png|jpg|jpeg|gif|ico|svg|webp)$/);
imagesCtx.keys().forEach(imagesCtx);


const registration = document.querySelector('.mod_registration .membership');
if (registration) {
    const member = registration.querySelector('.widget-checkbox');
    const selected = registration.querySelector('input[name="membership"]:checked');
    const valid = ['support25', 'support50', 'sponsor', 'gold_sponsor', 'diamond_sponsor'];
    if (!selected || !valid.includes(selected.value)) {
        member.classList.add('invisible');
    }

    registration.querySelectorAll('input[name="membership"]').forEach((el) => {
        el.addEventListener('change', () => {
            if (valid.includes(el.value)) {
                member.classList.remove('invisible');
            } else {
                member.classList.add('invisible');
            }
        })
    })
}
