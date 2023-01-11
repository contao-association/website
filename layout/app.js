
import './styles/app.scss';

// automatically add all images to the manifest.json
const imagesCtx = require.context('./images', false, /\.(png|jpg|jpeg|gif|ico|svg|webp)$/);
imagesCtx.keys().forEach(imagesCtx);
